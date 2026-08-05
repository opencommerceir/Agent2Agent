<?php

namespace App\Modules\AgentOrchestrator;

use App\Core\Application\Actions\DiscoverCapabilitiesAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\AgentOrchestrator\Application\Actions\ExecuteGoalAction;
use App\Modules\AgentOrchestrator\Application\Actions\GetAgentProfileAction;
use App\Modules\AgentOrchestrator\Application\Actions\GetExecutionResultAction;
use App\Modules\AgentOrchestrator\Application\Actions\ListAgentProfilesAction;
use App\Modules\AgentOrchestrator\Application\Actions\ListExecutionsAction;
use App\Modules\AgentOrchestrator\Application\Listeners\LogExecutionStepListener;
use App\Modules\AgentOrchestrator\Application\Services\CapabilityToolInvoker;
use App\Modules\AgentOrchestrator\Application\Services\ClaudeClient;
use App\Modules\AgentOrchestrator\Application\Services\DeterministicPlanner;
use App\Modules\AgentOrchestrator\Application\Services\LLMPlanner;
use App\Modules\AgentOrchestrator\Application\Services\OpenAIClient;
use App\Modules\AgentOrchestrator\Application\Services\PlanExecutor;
use App\Modules\AgentOrchestrator\Domain\Events\StepExecuted;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PlanExecutorInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use App\Modules\AgentOrchestrator\Domain\Services\ToolInvokerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Infrastructure\Repositories\ConfigBasedAgentProfileRepository;
use App\Modules\AgentOrchestrator\Infrastructure\Repositories\EloquentExecutionMemoryRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Registers the Agent Orchestrator module. `AgentProfileRepositoryInterface`
 * -> `ConfigBasedAgentProfileRepository` (§7.27) — a future database-backed
 * profile store is a drop-in replacement behind the same Interface.
 *
 * `PlannerInterface`'s own binding (§7.28) is a runtime, config-driven
 * choice between `DeterministicPlanner` and `LLMPlanner` — deliberately a
 * closure, not a plain class-string bind, re-evaluated on every
 * resolution (never `singleton()`) specifically so a test can override
 * `config('agent-orchestrator.planner.type')` before resolving it and get
 * the other implementation, the same "read config fresh, don't cache the
 * binding" shape `LLMClientInterface`'s own binding below needs for the
 * identical reason.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows the
 * established seeder pattern instead (AgentOrchestratorCapabilitiesSeeder)
 * — same RefreshDatabase-ordering reason every other module's own seeder
 * gives (see DemoCapabilitiesSeeder's own docblock).
 */
class AgentOrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExecutionMemoryRepositoryInterface::class, EloquentExecutionMemoryRepository::class);
        $this->app->bind(AgentProfileRepositoryInterface::class, ConfigBasedAgentProfileRepository::class);
        $this->app->bind(ToolInvokerInterface::class, CapabilityToolInvoker::class);
        $this->app->bind(PlanExecutorInterface::class, PlanExecutor::class);

        $this->app->bind(LLMClientInterface::class, function ($app) {
            $provider = config('agent-orchestrator.llm.provider');

            return match ($provider) {
                'openai' => new OpenAIClient(
                    config('agent-orchestrator.llm.openai.api_key'),
                    config('agent-orchestrator.llm.openai.model'),
                ),
                'claude' => new ClaudeClient(
                    config('agent-orchestrator.llm.claude.api_key'),
                    config('agent-orchestrator.llm.claude.model'),
                ),
                default => throw new InvalidArgumentException("Unsupported LLM provider: [{$provider}]."),
            };
        });

        $this->app->bind(PlannerInterface::class, function ($app) {
            if (config('agent-orchestrator.planner.type') !== 'llm') {
                return $app->make(DeterministicPlanner::class);
            }

            return new LLMPlanner(
                $app->make(LLMClientInterface::class),
                $app->make(DiscoverCapabilitiesAction::class),
                $app->make(DeterministicPlanner::class),
                (bool) config('agent-orchestrator.planner.fallback_to_deterministic', true),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/agents.php'));

        Event::listen(StepExecuted::class, LogExecutionStepListener::class);

        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('agent.goal.execute', fn (array $input, AuthContext $context) => $this->app->make(ExecuteGoalAction::class)->execute(
            goalText: $input['goal'],
            agentType: AgentType::from($input['agent_type']),
            context: $context,
        )->toArray());

        $handlers->register('agent.execution.get', fn (array $input, AuthContext $context) => $this->app->make(GetExecutionResultAction::class)->execute(
            executionId: (int) $input['execution_id'],
            tenantId: $context->tenantId,
        )->toArray());

        $handlers->register('agent.execution.list', function (array $input, AuthContext $context) {
            $agentType = isset($input['agent_type']) ? AgentType::tryFrom($input['agent_type']) : null;
            $status = $input['status'] ?? null;
            $limit = isset($input['limit']) ? (int) $input['limit'] : null;

            $results = $this->app->make(ListExecutionsAction::class)->execute($context->tenantId, $agentType, $status, $limit);

            return ['executions' => array_map(fn ($result) => $result->toArray(), $results)];
        });

        $handlers->register('agent.profile.get', fn (array $input, AuthContext $context) => [
            'profile' => $this->app->make(GetAgentProfileAction::class)->execute($input['agent_type'])->toArray(),
        ]);

        $handlers->register('agent.profile.list', fn (array $input, AuthContext $context) => [
            'profiles' => array_map(
                fn ($profile) => $profile->toArray(),
                $this->app->make(ListAgentProfilesAction::class)->execute(),
            ),
        ]);
    }
}
