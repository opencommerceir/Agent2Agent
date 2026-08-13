<?php

namespace App\Domains\Nexus\Developer\Domain\Entities;

use DateTimeImmutable;

/**
 * A publishable, installable Agent personality/tone/strategies preset —
 * the honest scope for "Agent Developer Platform: مارکت‌پلیس برای
 * ایجنت‌های Third-party" in this codebase (Phase 9/M7). Every Nexus Agent
 * (Phase 1/M3) already carries exactly these three fields
 * (personality/tone/strategies, App\Domains\Nexus\Agent\Domain\Entities\Agent);
 * a marketplace of *literal* third-party Agent processes/binaries would
 * need a code-execution sandbox this codebase has no infrastructure for
 * (no container orchestration, no untrusted-code execution anywhere) —
 * a preset a Business installs onto its own already-running, already-
 * credentialed Agent is the real, working version of the same idea.
 * `publisherBusinessId` doubles as the "developer account": there is no
 * separate developer-identity concept anywhere in this codebase, and a
 * Business is already a real, billable, revenue-earning entity (Credit
 * balance, MCP identity) — adding a second identity type purely to
 * publish templates would duplicate Business for no behavioral gain.
 */
final class AgentStrategyTemplate
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $publisherBusinessId,
        private readonly string $nameFa,
        private readonly string $nameEn,
        private readonly string $descriptionFa,
        private readonly string $descriptionEn,
        private readonly ?string $personality,
        private readonly ?string $tone,
        private readonly array $strategies,
        private readonly int $priceCredits,
        private int $installCount,
        private ?DateTimeImmutable $revokedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function publish(
        int $publisherBusinessId,
        string $nameFa,
        string $nameEn,
        string $descriptionFa,
        string $descriptionEn,
        ?string $personality,
        ?string $tone,
        array $strategies,
        int $priceCredits,
    ): self {
        return new self(
            id: null,
            publisherBusinessId: $publisherBusinessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            descriptionFa: $descriptionFa,
            descriptionEn: $descriptionEn,
            personality: $personality,
            tone: $tone,
            strategies: $strategies,
            priceCredits: $priceCredits,
            installCount: 0,
            revokedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function recordInstall(): void
    {
        $this->installCount++;
    }

    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function publisherBusinessId(): int
    {
        return $this->publisherBusinessId;
    }

    public function nameFa(): string
    {
        return $this->nameFa;
    }

    public function nameEn(): string
    {
        return $this->nameEn;
    }

    public function descriptionFa(): string
    {
        return $this->descriptionFa;
    }

    public function descriptionEn(): string
    {
        return $this->descriptionEn;
    }

    public function personality(): ?string
    {
        return $this->personality;
    }

    public function tone(): ?string
    {
        return $this->tone;
    }

    public function strategies(): array
    {
        return $this->strategies;
    }

    public function priceCredits(): int
    {
        return $this->priceCredits;
    }

    public function installCount(): int
    {
        return $this->installCount;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
