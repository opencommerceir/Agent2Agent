<?php

namespace App\Domains\Nexus\Agent\Domain\Entities;

use DateTimeImmutable;

/**
 * A Business's own negotiating Agent — personality, tone, authority
 * limits, strategies. Distinct from Core's App\Core\Domain\Entities\Agent
 * (a bearer-token identity with zero behavioral fields) and from
 * AgentOrchestrator's AgentProfile (a static, config-driven, per-type
 * keyword-routing table) — neither has, or should gain, these fields.
 * $coreAgentId links to Core's own Agent (see CreateAgentForBusinessAction)
 * so this Agent has genuine MCP-Gateway credentials via existing
 * plumbing, without duplicating auth. Framework-free (Domain Layer Rules).
 */
final class Agent
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private ?int $coreAgentId,
        private string $nameFa,
        private string $nameEn,
        private ?string $personality,
        private ?string $tone,
        private ?array $authorityLimits,
        private ?array $strategies,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $businessId,
        string $nameFa,
        string $nameEn,
        ?int $coreAgentId = null,
        ?string $personality = null,
        ?string $tone = null,
        ?array $authorityLimits = null,
        ?array $strategies = null,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            coreAgentId: $coreAgentId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            personality: $personality,
            tone: $tone,
            authorityLimits: $authorityLimits,
            strategies: $strategies,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function attachCoreAgent(int $coreAgentId): void
    {
        $this->coreAgentId = $coreAgentId;
    }

    public function updatePersonality(string $personality, string $tone): void
    {
        $this->personality = $personality;
        $this->tone = $tone;
    }

    public function setAuthorityLimits(array $authorityLimits): void
    {
        $this->authorityLimits = $authorityLimits;
    }

    public function setStrategies(array $strategies): void
    {
        $this->strategies = $strategies;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function coreAgentId(): ?int
    {
        return $this->coreAgentId;
    }

    public function nameFa(): string
    {
        return $this->nameFa;
    }

    public function nameEn(): string
    {
        return $this->nameEn;
    }

    public function personality(): ?string
    {
        return $this->personality;
    }

    public function tone(): ?string
    {
        return $this->tone;
    }

    public function authorityLimits(): ?array
    {
        return $this->authorityLimits;
    }

    public function strategies(): ?array
    {
        return $this->strategies;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
