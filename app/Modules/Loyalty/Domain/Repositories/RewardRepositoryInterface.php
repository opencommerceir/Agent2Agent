<?php

namespace App\Modules\Loyalty\Domain\Repositories;

use App\Modules\Loyalty\Domain\Entities\Reward;

interface RewardRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Reward;

    /**
     * @return list<Reward>
     */
    public function list(int $tenantId, ?bool $isActive): array;

    public function save(Reward $reward): Reward;
}
