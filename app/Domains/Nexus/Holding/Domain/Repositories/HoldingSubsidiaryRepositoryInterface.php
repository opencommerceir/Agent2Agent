<?php

namespace App\Domains\Nexus\Holding\Domain\Repositories;

use App\Domains\Nexus\Holding\Domain\Entities\HoldingSubsidiary;

interface HoldingSubsidiaryRepositoryInterface
{
    public function findById(int $id): ?HoldingSubsidiary;

    /**
     * @return list<HoldingSubsidiary>
     */
    public function findByHoldingId(int $holdingId): array;

    public function findByHoldingAndBusiness(int $holdingId, int $businessId): ?HoldingSubsidiary;

    /**
     * The one non-terminal (Invited or Active) row for this Business across
     * ALL Holdings, if any — the single check that enforces "a Business
     * belongs to at most one Holding at a time" (a Removed row never blocks
     * a fresh invitation elsewhere).
     */
    public function findActiveOrInvitedByBusinessId(int $businessId): ?HoldingSubsidiary;

    /**
     * @return list<HoldingSubsidiary>
     */
    public function findInvitationsForBusiness(int $businessId): array;

    public function save(HoldingSubsidiary $subsidiary): HoldingSubsidiary;
}
