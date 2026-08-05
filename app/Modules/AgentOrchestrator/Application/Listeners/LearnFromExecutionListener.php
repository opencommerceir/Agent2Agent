<?php

namespace App\Modules\AgentOrchestrator\Application\Listeners;

use App\Modules\AgentOrchestrator\Domain\Events\GoalCompleted;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PatternExtractorInterface;
use DateTimeImmutable;

/**
 * Owns Pattern Extraction/Learning's own write side (Phase 6, Stage 4,
 * §7.29) — reacts to the *existing* `GoalCompleted` event (dispatched by
 * `ExecuteGoalAction` since Stage 1, §7.26, previously unlistened-to; see
 * that event's own docblock) rather than a new dependency injected
 * directly into `ExecuteGoalAction` itself, the same "a Listener reacts to
 * a Domain Event, the dispatching class doesn't do the reacting inline"
 * convention `LogExecutionStepListener`/`InventoryLowListener` already
 * establish (HANDOFF §7.9/§3 pattern #11). Keeps this stage's own change
 * to `ExecuteGoalAction` itself limited to the one, unrelated concern that
 * genuinely can't be event-driven — consulting a learned suggestion
 * *before* planning (see that Action's own docblock).
 *
 * Fires on every finished Goal, success or failure — not just successful
 * ones. A successful run either creates a brand-new pattern (via
 * `PatternExtractorInterface::extract()`) or reinforces an existing one's
 * `successRate` upward; a *failed* run against an already-learned pattern
 * still degrades that pattern's `successRate` down via the same
 * `recordOutcome()` call. Without this, a pattern's success rate could
 * only ever rise — a real, deliberate correction from the original
 * request's own pseudocode, which only ever called pattern extraction on
 * a successful `ExecutionMemory` and never revisited a pattern on failure
 * (see `docs/execution-memory.md`'s own "Why success rate can fall"
 * section). A first-time failure with no existing pattern to degrade
 * creates nothing — there is no successful capability list to seed a new
 * pattern from.
 */
final class LearnFromExecutionListener
{
    public function __construct(
        private readonly PatternExtractorInterface $extractor,
        private readonly ExecutionPatternRepositoryInterface $patterns,
    ) {
    }

    public function handle(GoalCompleted $event): void
    {
        $result = $event->result;
        $goalPattern = $this->extractor->patternFor($result->goal);

        if ($goalPattern === 'general') {
            return;
        }

        $now = new DateTimeImmutable();
        $existing = $this->patterns->findExisting($event->tenantId, $goalPattern, $result->goal->agentType);

        if ($existing !== null) {
            $existing->recordOutcome($result->isSuccessful(), $result->successfulCapabilities(), $now);
            $this->patterns->save($existing);

            return;
        }

        if (! $result->isSuccessful()) {
            return;
        }

        $extracted = $this->extractor->extract($result, $event->tenantId);

        if ($extracted !== null) {
            $this->patterns->save($extracted);
        }
    }
}
