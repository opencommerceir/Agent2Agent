<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\RewardData;
use App\Modules\Loyalty\Domain\Exceptions\RewardNotFoundException;
use App\Modules\Loyalty\Domain\Repositories\RewardRepositoryInterface;

final class GetRewardAction
{
    public function __construct(
        private readonly RewardRepositoryInterface $rewards,
    ) {
    }

    public function execute(int $rewardId, int $tenantId): RewardData
    {
        $reward = $this->rewards->findById($rewardId, $tenantId);

        if (! $reward) {
            throw new RewardNotFoundException("Reward [{$rewardId}] does not exist.");
        }

        return RewardData::fromEntity($reward);
    }
}
