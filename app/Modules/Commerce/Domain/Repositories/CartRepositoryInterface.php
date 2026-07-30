<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Domain\Entities\Cart;

interface CartRepositoryInterface
{
    public function findActiveByOwner(int $tenantId, MemberType $ownerType, int $ownerId): ?Cart;

    public function findById(int $id, int $tenantId): ?Cart;

    public function save(Cart $cart): Cart;
}
