<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\ClaudeClient;
use App\Modules\AgentOrchestrator\Application\Services\DeterministicPlanner;
use App\Modules\AgentOrchestrator\Application\Services\LLMPlanner;
use App\Modules\AgentOrchestrator\Application\Services\OpenAIClient;
use App\Modules\AgentOrchestrator\Application\Services\OpenRouterClient;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Proves AgentOrchestratorServiceProvider's own config-driven bindings —
 * both bound as closures re-evaluated on every resolution (never
 * singleton()), specifically so a test can flip config() and immediately
 * observe the other implementation, no container reset needed.
 */
class PlannerConfigTest extends TestCase
{
    public function test_plannerTypeDeterministic_resolvesDeterministicPlanner(): void
    {
        config(['agent-orchestrator.planner.type' => 'deterministic']);

        $this->assertInstanceOf(DeterministicPlanner::class, app(PlannerInterface::class));
    }

    public function test_plannerTypeLlm_resolvesLLMPlanner(): void
    {
        config(['agent-orchestrator.planner.type' => 'llm']);

        $this->assertInstanceOf(LLMPlanner::class, app(PlannerInterface::class));
    }

    public function test_defaultPlannerTypeIsDeterministic(): void
    {
        // No override — config/agent-orchestrator.php's own default,
        // reinforced explicitly by phpunit.xml's PLANNER_TYPE=deterministic.
        $this->assertInstanceOf(DeterministicPlanner::class, app(PlannerInterface::class));
    }

    public function test_llmProviderOpenai_resolvesOpenAIClient(): void
    {
        config(['agent-orchestrator.llm.provider' => 'openai']);

        $this->assertInstanceOf(OpenAIClient::class, app(LLMClientInterface::class));
    }

    public function test_llmProviderClaude_resolvesClaudeClient(): void
    {
        config(['agent-orchestrator.llm.provider' => 'claude']);

        $this->assertInstanceOf(ClaudeClient::class, app(LLMClientInterface::class));
    }

    public function test_llmProviderOpenrouter_resolvesOpenRouterClient(): void
    {
        config(['agent-orchestrator.llm.provider' => 'openrouter']);

        $this->assertInstanceOf(OpenRouterClient::class, app(LLMClientInterface::class));
    }

    public function test_unsupportedLlmProvider_throws(): void
    {
        config(['agent-orchestrator.llm.provider' => 'gemini']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported LLM provider: [gemini].');

        app(LLMClientInterface::class);
    }
}
