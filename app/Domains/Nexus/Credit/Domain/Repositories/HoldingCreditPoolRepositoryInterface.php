<?php

namespace App\Domains\Nexus\Credit\Domain\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPool;

interface HoldingCreditPoolRepositoryInterface
{
    public function findByHoldingId(int $holdingId): ?HoldingCreditPool;

    public function save(HoldingCreditPool $pool): HoldingCreditPool;
}
