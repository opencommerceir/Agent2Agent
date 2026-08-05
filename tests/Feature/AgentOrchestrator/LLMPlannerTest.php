<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\Actions\DiscoverCapabilitiesAction;
use App\Core\Domain\Entities\Capability;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;
use App\Core\Domain\ValueObjects\CapabilityName;
use App\Core\Domain\ValueObjects\CapabilitySchema;
use App\Modules\AgentOrchestrator\Application\Services\LLMPlanner;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;
use RuntimeException;
use Tests\TestCase;

/**
 * A Laravel-booted Feature test, not framework-free Unit, purely because
 * LLMPlanner logs through the `Log` facade (the same reason PlanExecutorTest
 * is a Feature test, §7.26) — no database is touched. A fake
 * CapabilityRepositoryInterface stands in for the real Eloquent one so a
 * real DiscoverCapabilitiesAction can be constructed without one.
 */
class LLMPlannerTest extends TestCase
{
    public function test_createPlan_convertsAWellFormedLlmResponseIntoARealPlan(): void
    {
        $llmClient = $this->fakeLlmClient(fn () => [
            'steps' => [
                ['capability' => 'report.sales.generate', 'input' => ['start_date' => '2026-01-01', 'end_date' => '2026-01-07'], 'priority' => 'high'],
                ['capability' => 'commerce.coupon.create', 'input' => ['code' => 'COUPON-ABCDE', 'discount_type' => 'percentage', 'discount_value' => 15]],
            ],
        ]);

        $planner = new LLMPlanner($llmClient, $this->discoverCapabilities(), $this->neverCalledFallback());

        $plan = $planner->createPlan($this->goal(), $this->profile());

        $this->assertCount(2, $plan->steps);
        $this->assertSame('report.sales.generate', $plan->steps[0]->capability);
        $this->assertSame(Priority::High, $plan->steps[0]->priority);
        $this->assertSame('commerce.coupon.create', $plan->steps[1]->capability);
        $this->assertSame(Priority::Medium, $plan->steps[1]->priority); // no priority given -> defaults
    }

    public function test_createPlan_fallsBackWhenTheLlmClientThrows(): void
    {
        $llmClient = $this->fakeLlmClient(function () {
            throw new RuntimeException('network unreachable');
        });

        $fallbackPlan = new ExecutionPlan($this->goal(), []);
        $fallbackPlanner = $this->fakePlanner($fallbackPlan);

        $planner = new LLMPlanner($llmClient, $this->discoverCapabilities(), $fallbackPlanner);

        $result = $planner->createPlan($this->goal(), $this->profile());

        $this->assertSame($fallbackPlan, $result);
    }

    public function test_createPlan_fallsBackWhenTheResponseHasNoStepsArray(): void
    {
        $llmClient = $this->fakeLlmClient(fn () => ['not_steps' => []]);
        $fallbackPlan = new ExecutionPlan($this->goal(), []);
        $fallbackPlanner = $this->fakePlanner($fallbackPlan);

        $planner = new LLMPlanner($llmClient, $this->discoverCapabilities(), $fallbackPlanner);

        $this->assertSame($fallbackPlan, $planner->createPlan($this->goal(), $this->profile()));
    }

    public function test_createPlan_fallsBackWhenAStepHasNoCapabilityName(): void
    {
        $llmClient = $this->fakeLlmClient(fn () => ['steps' => [['input' => []]]]);
        $fallbackPlan = new ExecutionPlan($this->goal(), []);
        $fallbackPlanner = $this->fakePlanner($fallbackPlan);

        $planner = new LLMPlanner($llmClient, $this->discoverCapabilities(), $fallbackPlanner);

        $this->assertSame($fallbackPlan, $planner->createPlan($this->goal(), $this->profile()));
    }

    public function test_createPlan_rethrowsWhenFallbackIsDisabled(): void
    {
        $llmClient = $this->fakeLlmClient(function () {
            throw new RuntimeException('network unreachable');
        });

        $planner = new LLMPlanner($llmClient, $this->discoverCapabilities(), $this->neverCalledFallback(), fallbackEnabled: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('network unreachable');

        $planner->createPlan($this->goal(), $this->profile());
    }

    public function test_supportsLLM_isTrue(): void
    {
        $planner = new LLMPlanner($this->fakeLlmClient(fn () => ['steps' => []]), $this->discoverCapabilities(), $this->neverCalledFallback());

        $this->assertTrue($planner->supportsLLM());
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

    private function fakePlanner(ExecutionPlan $plan): PlannerInterface
    {
        return new class($plan) implements PlannerInterface {
            public function __construct(private readonly ExecutionPlan $plan)
            {
            }

            public function createPlan(Goal $goal, AgentProfile $profile): ExecutionPlan
            {
                return $this->plan;
            }

            public function supportsLLM(): bool
            {
                return false;
            }
        };
    }

    private function neverCalledFallback(): PlannerInterface
    {
        return new class implements PlannerInterface {
            public function createPlan(Goal $goal, AgentProfile $profile): ExecutionPlan
            {
                throw new RuntimeException('fallback should not have been called');
            }

            public function supportsLLM(): bool
            {
                return false;
            }
        };
    }

    private function discoverCapabilities(): DiscoverCapabilitiesAction
    {
        $repository = new class implements CapabilityRepositoryInterface {
            public function findById(int $id): ?Capability
            {
                return null;
            }

            public function findByName(CapabilityName $name): ?Capability
            {
                return null;
            }

            public function all(): array
            {
                return [
                    Capability::register(
                        new CapabilityName('commerce.coupon.create'),
                        'Create a discount Coupon',
                        CapabilitySchema::fromArray(['code' => 'string']),
                        CapabilitySchema::fromArray(['coupon' => 'array']),
                    ),
                ];
            }

            public function save(Capability $capability): Capability
            {
                return $capability;
            }
        };

        return new DiscoverCapabilitiesAction($repository);
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
