<?php

namespace App\Domains\Nexus\Approval\Domain\ValueObjects;

use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;

/**
 * One rung of an ApprovalPolicy's chain — "a deal at or above minAmount
 * needs this role's sign-off." Plain immutable VO, framework-free.
 */
final class ApprovalLevel
{
    public function __construct(
        public readonly TeamMemberRole $role,
        public readonly int $minAmount,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(TeamMemberRole::from($data['role']), (int) $data['minAmount']);
    }

    public function toArray(): array
    {
        return ['role' => $this->role->value, 'minAmount' => $this->minAmount];
    }
}
