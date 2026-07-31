<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\RedemptionData;
use App\Modules\Loyalty\Domain\Entities\PointTransaction;
use App\Modules\Loyalty\Domain\Entities\Redemption;
use App\Modules\Loyalty\Domain\Events\PointsWereRedeemed;
use App\Modules\Loyalty\Domain\Events\RewardWasRedeemed;
use App\Modules\Loyalty\Domain\Exceptions\InvalidPointsException;
use App\Modules\Loyalty\Domain\Exceptions\LoyaltyAccountNotFoundException;
use App\Modules\Loyalty\Domain\Exceptions\RewardNotFoundException;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\RewardRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * One Action = one business operation: spend points on a Reward. The
 * `points` input is validated against the named Reward's own
 * `points_required` (InvalidPointsException on a mismatch) rather than
 * being trusted as-is — this makes the redundant-looking pair of
 * `points`/`reward_id` inputs meaningful: the caller states the price it
 * expects to pay, and a stale/incorrect expectation fails loudly instead
 * of silently charging whatever the Reward happens to cost today.
 * `InsufficientPointsException` (from LoyaltyAccount::redeem()) is the
 * separate, later check for "the price is right but the balance isn't
 * there".
 *
 * Whole operation is one DB transaction: LoyaltyAccount, the `redeem`
 * PointTransaction, and the Redemption record all change together or not
 * at all — mirrors PlaceOrderAction's own transaction boundary.
 */
final class RedeemPointsAction
{
    public function __construct(
        private readonly LoyaltyAccountRepositoryInterface $accounts,
        private readonly RewardRepositoryInterface $rewards,
        private readonly PointTransactionRepositoryInterface $transactions,
    ) {
    }

    /**
     * @return array{redemption: array<string, mixed>, new_balance: int}
     */
    public function execute(int $tenantId, int $customerId, int $points, int $rewardId): array
    {
        return DB::transaction(function () use ($tenantId, $customerId, $points, $rewardId) {
            $account = $this->accounts->findByCustomer($customerId, $tenantId);

            if (! $account) {
                throw new LoyaltyAccountNotFoundException("Customer [{$customerId}] has no LoyaltyAccount.");
            }

            $reward = $this->rewards->findById($rewardId, $tenantId);

            if (! $reward || ! $reward->isActive()) {
                throw new RewardNotFoundException("Reward [{$rewardId}] does not exist.");
            }

            if ($points !== $reward->pointsRequired()->value()) {
                throw new InvalidPointsException(
                    "Reward [{$rewardId}] requires [{$reward->pointsRequired()}] points, got [{$points}]."
                );
            }

            $pointsToRedeem = new Points($points);

            $account->redeem($pointsToRedeem);
            $account = $this->accounts->save($account);

            $transaction = PointTransaction::record(
                tenantId: $tenantId,
                loyaltyAccountId: $account->id(),
                points: -$points,
                transactionType: TransactionType::Redeem,
                description: "Redeemed for reward [{$reward->name()}]",
                referenceId: $rewardId,
            );
            $this->transactions->save($transaction);

            $redemption = Redemption::complete($tenantId, $account->id(), $rewardId, $pointsToRedeem);
            $redemption = $this->accounts->saveRedemption($redemption);

            Event::dispatch(new PointsWereRedeemed($account, $points, $rewardId));
            Event::dispatch(new RewardWasRedeemed($redemption));

            return [
                'redemption' => RedemptionData::fromEntity($redemption)->toArray(),
                'new_balance' => $account->currentBalance()->value(),
            ];
        });
    }
}
