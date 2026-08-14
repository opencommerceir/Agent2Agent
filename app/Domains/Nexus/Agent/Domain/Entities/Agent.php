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

    /**
     * Whether this Agent reacts on its own to an incoming proposal/counter
     * (AutoRespondToNegotiationListener) instead of waiting for an external
     * caller. Read from the existing free-form `$strategies` bag (same
     * escape-hatch convention `authorityLimits`/`AutomationRule.config`
     * already use) rather than a dedicated column.
     *
     * Defaults to **disabled**, opt-in — deliberately the opposite default
     * of `authorityLimits`' own "permissive until configured" precedent.
     * Confirmed empirically, not just by caution in the abstract: defaulting
     * this to enabled broke 32 existing tests across 18 files spanning four
     * unrelated domains (Contract, Growth, Marketplace, plus Negotiation's
     * own), every one of them a test that manually drives a Negotiation
     * through propose/counter/accept as fixture setup for something else
     * entirely — proof that silently auto-resolving every Negotiation the
     * moment it's proposed is too large and surprising a blast radius for
     * an opt-out default, on top of it being real credit spend and real
     * deal commitment happening without the Business's explicit consent.
     */
    public function autoRespondEnabled(): bool
    {
        return (bool) ($this->strategies['auto_respond'] ?? false);
    }

    /**
     * How far (as a percent of the catalog item's list price) this Agent
     * will move from the list price before it accepts a deal on its own.
     */
    public function negotiationTolerancePercent(): float
    {
        return (float) ($this->strategies['tolerance_percent'] ?? 15);
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
