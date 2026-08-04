<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\AppliedDiscount;

interface AppliedDiscountRepositoryInterface
{
    /**
     * @return list<AppliedDiscount>
     */
    public function listByCart(int $cartId, int $tenantId): array;

    /**
     * Deletes every existing AppliedDiscount row for this Cart and
     * inserts `$discounts` in their place, in one call — the identical
     * "small, frequently-mutated collection, delete-and-reinsert on every
     * save" shape `EloquentCartRepository::save()` already uses for
     * `CartItem` (§7.2), never a partial update. `ApplyDiscountsToCartAction`
     * is the only caller — every `commerce.discount.apply` recomputes the
     * whole set from scratch and replaces it wholesale.
     *
     * @param list<AppliedDiscount> $discounts
     */
    public function replaceForCart(int $cartId, int $tenantId, array $discounts): void;
}
