<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\PointTransactionData;
use App\Modules\Loyalty\Domain\Entities\PointTransaction;
use App\Modules\Loyalty\Domain\Events\PointsWereExpired;
use App\Modules\Loyalty\Domain\Exceptions\LoyaltyAccountNotFoundException;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

/**
 * Not wired to MCP this stage (no `loyalty.points.expire` capability was
 * requested among the 8 — same "built, tested, not yet exposed to
 * Agents" gap Finance's UpdateTaxRateAction/Workflows'
 * UpdateWorkflowAction already carry, HANDOFF §6). Runs execution
 * against one LoyaltyAccount at a time — the natural unit a future
 * scheduled job (HANDOFF §8.23: no cron/scheduler mechanism exists
 * anywhere in this codebase yet) would iterate accounts and call this
 * once per account, the same way `CartAbandonedListener` is blocked on
 * scheduling rather than on this Action.
 *
 * Simplified FIFO: processes findExpirable()'s oldest-first results,
 * expiring each qualifying batch's full point amount but capped by
 * whatever balance genuinely remains as earlier batches (and any
 * intervening Redemption, which reduces current_balance directly rather
 * than being tied to a specific earn transaction) consume it. This does
 * NOT track partial consumption of one specific batch by one specific
 * Redemption — a real per-lot ledger — so a Customer who redeems most of
 * an old batch and keeps a recent one will still see the *older* batch's
 * remaining balance expire first, which is the economically conservative
 * (tenant-favoring) outcome, not the customer-favoring one a true FIFO
 * ledger would give. Flagged as a simplification, not a bug: a precise
 * per-lot implementation is real future work, not silently broken
 * behavior today (every point this method expires was genuinely
 * un-redeemed and genuinely past its expiry date).
 */
final class ExpirePointsAction
{
    public function __construct(
        private readonly LoyaltyAccountRepositoryInterface $accounts,
        private readonly PointTransactionRepositoryInterface $transactions,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $tenantId, int $loyaltyAccountId, ?DateTimeImmutable $asOf = null): array
    {
        $account = $this->accounts->findById($loyaltyAccountId, $tenantId);

        if (! $account) {
            throw new LoyaltyAccountNotFoundException("LoyaltyAccount [{$loyaltyAccountId}] does not exist.");
        }

        $asOf ??= new DateTimeImmutable();
        $expirable = $this->transactions->findExpirable($loyaltyAccountId, $tenantId, $asOf);

        $created = [];
        $remainingBalance = $account->currentBalance()->value();

        foreach ($expirable as $sourceTransaction) {
            if ($remainingBalance <= 0) {
                break;
            }

            $expiring = min($sourceTransaction->points(), $remainingBalance);

            if ($expiring <= 0) {
                continue;
            }

            $remainingBalance -= $expiring;

            $account->expire(new Points($expiring));

            $expireTransaction = PointTransaction::record(
                tenantId: $tenantId,
                loyaltyAccountId: $loyaltyAccountId,
                points: -$expiring,
                transactionType: TransactionType::Expire,
                description: "Expired {$expiring} point(s) earned on {$sourceTransaction->createdAt()->format('Y-m-d')}",
                referenceId: $sourceTransaction->id(),
            );
            $expireTransaction = $this->transactions->save($expireTransaction);

            $created[] = PointTransactionData::fromEntity($expireTransaction)->toArray();
        }

        if ($created !== []) {
            $account = $this->accounts->save($account);

            $totalExpired = array_sum(array_map(fn (array $t) => -$t['points'], $created));
            Event::dispatch(new PointsWereExpired($account, $totalExpired));
        }

        return $created;
    }
}
