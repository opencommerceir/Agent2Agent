<?php

namespace App\Domains\Nexus\Approval\Application\Actions;

use App\Domains\Nexus\Approval\Application\DTOs\ApprovalPolicyData;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalPolicyRepositoryInterface;

final class GetApprovalPolicyAction
{
    public function __construct(
        private readonly ApprovalPolicyRepositoryInterface $policies,
    ) {
    }

    public function execute(int $businessId): ?ApprovalPolicyData
    {
        $policy = $this->policies->findByBusinessId($businessId);

        return $policy ? ApprovalPolicyData::fromEntity($policy) : null;
    }
}
