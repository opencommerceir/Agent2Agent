<?php

namespace App\Modules\Commerce\Application\Jobs;

use App\Modules\Commerce\Domain\Entities\BulkOperationItem;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Events\BulkOperationCompleted;
use App\Modules\Commerce\Domain\Events\BulkOperationFailed;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationStatus;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Throwable;

/**
 * Works through a Bulk Price/Status/Inventory Update's own id list — no CSV
 * involved anywhere in this Job (that's `ProcessBulkImportJob`'s concern,
 * a sibling slice of this same stage). Constructor args are plain
 * primitives/arrays only (ids, strings, the update payload itself), never
 * a Repository — those get method-injected into `handle()` by the queue
 * worker instead, the same way a Controller action gets its own
 * dependencies, so this Job still (de)serializes sanely across a real
 * queue backend rather than just the `sync` driver tests run under.
 *
 * Processes in chunks of 100, each chunk inside its own DB::transaction().
 * The per-row try/catch deliberately lives INSIDE that transaction's
 * closure: one bad id's caught exception is just a row result (it never
 * rolls back the other ids already updated in the same chunk), while a
 * genuinely uncaught/fatal error escaping the closure still rolls back the
 * whole chunk, exactly as a real DB::transaction() should.
 *
 * The outer try/catch is a different failure class entirely — an
 * unrecoverable, whole-operation problem (a malformed payload, an unknown
 * update type) that happens before/between chunks rather than for one
 * row — and maps to BulkOperation::fail(), not a per-row failure count.
 */
final class ProcessBulkUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 100;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $bulkOperationId,
        public readonly int $tenantId,
        public readonly string $updateType,
        public readonly array $payload,
    ) {
    }

    public function handle(
        BulkOperationRepositoryInterface $operations,
        ProductRepositoryInterface $products,
        InventoryRepositoryInterface $inventories,
    ): void {
        $operation = $operations->findById($this->bulkOperationId, $this->tenantId);

        if (! $operation) {
            // Nothing sane to do — the BulkOperation this Job was meant to
            // report against no longer exists.
            return;
        }

        try {
            $rows = match ($this->updateType) {
                'price', 'status' => $this->payload['product_ids'],
                'inventory' => $this->payload['updates'],
                default => throw new RuntimeException("Unknown bulk update type [{$this->updateType}]."),
            };

            $operation->start(count($rows));
            $operation = $operations->save($operation);

            $processedCount = 0;
            $successCount = 0;
            $failedCount = 0;

            foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
                $chunkSuccess = 0;
                $chunkFailed = 0;

                DB::transaction(function () use ($chunk, $operation, $operations, $products, $inventories, &$chunkSuccess, &$chunkFailed): void {
                    foreach ($chunk as $index => $row) {
                        $rowNumber = $index + 1;
                        $data = $this->updateType === 'inventory' ? $row : ['product_id' => $row];

                        try {
                            $entityId = $this->applyRow($row, $products, $inventories);

                            $operations->saveItem(
                                $operation->id(),
                                $this->tenantId,
                                BulkOperationItem::success($rowNumber, $data, $entityId),
                            );
                            $chunkSuccess++;
                        } catch (Throwable $e) {
                            $operations->saveItem(
                                $operation->id(),
                                $this->tenantId,
                                BulkOperationItem::failed($rowNumber, $data, $e->getMessage()),
                            );
                            $chunkFailed++;
                        }
                    }
                });

                $processedCount += count($chunk);
                $successCount += $chunkSuccess;
                $failedCount += $chunkFailed;

                $operation->recordProgress($processedCount, $successCount, $failedCount);
                $operation = $operations->save($operation);
            }

            $operation->complete();
            $operation = $operations->save($operation);

            if ($operation->status() === BulkOperationStatus::Failed) {
                Event::dispatch(new BulkOperationFailed($operation));
            } else {
                Event::dispatch(new BulkOperationCompleted($operation));
            }
        } catch (Throwable $e) {
            $operation->fail();
            $operations->save($operation);
            Event::dispatch(new BulkOperationFailed($operation));
        }
    }

    /**
     * Applies one row's mutation and returns the affected Product's id —
     * used as every BulkOperationItem's entityId regardless of update
     * type, since price/status/inventory rows all ultimately act on a
     * Product's own catalog identity. Throws freely; the caller's per-row
     * try/catch is what turns that into a recorded failure instead of an
     * aborted chunk.
     *
     * @param int|array{product_id: int, variant_id: ?int, quantity: int} $row
     */
    private function applyRow(
        int|array $row,
        ProductRepositoryInterface $products,
        InventoryRepositoryInterface $inventories,
    ): int {
        return match ($this->updateType) {
            'price' => $this->applyPriceUpdate($row, $products),
            'status' => $this->applyStatusUpdate($row, $products),
            'inventory' => $this->applyInventoryUpdate($row, $inventories),
        };
    }

    private function applyPriceUpdate(int $productId, ProductRepositoryInterface $products): int
    {
        $product = $products->findById($productId, $this->tenantId);

        if (! $product) {
            throw new RuntimeException("Product [{$productId}] does not exist for this tenant.");
        }

        $product->update(
            categoryId: $product->categoryId(),
            name: $product->name(),
            description: $product->description(),
            price: Money::fromAmount($this->payload['price_amount'], $this->payload['price_currency']),
            status: $product->status(),
            attributes: $product->attributes(),
        );

        $products->save($product);

        return $productId;
    }

    private function applyStatusUpdate(int $productId, ProductRepositoryInterface $products): int
    {
        $product = $products->findById($productId, $this->tenantId);

        if (! $product) {
            throw new RuntimeException("Product [{$productId}] does not exist for this tenant.");
        }

        // Re-validated defensively even though BulkStatusUpdateAction
        // already validated it once — a Job can theoretically be retried
        // or replayed independently of the Action that first dispatched it.
        $status = ProductStatus::from($this->payload['new_status']);

        $product->update(
            categoryId: $product->categoryId(),
            name: $product->name(),
            description: $product->description(),
            price: $product->price(),
            status: $status,
            attributes: $product->attributes(),
        );

        $products->save($product);

        return $productId;
    }

    /**
     * @param array{product_id: int, variant_id: ?int, quantity: int} $update
     */
    private function applyInventoryUpdate(array $update, InventoryRepositoryInterface $inventories): int
    {
        $productId = $update['product_id'];
        $variantId = $update['variant_id'] ?? null;

        $inventory = $inventories->findByProduct($productId, $this->tenantId, $variantId)
            ?? Inventory::stock($this->tenantId, $productId, 0, $variantId);

        $inventory->setQuantityOnHand($update['quantity']);
        $inventories->save($inventory);

        return $productId;
    }
}
