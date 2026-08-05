<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Core\Application\Actions\DiscoverCapabilitiesAction;
use App\Modules\AgentOrchestrator\Application\Prompts\PlanningPromptTemplate;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The real, "intelligent" `PlannerInterface` implementation — asks a
 * configured LLM provider (`LLMClientInterface`) to plan a Goal against
 * every capability the platform currently has (`DiscoverCapabilitiesAction`
 * — the exact same building block `GET /mcp/v1/capabilities` itself uses
 * for discovery, not a "CapabilityRegistry" class, which does not exist
 * anywhere in this codebase; see this class's own docblock in
 * HANDOFF §7.28 for that correction), then converts the LLM's own
 * response into a real `ExecutionPlan`.
 *
 * Still holds no business logic — it never decides *whether* a plan is
 * good, only whether the LLM's *response* is well-formed enough to build
 * one from; whether the plan itself is any good is still, ultimately,
 * whatever downstream capabilities and their own permission/input
 * validation allow through (`CapabilityToolInvoker`, unchanged).
 *
 * On ANY failure — the LLM call itself, a malformed/unparseable
 * response, a response missing the expected shape — this class logs a
 * warning and falls back to the injected `PlannerInterface $fallbackPlanner`
 * (a `DeterministicPlanner` in practice), unless
 * `config('agent-orchestrator.planner.fallback_to_deterministic')` is
 * `false`, in which case the failure propagates (mapped to
 * `INTERNAL_ERROR`/500 by `MCPExceptionHandler` if it reaches an HTTP
 * caller, wrapped in `GoalExecutionFailedException` if it reaches
 * `ExecuteGoalAction` first). A Planner producing a working plan through
 * *some* mechanism is more valuable than a hard failure for an ordinary
 * production request — but an operator debugging the LLM integration
 * itself needs the ability to see the real failure instead of a silent
 * substitution, hence the flag.
 */
final class LLMPlanner implements PlannerInterface
{
    public function __construct(
        private readonly LLMClientInterface $llmClient,
        private readonly DiscoverCapabilitiesAction $discoverCapabilities,
        private readonly PlannerInterface $fallbackPlanner,
        private readonly bool $fallbackEnabled = true,
    ) {
    }

    public function createPlan(Goal $goal, AgentProfile $profile): ExecutionPlan
    {
        try {
            $capabilities = $this->discoverCapabilities->execute();
            $prompt = PlanningPromptTemplate::forGoal($goal, $profile, $capabilities);

            $response = $this->llmClient->completeStructured($prompt, $this->planSchema());

            $plan = $this->toExecutionPlan($goal, $response);

            Log::info('Plan created via LLM', ['goal' => $goal->text, 'steps' => count($plan->steps)]);

            return $plan;
        } catch (Throwable $e) {
            Log::warning('LLM planner failed', ['goal' => $goal->text, 'error' => $e->getMessage()]);

            if (! $this->fallbackEnabled) {
                throw $e;
            }

            Log::warning('Falling back to deterministic planner', ['goal' => $goal->text]);

            return $this->fallbackPlanner->createPlan($goal, $profile);
        }
    }

    public function supportsLLM(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function toExecutionPlan(Goal $goal, array $response): ExecutionPlan
    {
        $rawSteps = $response['steps'] ?? null;

        if (! is_array($rawSteps)) {
            throw new RuntimeException('LLM response did not contain a "steps" array.');
        }

        $steps = [];

        foreach ($rawSteps as $rawStep) {
            if (! is_array($rawStep) || ! isset($rawStep['capability']) || ! is_string($rawStep['capability'])) {
                throw new RuntimeException('LLM response contained a step with no valid "capability" name.');
            }

            $input = is_array($rawStep['input'] ?? null) ? $rawStep['input'] : [];
            $priority = is_string($rawStep['priority'] ?? null)
                ? (Priority::tryFrom($rawStep['priority']) ?? Priority::Medium)
                : Priority::Medium;

            $steps[] = new ExecutionStep($rawStep['capability'], $input, $priority);
        }

        return new ExecutionPlan($goal, $steps);
    }

    private function planSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'properties' => [
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'capability' => ['type' => 'string'],
                            'input' => ['type' => 'object'],
                            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        ],
                        'required' => ['capability', 'input'],
                    ],
                ],
            ],
            'required' => ['steps'],
        ]);
    }
}
