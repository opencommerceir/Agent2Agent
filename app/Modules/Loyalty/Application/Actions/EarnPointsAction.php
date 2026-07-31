<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\PointTransactionData;
use App\Modules\Loyalty\Domain\Entities\PointTransaction;
use App\Modules\Loyalty\Domain\Events\PointsWereEarned;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\ExpirationDate;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

/**
 * One Action = one business operation: credit points to a Customer's
 * LoyaltyAccount and record the ledger entry. Find-or-create — unlike
 * GetLoyaltyAccountAction's strict lookup, earning points for a
 * first-time purchaser who has no LoyaltyAccount yet is an entirely
 * normal case (`loyalty.points.earn`'s input is just `customer_id`, with
 * no requirement that `loyalty.account.create` was called first), so
 * this Action composes CreateLoyaltyAccountAction (Actions composing
 * Actions is normal here, HANDOFF §3 item 3) rather than requiring the
 * caller to provision one explicitly first.
 *
 * $transactionType defaults to `earn` (the only value the
 * `loyalty.points.earn` capability itself ever passes) but also accepts
 * `bonus` for a manual grant — both credit the LoyaltyAccount
 * identically (LoyaltyAccount::earn()'s own docblock); only the ledger
 * entry's transaction_type differs. OrderPlacedListener is this Action's
 * other caller, always with the default `earn`.
 */
final class EarnPointsAction
{
    public function __construct(
        private readonly LoyaltyAccountRepositoryInterface $accounts,
        private readonly PointTransactionRepositoryInterface $transactions,
        private readonly CreateLoyaltyAccountAction $createAccount,
    ) {
    }

    /**
     * @return array{transaction: array<string, mixed>, new_balance: int}
     */
    public function execute(
        int $tenantId,
        int $customerId,
        int $points,
        ?string $description = null,
        ?int $referenceId = null,
        string $transactionType = 'earn',
    ): array {
        $account = $this->accounts->findByCustomer($customerId, $tenantId);

        if (! $account) {
            $created = $this->createAccount->execute($tenantId, $customerId);
            $account = $this->accounts->findById($created->id, $tenantId);
        }

        $type = TransactionType::from($transactionType);

        $account->earn(new Points($points));
        $account = $this->accounts->save($account);

        $expiresAt = ExpirationDate::from(new DateTimeImmutable())->value();

        $transaction = PointTransaction::record(
            tenantId: $tenantId,
            loyaltyAccountId: $account->id(),
            points: $points,
            transactionType: $type,
            description: $description,
            referenceId: $referenceId,
            expiresAt: $expiresAt,
        );
        $transaction = $this->transactions->save($transaction);

        Event::dispatch(new PointsWereEarned($account, $points, $referenceId));

        return [
            'transaction' => PointTransactionData::fromEntity($transaction)->toArray(),
            'new_balance' => $account->currentBalance()->value(),
        ];
    }
}
