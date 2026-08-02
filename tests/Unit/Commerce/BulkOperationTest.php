<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationStatus;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BulkOperationTest extends TestCase
{
    public function test_create_startsPending(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);

        $this->assertSame(BulkOperationStatus::Pending, $operation->status());
        $this->assertSame(0, $operation->totalRows());
        $this->assertNull($operation->startedAt());
    }

    public function test_start_setsProcessingAndTotalRowsAndStartedAt(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);

        $operation->start(1000);

        $this->assertSame(BulkOperationStatus::Processing, $operation->status());
        $this->assertSame(1000, $operation->totalRows());
        $this->assertNotNull($operation->startedAt());
    }

    public function test_recordProgress_updatesCountersWhileProcessing(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);
        $operation->start(1000);

        $operation->recordProgress(100, 90, 10);

        $this->assertSame(100, $operation->processedRows());
        $this->assertSame(90, $operation->successRows());
        $this->assertSame(10, $operation->failedRows());
    }

    public function test_complete_withZeroFailures_isCompleted(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);
        $operation->start(100);
        $operation->recordProgress(100, 100, 0);

        $operation->complete();

        $this->assertSame(BulkOperationStatus::Completed, $operation->status());
        $this->assertNotNull($operation->completedAt());
    }

    public function test_complete_withSomeFailuresAndSomeSuccesses_isPartial(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);
        $operation->start(1000);
        $operation->recordProgress(1000, 990, 10);

        $operation->complete();

        $this->assertSame(BulkOperationStatus::Partial, $operation->status());
    }

    public function test_complete_withNoSuccessesAtAll_isFailed(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);
        $operation->start(10);
        $operation->recordProgress(10, 0, 10);

        $operation->complete();

        $this->assertSame(BulkOperationStatus::Failed, $operation->status());
    }

    public function test_complete_withZeroRows_isCompleted(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);
        $operation->start(0);

        $operation->complete();

        $this->assertSame(BulkOperationStatus::Completed, $operation->status());
    }

    public function test_fail_fromPending_isFailed(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);

        $operation->fail();

        $this->assertSame(BulkOperationStatus::Failed, $operation->status());
        $this->assertNotNull($operation->completedAt());
    }

    public function test_complete_fromPending_throws(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);

        $this->expectException(InvalidArgumentException::class);

        $operation->complete();
    }

    public function test_setFilePath_setsFilePath(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ExportOrders, 10);

        $operation->setFilePath('bulk_operations/exports/1.csv');

        $this->assertSame('bulk_operations/exports/1.csv', $operation->filePath());
    }

    public function test_start_fromCompleted_throws(): void
    {
        $operation = BulkOperation::create(1, BulkOperationType::ImportProducts, 10);
        $operation->start(0);
        $operation->complete();

        $this->expectException(InvalidArgumentException::class);

        $operation->start(10);
    }
}
