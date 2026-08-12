<?php

namespace App\Domains\Nexus\Llm\Domain\Entities;

use DateTimeImmutable;

/**
 * One immutable ledger row per LLM call attempt — a fact ("this provider
 * was called, for this feature, and cost this much"), not a workflow with
 * states, structurally the same shape as
 * App\Domains\Nexus\Credit\Domain\Entities\CreditTransaction (that class's
 * own docblock explains why: it doubles as the audit trail CLAUDE.md
 * requires, no separate generic AuditLog exists or is needed). Every
 * attempt LLMRouter makes is recorded here, success or failure, primary or
 * fallback — including admin "test connection" pings
 * (businessId/agentId both null, feature = 'admin_test_connection'), which
 * a paid-provider ping genuinely costs real money for and must not be
 * silently missing from the audit trail.
 *
 * `chargedCostUsd` already has MarginSettingsService::llmCostMarkupPercent()
 * applied by the caller before `record()` is invoked — this entity has no
 * opinion on margin policy, only on recording the two numbers and their
 * difference.
 */
final class LLMUsageLog
{
    public function __construct(
        private readonly ?int $id,
        private readonly ?int $businessId,
        private readonly ?int $agentId,
        private readonly string $feature,
        private readonly string $provider,
        private readonly string $model,
        private readonly int $promptTokens,
        private readonly int $completionTokens,
        private readonly int $totalTokens,
        private readonly float $realCostUsd,
        private readonly float $chargedCostUsd,
        private readonly float $marginUsd,
        private readonly int $latencyMs,
        private readonly bool $fromFallback,
        private readonly bool $success,
        private readonly ?string $errorMessage,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        ?int $businessId,
        ?int $agentId,
        string $feature,
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
        float $realCostUsd,
        float $chargedCostUsd,
        int $latencyMs,
        bool $fromFallback,
        bool $success,
        ?string $errorMessage = null,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            agentId: $agentId,
            feature: $feature,
            provider: $provider,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $promptTokens + $completionTokens,
            realCostUsd: $realCostUsd,
            chargedCostUsd: $chargedCostUsd,
            marginUsd: $chargedCostUsd - $realCostUsd,
            latencyMs: $latencyMs,
            fromFallback: $fromFallback,
            success: $success,
            errorMessage: $errorMessage,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function agentId(): ?int
    {
        return $this->agentId;
    }

    public function feature(): string
    {
        return $this->feature;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function promptTokens(): int
    {
        return $this->promptTokens;
    }

    public function completionTokens(): int
    {
        return $this->completionTokens;
    }

    public function totalTokens(): int
    {
        return $this->totalTokens;
    }

    public function realCostUsd(): float
    {
        return $this->realCostUsd;
    }

    public function chargedCostUsd(): float
    {
        return $this->chargedCostUsd;
    }

    public function marginUsd(): float
    {
        return $this->marginUsd;
    }

    public function latencyMs(): int
    {
        return $this->latencyMs;
    }

    public function fromFallback(): bool
    {
        return $this->fromFallback;
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
