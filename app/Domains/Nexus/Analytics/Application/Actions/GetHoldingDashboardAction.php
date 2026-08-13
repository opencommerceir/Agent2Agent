<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\ValueObjects\SubsidiaryStatus;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use InvalidArgumentException;

/**
 * Phase 7's "centralized reporting" — same pure read-model role
 * GetRevenueDashboardAction/GetBusinessDashboardAction already play,
 * spanning Holding/Business/Credit/Negotiation for one consolidated view.
 * No mutation happens here (Inter-Module Communication's own read-side
 * exception, established since Phase 1/M6).
 */
final class GetHoldingDashboardAction
{
    /**
     * @var list<NegotiationStatus>
     */
    private const ACTIVE_STATUSES = [NegotiationStatus::Proposed, NegotiationStatus::Countered, NegotiationStatus::PendingApproval];

    public function __construct(
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
        private readonly BusinessRepositoryInterface $businesses,
        private readonly CreditBalanceRepositoryInterface $creditBalances,
        private readonly NegotiationRepositoryInterface $negotiations,
    ) {
    }

    /**
     * @return array{
     *     holdingId: int,
     *     nameEn: string,
     *     rows: list<array{businessId: int, nameEn: string, isParent: bool, creditBalance: ?int, activeNegotiations: int}>,
     *     totalCreditBalance: int,
     * }
     */
    public function execute(int $holdingId): array
    {
        $holding = $this->holdings->findById($holdingId);

        if (! $holding) {
            throw new InvalidArgumentException("Holding [{$holdingId}] does not exist.");
        }

        $businessIds = [$holding->parentBusinessId()];
        foreach ($this->subsidiaries->findByHoldingId($holdingId) as $subsidiary) {
            if ($subsidiary->status() === SubsidiaryStatus::Active) {
                $businessIds[] = $subsidiary->businessId();
            }
        }

        $rows = [];
        $totalCreditBalance = 0;

        foreach ($businessIds as $businessId) {
            $business = $this->businesses->findById($businessId);
            $balance = $this->creditBalances->findByBusinessId($businessId)?->balance();
            $activeNegotiations = array_filter(
                $this->negotiations->findVisibleTo($businessId),
                fn ($negotiation) => in_array($negotiation->status(), self::ACTIVE_STATUSES, true),
            );

            $rows[] = [
                'businessId' => $businessId,
                'nameEn' => $business?->nameEn() ?? "#{$businessId}",
                'isParent' => $businessId === $holding->parentBusinessId(),
                'creditBalance' => $balance,
                'activeNegotiations' => count($activeNegotiations),
            ];

            $totalCreditBalance += $balance ?? 0;
        }

        return [
            'holdingId' => $holdingId,
            'nameEn' => $holding->nameEn(),
            'rows' => $rows,
            'totalCreditBalance' => $totalCreditBalance,
        ];
    }
}
