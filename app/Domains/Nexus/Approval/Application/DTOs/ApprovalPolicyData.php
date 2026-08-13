<?php

namespace App\Domains\Nexus\Approval\Application\DTOs;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalPolicy;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;

final class ApprovalPolicyData
{
    /**
     * @param  list<array{role: string, minAmount: int}>  $levels
     */
    public function __construct(
        public readonly int $businessId,
        public readonly array $levels,
    ) {
    }

    public static function fromEntity(ApprovalPolicy $policy): self
    {
        return new self(
            businessId: $policy->businessId(),
            levels: array_map(fn (ApprovalLevel $level) => $level->toArray(), $policy->levels()),
        );
    }

    public function toArray(): array
    {
        return [
            'businessId' => $this->businessId,
            'levels' => $this->levels,
        ];
    }
}
