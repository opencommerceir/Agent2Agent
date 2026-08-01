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

    /**
     * Added for Analytics' own Conversion Rate KPI (Phase 4 Stage 6,
     * §7.18) — nothing before this needed a bare count of Carts created
     * in a window, only single-Cart lookups or the abandoned-Cart scan
     * existed. A plain `COUNT(*)`, not a full Entity hydration (rule §e
     * "از Eloquent aggregates استفاده کن, نه loop در PHP").
     */
    public function countCreatedBetween(int $tenantId, DateTimeImmutable $start, DateTimeImmutable $end): int;

    public function save(Cart $cart): Cart;
}
