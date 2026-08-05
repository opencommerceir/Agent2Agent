<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\ReasoningEngineInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;

/**
 * The no-LLM `ReasoningEngineInterface` implementation (Phase 6, Stage 6,
 * §7.31) — the reasoning-side sibling to `DeterministicPlanner`. Two real
 * roles: (1) the platform's own default (`config('agent-orchestrator.reasoning.type')`
 * is `simple` out of the box, the same "safe default, no real network call
 * unless explicitly opted into" reasoning `planner.type` defaulting to
 * `deterministic` already established, §7.28), and (2) `LLMReasoningEngine`'s
 * own automatic fallback target on any LLM failure — the exact shape
 * `LLMPlanner` -> `DeterministicPlanner` already establishes.
 *
 * Still real, not a stub: it reads this tenant's own `ExecutionPattern`
 * history (the same `ExecutionPatternRepositoryInterface::findSimilarPatterns()`
 * `LLMReasoningEngine` itself uses for prompt context) and derives an
 * honest confidence from real numbers — a matched pattern's own
 * `successRate()` when thinking, the real `ExecutionResult::successRate()`
 * when reflecting — rather than a made-up constant.
 */
final class SimpleReasoningEngine implements ReasoningEngineInterface
{
    private const MAX_SIMILAR_PATTERNS = 5;

    private const DEFAULT_CONFIDENCE = 0.5;

    public function __construct(
        private readonly ExecutionPatternRepositoryInterface $patterns,
    ) {
    }

    public function think(Goal $goal, AgentProfile $profile, int $tenantId): ReasoningTrace
    {
        $similar = $this->patterns->findSimilarPatterns($tenantId, $goal->text, $goal->agentType, self::MAX_SIMILAR_PATTERNS);

        if ($similar === []) {
            $thoughts = [
                "No similar past executions found for this tenant and the [{$profile->type->value}] persona.",
                "Proceeding with the [{$profile->name}] persona's own planning rules for this goal.",
            ];
            $confidence = ConfidenceScore::fromFloat(self::DEFAULT_CONFIDENCE);
        } else {
            $averageSuccessRate = array_sum(array_map(fn ($pattern) => $pattern->successRate(), $similar)) / count($similar);

            $thoughts = [
                sprintf('Found %d similar past goal pattern(s) for this tenant, averaging %.0f%% success.', count($similar), $averageSuccessRate * 100),
                "Proceeding with the [{$profile->name}] persona's own planning rules for this goal.",
            ];
            $confidence = ConfidenceScore::fromFloat(round($averageSuccessRate, 2));
        }

        return ReasoningTrace::create(
            tenantId: $tenantId,
            agentType: $goal->agentType,
            goalText: $goal->text,
            reasoningType: ReasoningType::PreExecution,
            thoughts: $thoughts,
            alternatives: [],
            confidenceScore: $confidence,
            decision: "Proceed with the {$profile->type->value} persona's planned capability sequence.",
            explanation: 'Deterministic reasoning (no LLM configured or the LLM call failed) — decision based on this tenant\'s own recorded execution history, not a generated narrative.',
        );
    }

    public function reflect(ExecutionResult $result, ReasoningTrace $preReasoning, int $tenantId, int $executionId): ReasoningTrace
    {
        $successRate = $result->successRate();

        $thoughts = [
            sprintf('Execution finished with status [%s], %.0f%% success rate.', $result->status, $successRate * 100),
            $result->successfulCapabilities() !== []
                ? 'Succeeded: '.implode(', ', $result->successfulCapabilities())
                : 'No steps succeeded.',
        ];

        if ($result->failedCapabilities() !== []) {
            $thoughts[] = 'Failed: '.implode(', ', $result->failedCapabilities());
        }

        return ReasoningTrace::create(
            tenantId: $tenantId,
            agentType: $preReasoning->agentType,
            goalText: $preReasoning->goalText,
            reasoningType: ReasoningType::PostExecution,
            thoughts: $thoughts,
            alternatives: [],
            confidenceScore: ConfidenceScore::fromFloat(round($successRate, 2)),
            decision: $result->isSuccessful()
                ? 'The planned approach worked; no change recommended for a similar future goal.'
                : 'The planned approach did not fully succeed; review the failed capabilities above before retrying a similar goal.',
            explanation: 'Deterministic reflection (no LLM configured or the LLM call failed) — based directly on the real execution outcome, not a generated narrative.',
            executionId: $executionId,
        );
    }
}
