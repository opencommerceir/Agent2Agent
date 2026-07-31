<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\RewardData;
use App\Modules\Loyalty\Domain\Entities\Reward;
use App\Modules\Loyalty\Domain\Repositories\RewardRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\RewardType;
use InvalidArgumentException;

/**
 * One Action = one business operation: define a Reward Customers can
 * later spend points on. No RewardWasCreated event — the request's own
 * event list names only 4 (PointsWereEarned/Redeemed/Expired,
 * RewardWasRedeemed), the same "not every creation needs an event" shape
 * Workflows' own Workflow entity has (no WorkflowWasCreated event exists
 * either, only WorkflowWasTriggered).
 */
final class CreateRewardAction
{
    public function __construct(
        private readonly RewardRepositoryInterface $rewards,
    ) {
    }

    public function execute(
        int $tenantId,
        string $name,
        string $rewardType,
        int $pointsRequired,
        ?int $discountAmount = null,
        ?string $description = null,
    ): RewardData {
        $type = RewardType::from($rewardType);

        if ($type === RewardType::DiscountCoupon && $discountAmount === null) {
            throw new InvalidArgumentException('discount_amount is required for a discount_coupon Reward.');
        }

        $reward = Reward::create(
            tenantId: $tenantId,
            name: $name,
            description: $description,
            rewardType: $type,
            pointsRequired: new Points($pointsRequired),
            discountAmount: $discountAmount,
        );

        $reward = $this->rewards->save($reward);

        return RewardData::fromEntity($reward);
    }
}
