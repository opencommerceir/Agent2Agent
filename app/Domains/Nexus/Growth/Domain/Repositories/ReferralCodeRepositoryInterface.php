<?php

namespace App\Domains\Nexus\Growth\Domain\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralCode;

interface ReferralCodeRepositoryInterface
{
    public function findByBusinessId(int $businessId): ?ReferralCode;

    public function findByCode(string $code): ?ReferralCode;

    public function codeExists(string $code): bool;

    public function save(ReferralCode $code): ReferralCode;
}
