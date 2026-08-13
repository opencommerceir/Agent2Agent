<?php

namespace App\Domains\Nexus\Approval\Domain\Repositories;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalPolicy;

interface ApprovalPolicyRepositoryInterface
{
    public function findByBusinessId(int $businessId): ?ApprovalPolicy;

    public function save(ApprovalPolicy $policy): ApprovalPolicy;
}
