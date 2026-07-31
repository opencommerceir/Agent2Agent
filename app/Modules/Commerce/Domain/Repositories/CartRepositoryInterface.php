<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Domain\Entities\Cart;
use DateTimeImmutable;

interface CartRepositoryInterface
{
    public function findActiveByOwner(int $tenantId, MemberType $ownerType, int $ownerId): ?Cart;

    public function findById(int $id, int $tenantId): ?Cart;

    /**
     * Added for the scheduled `commerce:check-abandoned-carts` command
     * (HANDOFF §8.23/§8.27): every Cart still `Active` whose `updated_at`
     * is older than $before. Nothing before this needed to enumerate
     * Carts in bulk — only single-cart lookups by owner or id existed.
     *
     * @return list<Cart>
     */
    public function findStaleActive(int $tenantId, DateTimeImmutable $before): array;

    public function save(Cart $cart): Cart;
}
