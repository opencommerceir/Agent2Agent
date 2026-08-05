<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use DateTimeImmutable;
use LogicException;

/**
 * A learned, tenant-scoped shorthand for "goals that mention these
 * keywords, for this Agent persona, tend to be satisfied by these
 * capabilities" — the unit `PatternExtractorInterface` produces from a
 * successful `ExecutionResult` (Phase 6, Stage 4, §7.29) and
 * `LearningServiceInterface::suggestPlan()` reads back to skip planning
 * entirely for a goal it recognizes.
 *
 * One row per (tenantId, goalPattern, agentType) — repeat occurrences of
 * the same pattern call `recordOutcome()` on the *same* row (usage_count/
 * success_rate accumulate) rather than inserting a duplicate; see
 * `EloquentExecutionPatternRepository::save()`'s own upsert-by-composite-key
 * docblock. `$id` is null until the first `save()`.
 */
final class ExecutionPattern
{
    private int $usageCount;

    private float $successRate;

    private DateTimeImmutable $lastUsedAt;

    /**
     * @var list<string>
     */
    private array $successfulCapabilities;

    /**
     * @var list<string>
     */
    private array $failedCapabilities;

    private function __construct(
        private ?int $id,
        public readonly int $tenantId,
        public readonly string $goalPattern,
        public readonly AgentType $agentType,
        array $successfulCapabilities,
        array $failedCapabilities,
        int $usageCount,
        float $successRate,
        DateTimeImmutable $lastUsedAt,
    ) {
        $this->successfulCapabilities = $successfulCapabilities;
        $this->failedCapabilities = $failedCapabilities;
        $this->usageCount = $usageCount;
        $this->successRate = $successRate;
        $this->lastUsedAt = $lastUsedAt;
    }

    /**
     * A brand-new pattern from its first successful occurrence — usage 1,
     * success rate 100%, no failed capabilities recorded yet.
     *
     * @param list<string> $successfulCapabilities
     */
    public static function create(
        int $tenantId,
        string $goalPattern,
        AgentType $agentType,
        array $successfulCapabilities,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            goalPattern: $goalPattern,
            agentType: $agentType,
            successfulCapabilities: $successfulCapabilities,
            failedCapabilities: [],
            usageCount: 1,
            successRate: 1.0,
            lastUsedAt: $now,
        );
    }

    /**
     * Rebuilds an already-persisted pattern directly into its current
     * state — the same "toEntity() reconstructs directly" split every
     * other Eloquent Repository's own `toEntity()` in this codebase
     * already relies on (see `ExecutionStep::reconstruct()`).
     *
     * @param list<string> $successfulCapabilities
     * @param list<string> $failedCapabilities
     */
    public static function reconstruct(
        int $id,
        int $tenantId,
        string $goalPattern,
        AgentType $agentType,
        array $successfulCapabilities,
        array $failedCapabilities,
        int $usageCount,
        float $successRate,
        DateTimeImmutable $lastUsedAt,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            goalPattern: $goalPattern,
            agentType: $agentType,
            successfulCapabilities: $successfulCapabilities,
            failedCapabilities: $failedCapabilities,
            usageCount: $usageCount,
            successRate: $successRate,
            lastUsedAt: $lastUsedAt,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * Assigned once, by the Repository, immediately after the first
     * `save()` of a brand-new pattern — the same "id becomes known only
     * after persistence" shape `ExecutionMemoryRepositoryInterface::save()`
     * already has (it returns the new id in its own result array rather
     * than mutating the given Entity). Throws if called on a pattern that
     * already has one; a persisted pattern's own identity never changes.
     */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException("ExecutionPattern already has id [{$this->id}]; assignId() is one-time only.");
        }

        $this->id = $id;
    }

    /**
     * Case-insensitive: true when at least one `|`-separated keyword this
     * pattern was extracted from also appears in the given (new) goal
     * text. A `'general'` pattern (no recognized keyword found at
     * extraction time, see `PatternExtractor::extractGoalPattern()`) never
     * matches anything here — matching it against every future
     * unrecognized goal would be a false-positive machine, not a learned
     * signal.
     */
    public function matches(string $goal): bool
    {
        if ($this->goalPattern === 'general') {
            return false;
        }

        $text = mb_strtolower($goal);

        foreach (explode('|', $this->goalPattern) as $keyword) {
            if ($keyword !== '' && str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One repeat occurrence of this pattern: bumps usage, folds the new
     * outcome into a running success-rate average, unions in any
     * newly-seen successful/failed capability names, and stamps "now" as
     * the most recent use. Deliberately the *only* mutator for this
     * entity's numeric state — `incrementUsage()`/`updateSuccessRate()`
     * as two independently-callable methods (the original request's own
     * shape) would let a caller update one without the other, leaving
     * `usageCount` and `successRate` inconsistent with each other.
     *
     * @param list<string> $capabilitiesUsed
     */
    public function recordOutcome(bool $successful, array $capabilitiesUsed, DateTimeImmutable $now): void
    {
        $this->successRate = (($this->successRate * $this->usageCount) + ($successful ? 1 : 0)) / ($this->usageCount + 1);
        $this->usageCount++;
        $this->lastUsedAt = $now;

        if ($successful) {
            $this->successfulCapabilities = array_values(array_unique([...$this->successfulCapabilities, ...$capabilitiesUsed]));
        } else {
            $this->failedCapabilities = array_values(array_unique([...$this->failedCapabilities, ...$capabilitiesUsed]));
        }
    }

    /**
     * @return list<string>
     */
    public function successfulCapabilities(): array
    {
        return $this->successfulCapabilities;
    }

    /**
     * @return list<string>
     */
    public function failedCapabilities(): array
    {
        return $this->failedCapabilities;
    }

    public function usageCount(): int
    {
        return $this->usageCount;
    }

    public function successRate(): float
    {
        return $this->successRate;
    }

    public function lastUsedAt(): DateTimeImmutable
    {
        return $this->lastUsedAt;
    }
}
