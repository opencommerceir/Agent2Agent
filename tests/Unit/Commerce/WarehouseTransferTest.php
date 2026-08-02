<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\WarehouseTransfer;
use App\Modules\Commerce\Domain\Entities\WarehouseTransferItem;
use App\Modules\Commerce\Domain\ValueObjects\TransferStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WarehouseTransferTest extends TestCase
{
    private function makeTransfer(): WarehouseTransfer
    {
        return WarehouseTransfer::request(
            tenantId: 1,
            sourceWarehouseId: 1,
            destinationWarehouseId: 2,
            requestedBy: 10,
            items: [new WarehouseTransferItem(productId: 100, variantId: null, quantity: 5)],
        );
    }

    public function test_request_startsPending(): void
    {
        $transfer = $this->makeTransfer();

        $this->assertSame(TransferStatus::Pending, $transfer->status());
        $this->assertNull($transfer->approvedBy());
        $this->assertNull($transfer->completedAt());
    }

    public function test_request_withSameSourceAndDestination_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WarehouseTransfer::request(1, 1, 1, 10, [new WarehouseTransferItem(100, null, 5)]);
    }

    public function test_request_withNoItems_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WarehouseTransfer::request(1, 1, 2, 10, []);
    }

    public function test_approve_fromPending_setsApprovedByAndStatus(): void
    {
        $transfer = $this->makeTransfer();

        $transfer->approve(20);

        $this->assertSame(TransferStatus::Approved, $transfer->status());
        $this->assertSame(20, $transfer->approvedBy());
    }

    public function test_complete_fromApproved_setsCompletedAt(): void
    {
        $transfer = $this->makeTransfer();
        $transfer->approve(20);

        $transfer->complete();

        $this->assertSame(TransferStatus::Completed, $transfer->status());
        $this->assertNotNull($transfer->completedAt());
    }

    public function test_complete_fromPending_throws(): void
    {
        $transfer = $this->makeTransfer();

        $this->expectException(InvalidArgumentException::class);

        $transfer->complete();
    }

    public function test_cancel_fromPending_succeeds(): void
    {
        $transfer = $this->makeTransfer();

        $transfer->cancel();

        $this->assertSame(TransferStatus::Cancelled, $transfer->status());
    }

    public function test_approve_fromCompleted_throws(): void
    {
        $transfer = $this->makeTransfer();
        $transfer->approve(20);
        $transfer->complete();

        $this->expectException(InvalidArgumentException::class);

        $transfer->approve(20);
    }

    public function test_items_areFrozenAtCreation(): void
    {
        $transfer = $this->makeTransfer();

        $this->assertCount(1, $transfer->items());
        $this->assertSame(100, $transfer->items()[0]->productId());
        $this->assertSame(5, $transfer->items()[0]->quantity());
    }
}
