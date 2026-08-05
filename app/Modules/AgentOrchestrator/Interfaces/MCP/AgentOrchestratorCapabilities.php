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
        ];
    }
}
