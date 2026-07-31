<?php

namespace App\Modules\Loyalty\Domain\Repositories;

use App\Modules\Loyalty\Domain\Entities\PointTransaction;
use DateTimeImmutable;

interface PointTransactionRepositoryInterface
{
    /**
     * @return list<PointTransaction>
     */
    public function listByAccount(int $loyaltyAccountId, int $tenantId, int $limit): array;

    /**
     * `earn`/`bonus` transactions for one LoyaltyAccount whose
     * `expires_at` is due (<= $asOf) and that have not already been
     * expired. "Already expired" is recognized without a dedicated
     * boolean/flag column — a `PointTransaction` is immutable
     * (HANDOFF gotcha #10 territory: no mutable "processed" marker is
     * ever added to a ledger row) — by checking whether an `expire`
     * transaction already exists whose `reference_id` points back at
     * this row's own id (ExpirePointsAction is the only writer of that
     * link). Ordered oldest-first so ExpirePointsAction can process a
     * simplified FIFO: expire each qualifying batch fully, capped by
     * whatever balance genuinely remains once earlier redemptions are
     * accounted for (see ExpirePointsAction's own docblock for why this
     * is a deliberate simplification, not a full per-lot ledger).
     *
     * @return list<PointTransaction>
     */
    public function findExpirable(int $loyaltyAccountId, int $tenantId, DateTimeImmutable $asOf): array;

    public function save(PointTransaction $transaction): PointTransaction;
}
