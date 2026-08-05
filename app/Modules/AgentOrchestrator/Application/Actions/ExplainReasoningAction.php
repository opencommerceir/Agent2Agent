<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Domain\Exceptions\ExecutionNotFoundException;
use App\Modules\AgentOrchestrator\Domain\Repositories\ReasoningTraceRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\ExplanationGeneratorInterface;

/**
 * Backs `agent.reasoning.explain` (Phase 6, Stage 6, §7.31). Plain
 * `int $tenantId`, never `AuthContext` — same HANDOFF §3 pattern #1 shape
 * `GetReasoningTraceAction` already establishes. Renders whichever traces
 * actually exist for this execution (pre only, if reflection never ran —
 * see `ReasoningTraceRepositoryInterface`'s own docblock) — never both
 * unconditionally.
 */
final class ExplainReasoningAction
{
    public function __construct(
        private readonly ReasoningTraceRepositoryInterface $reasoningTraces,
        private readonly ExplanationGeneratorInterface $explanationGenerator,
    ) {
    }

    public function execute(int $tenantId, int $executionId): string
    {
        $traces = $this->reasoningTraces->findByExecution($tenantId, $executionId);

        if ($traces === []) {
            throw new ExecutionNotFoundException(
                "No reasoning trace found for execution id [{$executionId}]."
            );
        }

        return implode("\n---\n", array_map(
            fn ($trace) => $this->explanationGenerator->generate($trace),
            $traces,
        ));
    }
}
