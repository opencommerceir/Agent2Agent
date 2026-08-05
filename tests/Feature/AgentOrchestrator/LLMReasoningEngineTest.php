<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\LLMReasoningEngine;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use App\Modules\AgentOrchestrator\Domain\Services\ReasoningEngineInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;
use RuntimeException;
use Tests\TestCase;

/**
 * A Laravel-booted Feature test, not framework-free Unit, purely because
 * `LLMReasoningEngine` logs through the `Log` facade — the same reason
 * `LLMPlannerTest` is a Feature test (§7.28). No database is touched.
 */
class LLMReasoningEngineTest extends TestCase
{
    public function test_think_convertsAWellFormedLlmResponseIntoARealTrace(): void
    {
        $llmClient = $this->fakeLlmClient(fn () => [
            'thoughts' => ['Goal requires a 15% increase.', 'Similar goal last month succeeded with 12%.'],
            'alternatives' => [
                ['plan' => 'Focus on high-margin products only', 'confidence' => 0.7, 'reason' => 'Lower volume but higher profit'],
            ],
            'confidence' => 0.85,
            'decision' => 'Use a targeted coupon campaign on top-selling products',
            'explanation' => 'Based on past success with similar strategy',
        ]);

        $engine = new LLMReasoningEngine($llmClient, $this->patterns([]), $this->neverCalledFallback());

        $trace = $engine->think($this->goal(), $this->profile(), tenantId: 1);

        $this->assertSame(ReasoningType::PreExecution, $trace->reasoningType);
        $this->assertCount(2, $trace->thoughts);
        $this->assertCount(1, $trace->alternatives);
        $this->assertSame('Focus on high-margin products only', $trace->alternatives[0]->plan);
        $this->assertSame(0.85, $trace->confidenceScore->value);
        $this->assertSame('Use a targeted coupon campaign on top-selling products', $trace->decision);
        $this->assertNull($trace->executionId());
    }

    public function test_think_fallsBackWhenTheLlmClientThrows(): void
    {
        $llmClient = $this->fakeLlmClient(function () {
            throw new RuntimeException('network unreachable');
        });

        $fallbackTrace = $this->fallbackTrace();
        $engine = new LLMReasoningEngine($llmClient, $this->patterns([]), $this->fakeReasoningEngine($fallbackTrace));

        $result = $engine->think($this->goal(), $this->profile(), tenantId: 1);

        $this->assertSame($fallbackTrace, $result);
    }

    public function test_think_fallsBackWhenTheResponseIsMissingRequiredKeys(): void
    {
        $llmClient = $this->fakeLlmClient(fn () => ['thoughts' => ['ok']]); // no confidence/decision/explanation

        $fallbackTrace = $this->fallbackTrace();
        $engine = new LLMReasoningEngine($llmClient, $this->patterns([]), $this->fakeReasoningEngine($fallbackTrace));

        $this->assertSame($fallbackTrace, $engine->think($this->goal(), $this->profile(), tenantId: 1));
    }

