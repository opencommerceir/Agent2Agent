<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\LLMReasoningEngine;
use App\Modules\AgentOrchestrator\Application\Services\SimpleReasoningEngine;
use App\Modules\AgentOrchestrator\Domain\Services\ReasoningEngineInterface;
use Tests\TestCase;

/**
 * Proves AgentOrchestratorServiceProvider's own config-driven
 * `ReasoningEngineInterface` binding — the same "closure re-evaluated on
 * every resolution" shape `PlannerConfigTest` already establishes for
 * `PlannerInterface` (§7.28), one level over.
 */
class ReasoningConfigTest extends TestCase
{
    public function test_reasoningTypeSimple_resolvesSimpleReasoningEngine(): void
    {
        config(['agent-orchestrator.reasoning.type' => 'simple']);

        $this->assertInstanceOf(SimpleReasoningEngine::class, app(ReasoningEngineInterface::class));
    }

    public function test_reasoningTypeLlm_resolvesLLMReasoningEngine(): void
    {
        config(['agent-orchestrator.reasoning.type' => 'llm']);

        $this->assertInstanceOf(LLMReasoningEngine::class, app(ReasoningEngineInterface::class));
    }

    public function test_defaultReasoningTypeIsSimple(): void
    {
        // No override — config/agent-orchestrator.php's own default,
        // reinforced explicitly by phpunit.xml's REASONING_TYPE=simple, so
        // the whole suite never attempts a real LLM network call for
        // reasoning either (the same reasoning PLANNER_TYPE's own default
        // already established, §7.28).
        $this->assertInstanceOf(SimpleReasoningEngine::class, app(ReasoningEngineInterface::class));
    }
}
