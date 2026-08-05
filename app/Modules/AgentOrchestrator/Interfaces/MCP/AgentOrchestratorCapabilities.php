<?php

namespace App\Modules\AgentOrchestrator\Interfaces\MCP;

/**
 * The capability manifest for the Agent Orchestrator module — what
 * AgentOrchestratorCapabilitiesSeeder registers into the Capability
 * Registry and AgentOrchestratorServiceProvider wires into
 * CapabilityHandlerRegistry.
 *
 * Not named in this module's own original request (only the HTTP
 * `/api/agents/*` surface was) — added unprompted for the same reason
 * every module in this codebase gets one (HANDOFF §3 pattern #12): every
 * other module's own capabilities are reachable both directly over MCP
 * *and* through whatever transport-specific surface it also has (the
 * Admin Dashboard reuses the same Actions its own capabilities do,
 * HANDOFF §3 pattern #19) — this module's `/api/agents/*` Controller
 * reuses the exact same Actions these three capabilities call, so an
 * external MCP client (another Agent, a future multi-agent orchestration
 * one level up) can trigger a Goal the same way a human-facing client
 * hitting `/api/agents/{agent_type}` can.
 */
final class AgentOrchestratorCapabilities
{
    /**
     * @return list<array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, string>,
     *     outputSchema: array<string, string>,
     *     requiredPermissions: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'agent.goal.execute',
                'description' => 'Plan and execute a business Goal by invoking existing OpenCommerce capabilities on the caller\'s behalf',
                'inputSchema' => ['goal' => 'string', 'agent_type' => 'string'],
                'outputSchema' => ['goal' => 'string', 'agent_type' => 'string', 'steps' => 'array', 'summary' => 'string', 'status' => 'string', 'execution_time' => 'number'],
                'requiredPermissions' => ['agent.goals.execute'],
            ],
            [
                'name' => 'agent.execution.get',
                'description' => 'Retrieve one past goal Execution by id',
                'inputSchema' => ['execution_id' => 'integer'],
                'outputSchema' => ['goal' => 'string', 'agent_type' => 'string', 'steps' => 'array', 'summary' => 'string', 'status' => 'string', 'execution_time' => 'number'],
                'requiredPermissions' => ['agent.executions.read'],
            ],
            [
                'name' => 'agent.execution.list',
                'description' => "List the tenant's own past goal Executions, optionally filtered by agent_type or status",
                // agent_type, status, and limit are all optional.
                'inputSchema' => [],
                'outputSchema' => ['executions' => 'array'],
                'requiredPermissions' => ['agent.executions.read'],
            ],
            [
                'name' => 'agent.profile.get',
                'description' => 'Retrieve one Agent persona\'s own config-driven profile (planning rules, default inputs, expected permissions)',
                'inputSchema' => ['agent_type' => 'string'],
                'outputSchema' => ['profile' => 'array'],
                'requiredPermissions' => ['agent.profiles.read'],
            ],
            [
                'name' => 'agent.profile.list',
                'description' => 'List every configured Agent persona profile (§7.27)',
                'inputSchema' => [],
                'outputSchema' => ['profiles' => 'array'],
                'requiredPermissions' => ['agent.profiles.read'],
            ],
            [
                'name' => 'agent.memory.insights',
                'description' => "Aggregate stats over this tenant's own recent goal-execution history for one Agent persona (§7.29): total/successful executions, average duration, most-used capabilities, success rate",
                'inputSchema' => ['agent_type' => 'string'],
                'outputSchema' => ['insights' => 'object'],
                'requiredPermissions' => ['agent.memory.read'],
            ],
            [
                'name' => 'agent.memory.suggest',
                'description' => "Preview the plan Learning would use for a goal, if this tenant's own execution history already has a matching, sufficiently-successful pattern (§7.29) — null when nothing qualifies, the same plan ExecuteGoalAction would silently prefer over real planning",
                'inputSchema' => ['goal' => 'string', 'agent_type' => 'string'],
                'outputSchema' => ['suggested_plan' => 'object'],
                'requiredPermissions' => ['agent.memory.read'],
            ],
            [
                'name' => 'agent.collaboration.delegate',
                'description' => "Delegate a sub-task to a different Agent persona and run it synchronously under the caller's own real permissions (§7.30) — reachable from any plan step exactly like any other capability, not a special execution mode",
                'inputSchema' => ['from_agent' => 'string', 'to_agent' => 'string', 'task' => 'string'],
                'outputSchema' => ['delegation_id' => 'integer', 'result' => 'object'],
                'requiredPermissions' => ['agent.collaboration.delegate'],
            ],
            [
                'name' => 'agent.collaboration.messages',
                'description' => "List this tenant's own persona-to-persona communication log for one Agent persona, most recent first (§7.30)",
                'inputSchema' => ['agent_type' => 'string'],
                'outputSchema' => ['messages' => 'array'],
                'requiredPermissions' => ['agent.collaboration.read'],
            ],
            [
                'name' => 'agent.reasoning.trace',
                'description' => "Retrieve the pre-execution reasoning and post-execution reflection recorded for one past goal Execution (§7.31) — either may be null if reflection never ran",
                'inputSchema' => ['execution_id' => 'integer'],
                'outputSchema' => ['pre_reasoning' => 'object', 'post_reasoning' => 'object'],
                'requiredPermissions' => ['agent.reasoning.read'],
            ],
            [
                'name' => 'agent.reasoning.explain',
                'description' => 'Render one past goal Execution\'s own recorded reasoning trace(s) as a human-readable explanation (§7.31)',
                'inputSchema' => ['execution_id' => 'integer'],
                'outputSchema' => ['explanation' => 'string'],
                'requiredPermissions' => ['agent.reasoning.read'],
            ],
        ];
    }
}
