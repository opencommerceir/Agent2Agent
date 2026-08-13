<?php

namespace App\Domains\Nexus\Approval\Application\Actions;

use App\Domains\Nexus\Approval\Application\DTOs\ApprovalPolicyData;
use App\Domains\Nexus\Approval\Domain\Entities\ApprovalPolicy;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalPolicyRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use InvalidArgumentException;

/**
 * Owner-only, same authorization shape every other Business-settings Action
 * in this phase uses (SetCreditPoolingEnabledAction). Levels are always
 * fully replaced, not merged — a Business editing its chain edits the
 * whole thing, mirroring MarginSettingsService's own "just overwrite the
 * stored value" simplicity rather than per-level patch semantics nothing
 * here needs.
 */
final class SetApprovalPolicyAction
{
    public function __construct(
        private readonly ApprovalPolicyRepositoryInterface $policies,
    ) {
    }

    /**
     * @param  list<array{role: string, minAmount: int}>  $levels
     */
    public function execute(int $businessId, int $callingOwnerId, array $levels): ApprovalPolicyData
    {
        $caller = BusinessOwner::query()->find($callingOwnerId);

        if (! $caller || $caller->business_id !== $businessId || $caller->role !== TeamMemberRole::Owner) {
            throw new InvalidArgumentException('Only an Owner may set the approval policy.');
        }

        $approvalLevels = array_map(fn (array $level) => ApprovalLevel::fromArray($level), $levels);

        $policy = $this->policies->findByBusinessId($businessId);

        if ($policy) {
            $policy->redefine($approvalLevels);
        } else {
            $policy = ApprovalPolicy::define($businessId, $approvalLevels);
        }

        $policy = $this->policies->save($policy);

        return ApprovalPolicyData::fromEntity($policy);
    }
}
