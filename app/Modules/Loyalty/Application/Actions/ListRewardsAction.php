<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\RewardData;
use App\Modules\Loyalty\Domain\Repositories\RewardRepositoryInterface;

final class ListRewardsAction
{
    public function __construct(
        private readonly RewardRepositoryInterface $rewards,
    ) {
    }

    /**
     * @param array{is_active?: bool} $input
     * @return array{rewards: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $isActive = array_key_exists('is_active', $input) ? (bool) $input['is_active'] : null;

        $rewards = $this->rewards->list($tenantId, $isActive);

        return [
            'rewards' => array_map(fn ($reward) => RewardData::fromEntity($reward)->toArray(), $rewards),
        ];
    }
}
