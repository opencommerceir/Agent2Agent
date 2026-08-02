<?php

namespace App\Modules\Commerce\Application\Jobs;

use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\Events\BulkOperationCompleted;
use App\Modules\Commerce\Domain\Events\BulkOperationFailed;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds an Orders CSV export (`BulkOperationType::ExportOrders`) — no
 * input file, no per-row failure concept (a query either returns the row
 * or it doesn't, rule stated explicitly in this stage's own brief): every
 * row counts as a success once the file is written.
 *
 * Constructor takes only primitive values (same serialization reasoning
 * as ProcessBulkImportJob — see that Job's own docblock).
 */
final class ProcessBulkExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const MAX_ROWS = 10000;

    public function __construct(
        public readonly int $bulkOperationId,
        public readonly int $tenantId,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?string $status = null,
    ) {
    }

    public function handle(
        BulkOperationRepositoryInterface $operations,
        OrderRepositoryInterface $orders,
        CustomerRepositoryInterface $customers,
    ): void {
        $operation = $operations->findById($this->bulkOperationId, $this->tenantId);

        if (! $operation) {
            return;
        }

        try {
            $from = $this->parseDate($this->startDate, endOfDay: false);
            $to = $this->parseDate($this->endDate, endOfDay: true);
            $status = $this->status !== null ? OrderStatus::from($this->status) : null;

            $matchedOrders = $orders->listByTenant($this->tenantId, $status, self::MAX_ROWS, $from, $to);

            $operation->start(count($matchedOrders));
            $operations->save($operation);

            $csv = $this->buildCsv($matchedOrders, $customers);

            $exportFilePath = "bulk_operations/exports/{$operation->id()}.csv";
            Storage::disk('public')->put($exportFilePath, $csv);

            $operation->setFilePath($exportFilePath);

            $rowCount = count($matchedOrders);
            $operation->recordProgress($rowCount, $rowCount, 0);
            $operations->save($operation);

            $operation->complete();
            $operations->save($operation);

            Event::dispatch(new BulkOperationCompleted($operation));
        } catch (Throwable $e) {
            $operation->fail();
            $operations->save($operation);
            Event::dispatch(new BulkOperationFailed($operation));
        }
    }

    /**
     * @param list<Order> $orders
     */
    private function buildCsv(array $orders, CustomerRepositoryInterface $customers): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['order_number', 'customer_email', 'total_amount', 'status', 'created_at']);

        foreach ($orders as $order) {
            $customerEmail = '';

            if ($order->customerId() !== null) {
                $customer = $customers->findById($order->customerId(), $this->tenantId);
                $customerEmail = $customer?->email()->value() ?? '';
            }

            fputcsv($handle, [
                $order->orderNumber()->value(),
                $customerEmail,
                number_format($order->total()->amount() / 100, 2, '.', ''),
                $order->status()->value,
                $order->createdAt()->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function parseDate(?string $date, bool $endOfDay): ?DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false) {
            return null;
        }

        // $to is treated as inclusive of the whole calendar day (rule
        // stated on OrderRepositoryInterface::listByTenant()'s own
        // docblock: "inclusive window") — a bare Y-m-d midnight timestamp
        // would otherwise exclude every Order placed later that same day.
        return $endOfDay ? $parsed->setTime(23, 59, 59) : $parsed;
    }
}
