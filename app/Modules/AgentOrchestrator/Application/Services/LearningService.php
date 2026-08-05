<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\LearningServiceInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;

/**
 * The one `LearningServiceInterface` implementation (Phase 6, Stage 4,
 * §7.29). Reads from the *existing* `ExecutionMemoryRepositoryInterface`
 * (Stage 1, §7.26) for `getInsights()` and the new
 * `ExecutionPatternRepositoryInterface` for `suggestPlan()` — deliberately
 * does not introduce a second, parallel execution log (see
 * `docs/execution-memory.md`'s own "Why no new ExecutionMemory entity"
 * section for the full reasoning this stage's own planning caught before
 * writing any migration). `suggestPlan()` resolves each suggested step's
 * own default input through the same `AgentProfileInputResolver`
 * `DeterministicPlanner` uses — a learned pattern only ever remembers
 * *which capabilities* succeeded, never their resolved input values (see
 * `ExecutionPattern`'s own docblock), so a `{date:-7}`-shaped raw config
 * value still needs real resolution before it can reach a capability a
 * second time; skipping this step was a real bug caught by this stage's
 * own `LearningServiceTest` (a suggested plan's `report.sales.generate`
 * step failed real validation with a literal, unresolved `'{date:-7}'`
 * string).
 */
final class LearningService implements LearningServiceInterface
{
    /**
     * How many of the tenant's own most recent Executions `getInsights()`
     * reads over — a bounded, recent window, not a full-table scan, the
     * same "prove the proportional behavior, not raw throughput" scope
     * this codebase's own test suite always uses for a data-volume claim
     * (HANDOFF §7.23).
     */
    private const INSIGHTS_WINDOW = 50;

    private const SUGGESTION_CANDIDATES = 5;

    /**
     * A candidate pattern below this success rate is not suggested at all
     * — a pattern that fails more often than it works is not a template
     * worth skipping real planning for. Not named in the original
     * request; added the same way every prior "the request's own gap
     * would surface ugly at runtime" correction in this codebase was
     * (HANDOFF §3 pattern #12) — without it, a pattern learned once by
     * accident and never repeated (usage 1, one early failure) could keep
     * getting suggested forever.
     */
    private const MIN_SUCCESS_RATE = 0.5;

    public function __construct(
        private readonly ExecutionPatternRepositoryInterface $patterns,
        private readonly ExecutionMemoryRepositoryInterface $memory,
        private readonly AgentProfileRepositoryInterface $profiles,
        private readonly AgentProfileInputResolver $inputResolver = new AgentProfileInputResolver(),
    ) {
    }

    public function suggestPlan(Goal $goal, int $tenantId): ?ExecutionPlan
    {
        $candidates = $this->patterns->findSimilarPatterns(
            $tenantId,
            $goal->text,
            $goal->agentType,
            self::SUGGESTION_CANDIDATES,
        );

        $best = null;

        foreach ($candidates as $candidate) {
            if ($candidate->successRate() >= self::MIN_SUCCESS_RATE && $candidate->successfulCapabilities() !== []) {
                $best = $candidate;
                break; // already ordered success_rate desc, usage_count desc
            }
        }

        if ($best === null) {
            return null;
        }

        $profile = $this->profiles->findByType($goal->agentType->value);

        $steps = array_map(
            fn (string $capability) => new ExecutionStep(
                $capability,
                $this->inputResolver->resolve($profile->getDefaultInput($capability), $goal),
                Priority::Medium,
            ),
            $best->successfulCapabilities(),
        );

        return new ExecutionPlan($goal, $steps);
    }

    public function getInsights(int $tenantId, AgentType $agentType): array
    {
        $recent = $this->memory->list($tenantId, $agentType, null, self::INSIGHTS_WINDOW);
        $total = count($recent);

        $successful = array_values(array_filter(
            $recent,
            fn (array $record) => $record['result']->isSuccessful(),
        ));

        return [
            'total_executions' => $total,
            'average_duration' => $this->averageDuration($recent),
            'most_used_capabilities' => $this->mostUsedCapabilities($successful),
            'success_rate' => $total === 0 ? 0.0 : round(count($successful) / $total, 4),
            'recent_goals' => array_slice(
                array_map(fn (array $record) => $record['result']->goal->text, $recent),
                0,
                5,
            ),
        ];
    }

    /**
     * @param list<array{id: int, tenantId: int, agentId: int, agentType: AgentType, result: \App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult}> $records
     */
    private function averageDuration(array $records): float
    {
        if ($records === []) {
            return 0.0;
        }

        $total = array_sum(array_map(fn (array $record) => $record['result']->executionTimeSeconds, $records));

        return round($total / count($records), 4);
    }

    /**
     * @param list<array{id: int, tenantId: int, agentId: int, agentType: AgentType, result: \App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult}> $successfulRecords
     * @return list<array{capability: string, count: int}>
     */
    private function mostUsedCapabilities(array $successfulRecords): array
    {
        $counts = [];

        foreach ($successfulRecords as $record) {
            foreach ($record['result']->successfulCapabilities() as $capability) {
                $counts[$capability] = ($counts[$capability] ?? 0) + 1;
            }
        }

        arsort($counts);

        $ranked = [];
        foreach ($counts as $capability => $count) {
            $ranked[] = ['capability' => $capability, 'count' => $count];
        }

        return array_slice($ranked, 0, 5);
    }
}
