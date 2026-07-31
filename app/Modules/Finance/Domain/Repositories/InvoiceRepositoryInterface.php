<?php

namespace App\Modules\Finance\Domain\Repositories;

use App\Modules\Finance\Domain\Entities\Invoice;
use App\Modules\Finance\Domain\ValueObjects\InvoiceStatus;

interface InvoiceRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Invoice;

    public function invoiceNumberExists(string $invoiceNumber, int $tenantId): bool;

    /**
     * @return list<Invoice>
     */
    public function list(int $tenantId, ?InvoiceStatus $status, ?int $customerId, int $limit): array;

    public function save(Invoice $invoice): Invoice;
}
