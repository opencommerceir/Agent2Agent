<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * Mirrors Core's CheckPermissionAction: execute() is the primary query
 * (bool), authorize() is a throw-on-deny convenience wrapper for call
 * sites that want an assertion instead of an if. A product with no
 * Inventory record at all is treated as zero available (a conservative
 * default that blocks overselling untracked stock, rather than treating
 * "not tracked" as "unlimited").
 */
final class CheckInventoryAction
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $productId, int $tenantId, Quantity $requestedQuantity): bool
    {
        $inventory = $this->inventories->findByProduct($productId, $tenantId);
        $available = $inventory ? $inventory->available() : 0;

        return $available >= $requestedQuantity->value();
    }

    public function authorize(int $productId, int $tenantId, Quantity $requestedQuantity): void
    {
        if (! $this->execute($productId, $tenantId, $requestedQuantity)) {
            throw new InsufficientInventoryException(
                "Insufficient inventory for product [{$productId}]: requested {$requestedQuantity->value()}."
            );
        }
    }
}
