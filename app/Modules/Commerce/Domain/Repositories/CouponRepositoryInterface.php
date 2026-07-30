<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;

interface CouponRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Coupon;

    public function findByCode(CouponCode $code, int $tenantId): ?Coupon;

    public function codeExists(CouponCode $code, int $tenantId): bool;

    public function save(Coupon $coupon): Coupon;
}
