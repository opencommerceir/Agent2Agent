<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;

/**
 * Turns one finished `ExecutionResult` into the `ExecutionPattern` shape it
 * *would* be on its own (Phase 6, Stage 4, §7.29) — a pure Domain
 * calculation, no Repository dependency, the same "only combines what it's
 * given" shape `WorkflowEvaluator`/`PricingService` already establish
 * (HANDOFF §3). Never decides whether a matching pattern already exists for
 * this tenant/goal/Agent persona — that composite-key lookup, and the
 * choice between inserting this as a new row versus folding it into an
 * existing one via `ExecutionPattern::recordOutcome()`, is
 * `LearnFromExecutionListener`'s job (Application layer), not this
 * Service's.
 */
interface PatternExtractorInterface
{
    /**
     * `null` when `$result->isSuccessful()` is false — a *brand-new*
     * pattern is only ever seeded from a fully-successful run (HANDOFF
     * §7.29's own `ExecutionResult::isSuccessful()` docblock explains why
     * "partial" doesn't qualify as a template worth repeating). A failed
     * run against an *already-existing* pattern still degrades that
     * pattern's own success rate — see `patternFor()`, which is what makes
     * that possible without needing a non-null `ExecutionPattern` here.
     */
    public function extract(ExecutionResult $result, int $tenantId): ?ExecutionPattern;

    /**
     * The same keyword classification `extract()` uses internally, exposed
     * on its own so a *failed* run can still be matched against an
     * already-learned pattern (to degrade its `successRate`) even though
     * `extract()` itself returns null for a failure — see
     * `LearnFromExecutionListener`'s own docblock for why this split
     * matters: without it, a pattern's success rate could only ever rise,
     * never reflect a goal that stopped working. `'general'` means no
     * recognized keyword — the same sentinel `ExecutionPattern::matches()`
     * already treats as "never a real signal."
     */
    public function patternFor(Goal $goal): string;
}
