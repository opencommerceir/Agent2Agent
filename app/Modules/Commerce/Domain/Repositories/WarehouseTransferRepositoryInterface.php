<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\WarehouseTransfer;

/**
 * Owns WarehouseTransferItem persistence too (they're frozen at creation
 * and never looked up independently) — the same "repo owns its child
 * records" shape CRM's TicketRepositoryInterface (TicketComment) and
 * Finance's InvoiceRepositoryInterface (InvoiceItem) already establish.
 */
interface WarehouseTransferRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?WarehouseTransfer;

    public function save(WarehouseTransfer $transfer): WarehouseTransfer;
}
