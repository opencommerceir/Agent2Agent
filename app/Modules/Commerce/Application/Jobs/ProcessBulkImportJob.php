<?php

namespace App\Modules\Commerce\Application\Jobs;

use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\Entities\BulkOperationItem;
use App\Modules\Commerce\Domain\Entities\Customer;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Entities\Product;
use App\Modules\Commerce\Domain\Events\BulkOperationCompleted;
use App\Modules\Commerce\Domain\Events\BulkOperationFailed;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Services\CsvParserInterface;
use App\Modules\Commerce\Domain\Services\CsvValidatorInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationStatus;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Streams a Product/Customer CSV (`BulkOperationType::ImportProducts` or
 * `ImportCustomers`, decided by the BulkOperation's own type()) through
 * `CsvParserInterface` in two passes — one to learn the real row count for
 * start(), a second to actually process — and upserts each row in chunks
 * of 100 (rule §д.2), never materializing the whole file into one array.
 *
 * Constructor takes only primitive ids: a queued Job's constructor
 * arguments are serialized onto the queue, so Repositories/Services are
 * method-injected into handle() itself instead (Laravel resolves handle()'s
 * own parameters from the container exactly like a Controller action).
 *
 * Required columns:
 *  - ImportProducts:  sku, name, price, currency (hard-required — a row
 *    missing/blank any of these fails via CsvValidatorInterface before
 *    ever reaching the Product upsert). category, status, stock_quantity
 *    are enrichment: a blank/unresolved category leaves categoryId null
 *    (CategoryRepositoryInterface::findByName()'s own docblock), a blank
 *    status defaults to Draft, a blank/non-numeric stock_quantity defaults
 *    to 0 — none of these three ever fail the row on their own.
 *  - ImportCustomers: email, first_name, last_name (hard-required). phone
 *    is enrichment (blank -> null).
 *
 * Per-row try/catch lives *inside* the DB::transaction() closure for each
 * chunk (rule stated explicitly in this stage's own brief) so one bad
 * row's caught exception can never roll back the other 99 rows already
 * written in that same chunk — only a genuinely uncaught/fatal error
 * (a DB connection loss, for example) rolls back the whole chunk.
 *
 * The outer try/catch around the whole run is for the other kind of
 * failure entirely — BulkOperation::fail()'s own use case: something
 * unrecoverable that isn't a single row's fault (the file vanishing
 * between ImportProductsAction's existence check and this Job actually
 * running, for example).
 */
final class ProcessBulkImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CHUNK_SIZE = 100;

    public function __construct(
        public readonly int $bulkOperationId,
        public readonly int $tenantId,
    ) {
    }

    public function handle(
        BulkOperationRepositoryInterface $operations,
        CsvParserInterface $parser,
        CsvValidatorInterface $validator,
        ProductRepositoryInterface $products,
        CategoryRepositoryInterface $categories,
        InventoryRepositoryInterface $inventories,
        CustomerRepositoryInterface $customers,
    ): void {
        $operation = $operations->findById($this->bulkOperationId, $this->tenantId);

        if (! $operation) {
            // Nothing sane to report progress against — not a retryable
            // condition (a not-found BulkOperation will never appear later).
            return;
        }

        try {
            // $operation->filePath() is relative to the 'local' disk's own
            // bulk_operations/ directory, not the disk root — see
            // ImportProductsAction's own docblock for the full convention.
            $absolutePath = Storage::disk('local')->path('bulk_operations/'.$operation->filePath());

            $totalRows = 0;

            foreach ($parser->parse($absolutePath) as $ignored) {
                $totalRows++;
            }

            $operation->start($totalRows);
            $operations->save($operation);

            $requiredColumns = match ($operation->type()) {
                BulkOperationType::ImportProducts => ['sku', 'name', 'price', 'currency'],
                BulkOperationType::ImportCustomers => ['email', 'first_name', 'last_name'],
                default => throw new InvalidArgumentException(
                    "ProcessBulkImportJob cannot process BulkOperationType [{$operation->type()->value}]."
                ),
            };

            $successTotal = 0;
            $failedTotal = 0;
            $failedItems = [];
            $chunk = [];

            foreach ($parser->parse($absolutePath) as $rowNumber => $row) {
                $chunk[$rowNumber] = $row;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    [$successTotal, $failedTotal, $failedItems] = $this->processChunk(
                        $chunk, $operation, $operations, $validator, $requiredColumns,
                        $products, $categories, $inventories, $customers,
                        $successTotal, $failedTotal, $failedItems,
                    );
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                [$successTotal, $failedTotal, $failedItems] = $this->processChunk(
                    $chunk, $operation, $operations, $validator, $requiredColumns,
                    $products, $categories, $inventories, $customers,
                    $successTotal, $failedTotal, $failedItems,
                );
            }

            if ($failedTotal > 0) {
                $errorFilePath = "bulk_operations/errors/{$operation->id()}.csv";
                Storage::disk('public')->put($errorFilePath, $this->buildErrorCsv($failedItems));
                $operation->setErrorFilePath($errorFilePath);
            }

            $operation->complete();
            $operations->save($operation);

            Event::dispatch(
                $operation->status() === BulkOperationStatus::Failed
                    ? new BulkOperationFailed($operation)
                    : new BulkOperationCompleted($operation)
            );
        } catch (Throwable $e) {
            $operation->fail();
            $operations->save($operation);
            Event::dispatch(new BulkOperationFailed($operation));
        }
    }

    /**
     * Processes one chunk (<= 100 rows) inside a single transaction,
     * returning the *running* [successTotal, failedTotal, failedItems]
     * accumulated so far (this chunk's own outcomes folded into whatever
     * was passed in) — recordProgress()'s own real-time tracking (rule
     * §д.5) needs the cumulative counts after every chunk, not just this
     * chunk's own.
     *
     * @param array<int, array<string, string>> $chunk row number => row
     * @param list<string> $requiredColumns
     * @param array<int, array{error: string, row: array<string, string>}> $failedItemsSoFar
     * @return array{0: int, 1: int, 2: array<int, array{error: string, row: array<string, string>}>}
     */
    private function processChunk(
        array $chunk,
        BulkOperation $operation,
        BulkOperationRepositoryInterface $operations,
        CsvValidatorInterface $validator,
        array $requiredColumns,
        ProductRepositoryInterface $products,
        CategoryRepositoryInterface $categories,
        InventoryRepositoryInterface $inventories,
        CustomerRepositoryInterface $customers,
        int $successTotal,
        int $failedTotal,
        array $failedItemsSoFar,
    ): array {
        DB::transaction(function () use (
            $chunk, $operation, $operations, $validator, $requiredColumns,
            $products, $categories, $inventories, $customers,
            &$successTotal, &$failedTotal, &$failedItemsSoFar,
        ) {
            foreach ($chunk as $rowNumber => $row) {
                try {
                    $validation = $validator->validateRow($row, $requiredColumns);

                    if (! $validation->isValid) {
                        throw new InvalidArgumentException(implode('; ', $validation->errors));
                    }

                    $entityId = match ($operation->type()) {
                        BulkOperationType::ImportProducts => $this->upsertProductRow(
                            $operation->tenantId(), $row, $products, $categories, $inventories,
                        ),
                        BulkOperationType::ImportCustomers => $this->upsertCustomerRow(
                            $operation->tenantId(), $row, $customers,
                        ),
                        default => throw new InvalidArgumentException('Unsupported bulk import type.'),
                    };

                    $operations->saveItem(
                        $operation->id(), $operation->tenantId(),
                        BulkOperationItem::success($rowNumber, $row, $entityId),
                    );
                    $successTotal++;
                } catch (Throwable $e) {
                    $operations->saveItem(
                        $operation->id(), $operation->tenantId(),
                        BulkOperationItem::failed($rowNumber, $row, $e->getMessage()),
                    );
                    $failedTotal++;
                    $failedItemsSoFar[$rowNumber] = ['error' => $e->getMessage(), 'row' => $row];
                }
            }
        });

        $operation->recordProgress($successTotal + $failedTotal, $successTotal, $failedTotal);
        $operations->save($operation);

        return [$successTotal, $failedTotal, $failedItemsSoFar];
    }

    /**
     * Upserts one CSV row into the Product catalog keyed by SKU, mirroring
     * SyncWooCommerceProductsAction::upsert()'s own shape exactly (find by
     * SKU, update in place or create) but reading from a CSV row instead
     * of a UCP payload, then sets the resulting Product's on-hand stock.
     *
     * @param array<string, string> $row
     */
    private function upsertProductRow(
        int $tenantId,
        array $row,
        ProductRepositoryInterface $products,
        CategoryRepositoryInterface $categories,
        InventoryRepositoryInterface $inventories,
    ): int {
        $sku = new SKU($row['sku']); // throws InvalidSKUException on bad format
        $priceAmount = $this->parsePriceToCents($row['price']);
        $price = Money::fromAmount($priceAmount, $row['currency']); // throws InvalidArgumentException on a bad currency code

        $statusRaw = trim((string) ($row['status'] ?? ''));
        $status = $statusRaw === '' ? ProductStatus::Draft : ProductStatus::from($statusRaw); // throws ValueError for an unknown status

        $categoryName = trim((string) ($row['category'] ?? ''));
        $categoryId = $categoryName !== '' ? $categories->findByName($categoryName, $tenantId)?->id() : null;

        $name = trim($row['name']);
        $existing = $products->findBySku($sku, $tenantId);

        if ($existing) {
            $existing->update(
                categoryId: $categoryId,
                name: $name,
                description: $existing->description(),
                price: $price,
                status: $status,
                attributes: $existing->attributes(),
            );
            $product = $products->save($existing);
        } else {
            $product = Product::create(
                tenantId: $tenantId,
                categoryId: $categoryId,
                name: $name,
                slug: Str::slug($name),
                description: null,
                sku: $sku,
                price: $price,
                status: $status,
            );
            $product = $products->save($product);
        }

        $stockRaw = trim((string) ($row['stock_quantity'] ?? ''));
        $quantityOnHand = $stockRaw !== '' && is_numeric($stockRaw) ? (int) $stockRaw : 0;

        $inventory = $inventories->findByProduct($product->id(), $tenantId);

        if ($inventory) {
            $inventory->setQuantityOnHand($quantityOnHand);
        } else {
            $inventory = Inventory::stock($tenantId, $product->id(), $quantityOnHand);
        }

        $inventories->save($inventory);

        return $product->id();
    }

    /**
     * @param array<string, string> $row
     */
    private function upsertCustomerRow(int $tenantId, array $row, CustomerRepositoryInterface $customers): int
    {
        $email = new Email(trim($row['email'])); // throws InvalidEmailException on bad format
        $firstName = trim($row['first_name']);
        $lastName = trim($row['last_name']);
        $phoneRaw = trim((string) ($row['phone'] ?? ''));
        $phone = $phoneRaw !== '' ? $phoneRaw : null;

        $existing = $customers->findByEmail($email, $tenantId);

        if ($existing) {
            $existing->update(
                firstName: $firstName,
                lastName: $lastName,
                email: $email,
                phone: $phone,
                defaultAddress: $existing->defaultAddress(),
                notes: $existing->notes(),
                status: $existing->status(),
            );
            $customer = $customers->save($existing);
        } else {
            $customer = Customer::register(
                tenantId: $tenantId,
                firstName: $firstName,
                lastName: $lastName,
                email: $email,
                phone: $phone,
            );
            $customer = $customers->save($customer);
        }

        return $customer->id();
    }

    /**
     * Mirrors WooCommerceProductMapper::toCents()'s own decimal-string-to-
     * integer-cents conversion, except a non-numeric price is this Job's
     * own concern to reject outright (WooCommerce's own mapper never sees
     * a hand-typed CSV cell, so it never needed this guard) — a bad price
     * must fail its row rather than silently import as 0.
     */
    private function parsePriceToCents(string $raw): int
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            throw new InvalidArgumentException("Invalid price [{$raw}]. Expected a decimal number, e.g. \"29.99\".");
        }

        return (int) round(((float) $trimmed) * 100);
    }

    /**
     * A small, self-contained fputcsv writer — deliberately not shared
     * with Analytics' own ReportExporter (Analytics depends on Commerce,
     * never the other way around); duplicating ~15 lines here is the same
     * "small, stable, cheaper to duplicate than share" tradeoff Money's own
     * cross-module duplication already establishes.
     *
     * @param array<int, array{error: string, row: array<string, string>}> $failedItems row number => outcome
     */
    private function buildErrorCsv(array $failedItems): string
    {
        $handle = fopen('php://temp', 'r+');

        $columnNames = $failedItems === [] ? [] : array_keys(reset($failedItems)['row']);
        fputcsv($handle, array_merge(['row_number', 'error_message'], $columnNames));

        foreach ($failedItems as $rowNumber => $item) {
            fputcsv($handle, array_merge([$rowNumber, $item['error']], array_values($item['row'])));
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
