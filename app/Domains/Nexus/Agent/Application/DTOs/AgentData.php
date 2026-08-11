<?php

namespace App\Domains\Nexus\Agent\Application\DTOs;

use App\Domains\Nexus\Agent\Domain\Entities\Agent;

/**
 * Structured data transfer for the Nexus Agent across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class AgentData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $businessId,
        public readonly ?int $coreAgentId,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly ?string $personality,
        public readonly ?string $tone,
        public readonly ?array $authorityLimits,
        public readonly ?array $strategies,
        public readonly ?string $plainCoreAgentToken = null,
    ) {
    }

    public static function fromEntity(Agent $agent, ?string $plainCoreAgentToken = null): self
    {
        return new self(
            id: $agent->id(),
            businessId: $agent->businessId(),
            coreAgentId: $agent->coreAgentId(),
            nameFa: $agent->nameFa(),
            nameEn: $agent->nameEn(),
            personality: $agent->personality(),
            tone: $agent->tone(),
            authorityLimits: $agent->authorityLimits(),
            strategies: $agent->strategies(),
            plainCoreAgentToken: $plainCoreAgentToken,
        );
    }

    /**
     * @return array{id: ?int, businessId: int, coreAgentId: ?int, nameFa: string, nameEn: string, personality: ?string, tone: ?string, authorityLimits: ?array, strategies: ?array}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessId' => $this->businessId,
            'coreAgentId' => $this->coreAgentId,
            'nameFa' => $this->nameFa,
            'nameEn' => $this->nameEn,
            'personality' => $this->personality,
            'tone' => $this->tone,
            'authorityLimits' => $this->authorityLimits,
            'strategies' => $this->strategies,
        ];
    }
}