    public function test_think_rethrowsWhenFallbackIsDisabled(): void
    {
        $llmClient = $this->fakeLlmClient(function () {
            throw new RuntimeException('network unreachable');
        });

        $engine = new LLMReasoningEngine($llmClient, $this->patterns([]), $this->neverCalledFallback(), fallbackEnabled: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('network unreachable');

        $engine->think($this->goal(), $this->profile(), tenantId: 1);
    }

    public function test_reflect_convertsAWellFormedLlmResponseIntoARealTrace(): void
    {
        $llmClient = $this->fakeLlmClient(fn () => [
            'thoughts' => ['Execution completed successfully with a 16% sales increase.'],
            'confidence' => 0.92,
            'decision' => 'Targeted coupon strategy is effective; improve notification reach next time.',
            'explanation' => 'Goal exceeded by 1%.',
        ]);

        $engine = new LLMReasoningEngine($llmClient, $this->patterns([]), $this->neverCalledFallback());
        $preReasoning = $this->fallbackTrace();

        $trace = $engine->reflect($this->completedResult(), $preReasoning, tenantId: 1, executionId: 42);

        $this->assertSame(ReasoningType::PostExecution, $trace->reasoningType);
        $this->assertSame(42, $trace->executionId());
        $this->assertSame(0.92, $trace->confidenceScore->value);
        $this->assertSame([], $trace->alternatives);
    }

    public function test_reflect_fallsBackWhenTheLlmClientThrows(): void
    {
        $llmClient = $this->fakeLlmClient(function () {
            throw new RuntimeException('network unreachable');
        });

        $fallbackTrace = $this->fallbackTrace();
        $engine = new LLMReasoningEngine($llmClient, $this->patterns([]), $this->fakeReasoningEngine($fallbackTrace));
        $preReasoning = $this->fallbackTrace();

        $result = $engine->reflect($this->completedResult(), $preReasoning, tenantId: 1, executionId: 42);

        $this->assertSame($fallbackTrace, $result);
    }

    private function fakeLlmClient(\Closure $respond): LLMClientInterface
    {
        return new class($respond) implements LLMClientInterface {
            public function __construct(private readonly \Closure $respond)
            {
            }

            public function complete(string $prompt, array $options = []): string
            {
                return '';
            }

            public function completeStructured(string $prompt, string $schema, array $options = []): array
            {
                return ($this->respond)();
            }
        };
    }

    private function patterns(array $similar): ExecutionPatternRepositoryInterface
    {
        return new class($similar) implements ExecutionPatternRepositoryInterface {
            public function __construct(private readonly array $similar)
            {
            }

            public function save(ExecutionPattern $pattern): void
            {
            }

            public function findExisting(int $tenantId, string $goalPattern, AgentType $agentType): ?ExecutionPattern
            {
                return null;
            }

            public function findSimilarPatterns(int $tenantId, string $goal, AgentType $agentType, int $limit): array
            {
                return $this->similar;
            }
        };
    }

    private function fakeReasoningEngine(ReasoningTrace $trace): ReasoningEngineInterface
    {
        return new class($trace) implements ReasoningEngineInterface {
            public function __construct(private readonly ReasoningTrace $trace)
            {
            }

            public function think(Goal $goal, AgentProfile $profile, int $tenantId): ReasoningTrace
            {
                return $this->trace;
            }

            public function reflect(ExecutionResult $result, ReasoningTrace $preReasoning, int $tenantId, int $executionId): ReasoningTrace
            {
                return $this->trace;
            }
        };
    }

    private function neverCalledFallback(): ReasoningEngineInterface
    {
        return new class implements ReasoningEngineInterface {
            public function think(Goal $goal, AgentProfile $profile, int $tenantId): ReasoningTrace
            {
                throw new RuntimeException('fallback should not have been called');
            }

            public function reflect(ExecutionResult $result, ReasoningTrace $preReasoning, int $tenantId, int $executionId): ReasoningTrace
            {
                throw new RuntimeException('fallback should not have been called');
            }
        };
    }

    private function fallbackTrace(): ReasoningTrace
    {
        return ReasoningTrace::create(
            tenantId: 1,
            agentType: AgentType::Ceo,
            goalText: 'Increase sales by 15% this week',
            reasoningType: ReasoningType::PreExecution,
            thoughts: ['Deterministic fallback thought.'],
            alternatives: [],
            confidenceScore: ConfidenceScore::fromFloat(0.5),
            decision: 'Proceed with the planned capability sequence.',
            explanation: 'Deterministic reasoning.',
        );
    }

    private function completedResult(): ExecutionResult
    {
        $step = new ExecutionStep('report.sales.generate', []);
        $step->markAsRunning();
        $step->markAsCompleted(['total' => 100]);

        return ExecutionResult::fromSteps($this->goal(), [$step], 1.2);
    }

    private function goal(): Goal
    {
        return Goal::fromText('Increase sales by 15% this week', AgentType::Ceo);
    }

    private function profile(): AgentProfile
    {
        return AgentProfile::fromConfig(AgentType::Ceo, [
            'name' => 'CEO Agent',
            'description' => 'Test profile',
            'planning_rules' => ['default' => []],
            'default_inputs' => [],
            'permissions' => [],
        ]);
    }
}
