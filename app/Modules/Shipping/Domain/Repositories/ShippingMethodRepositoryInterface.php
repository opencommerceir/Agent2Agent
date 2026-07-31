<?php

namespace App\Modules\Shipping\Domain\Repositories;

use App\Modules\Shipping\Domain\Entities\ShippingMethod;

interface ShippingMethodRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?ShippingMethod;

    /**
     * @return list<ShippingMethod>
     */
    public function list(int $tenantId, ?bool $isActive): array;

    public function save(ShippingMethod $method): ShippingMethod;
}
