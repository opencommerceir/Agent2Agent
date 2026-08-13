<?php

namespace App\Domains\Nexus\Approval\Application\DTOs;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalRequest;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;

final class ApprovalRequestData
{
    /**
     * @param  list<array{role: string, minAmount: int}>  $requiredLevels
     */
    public function __construct(
        public readonly int $id,
        public readonly int $negotiationId,
        public readonly int $businessId,
        public readonly array $requiredLevels,
        public readonly int $currentLevelIndex,
        public readonly string $currentRequiredRole,
        public readonly string $status,
    ) {
    }

    public static function fromEntity(ApprovalRequest $request): self
    {
        return new self(
            id: $request->id(),
            negotiationId: $request->negotiationId(),
            businessId: $request->businessId(),
            requiredLevels: array_map(fn (ApprovalLevel $level) => $level->toArray(), $request->requiredLevels()),
            currentLevelIndex: $request->currentLevelIndex(),
            currentRequiredRole: $request->status()->value === 'pending' ? $request->currentRequiredRole()->value : '',
            status: $request->status()->value,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'negotiationId' => $this->negotiationId,
            'businessId' => $this->businessId,
            'requiredLevels' => $this->requiredLevels,
            'currentLevelIndex' => $this->currentLevelIndex,
            'currentRequiredRole' => $this->currentRequiredRole,
            'status' => $this->status,
        ];
    }
}
