<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Discount;

/**
 * Not explicitly requested this stage, but Discount has its own Entity,
 * Eloquent Model and migration — persisting it via direct Eloquent calls
 * from an Action would break the Repository convention every other
 * aggregate in this codebase follows.
 */
interface DiscountRepositoryInterface
{
    public function save(Discount $discount): Discount;

    /**
     * @return list<Discount>
     */
    public function listByOrder(int $orderId): array;
}
