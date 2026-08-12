<?php

namespace App\Domains\Nexus\Growth\Domain\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralSignup;

interface ReferralSignupRepositoryInterface
{
    public function findByRefereeId(int $refereeBusinessId): ?ReferralSignup;

    /**
     * @return list<ReferralSignup>
     */
    public function findByReferrerId(int $referrerBusinessId): array;

    public function countByReferrerId(int $referrerBusinessId): int;

    public function save(ReferralSignup $signup): ReferralSignup;
}
