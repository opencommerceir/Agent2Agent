<?php

namespace App\Modules\Workflows\Interfaces\MCP;

/**
 * The capability manifest for the Workflows module — what
 * WorkflowsCapabilitiesSeeder registers into the Capability Registry and
 * WorkflowsServiceProvider wires into CapabilityHandlerRegistry. Kept as
 * plain data here, separate from the seeder's idempotency plumbing, the
 * same split Commerce's/CRM's/Finance's own capability manifests
 * established.
 *
 * All 4 requested names were renamed from 2 segments to 3 —
 * `workflow.create/get/list/trigger` — CapabilityName requires exactly 3
 * dot-separated segments (HANDOFF gotcha #2, hit again here the same way
 * WooCommerce's and CRM's capabilities hit it). `workflow.log.list` was
 * already compliant. Permissions were renamed the same way
 * (`workflows.manage/read/execute` -> `workflow.definitions.manage/read/execute`,
 * PermissionKey has the identical 3-segment requirement) while keeping
 * the same 3 permission concepts the request specified — `read` covers
 * get/list/log.list exactly as originally grouped.
 *
 * Only 6 of Workflows' 7 Actions are wired here — UpdateWorkflowAction
 * was built and tested but wasn't among the 5 capabilities requested
 * this stage (see its own docblock).
 */
final class WorkflowsCapabilities
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
                'name' => 'workflow.definition.create',
                'description' => 'Create a Workflow: an event type, a set of matching rules, and the actions to run when they all match',
                'inputSchema' => ['name' => 'string', 'event_type' => 'string', 'rules' => 'array', 'actions' => 'array'],
                // description is optional.
                'outputSchema' => ['workflow' => 'array'],
                'requiredPermissions' => ['workflow.definitions.manage'],
            ],
            [
                'name' => 'workflow.definition.get',
                'description' => 'Get a Workflow by id',
                'inputSchema' => ['workflow_id' => 'integer'],
                'outputSchema' => ['workflow' => 'array'],
                'requiredPermissions' => ['workflow.definitions.read'],
            ],
            [
                'name' => 'workflow.definition.list',
                'description' => "List the tenant's Workflows, optionally filtered by status or event type",
                // status and event_type are both optional.
                'inputSchema' => [],
                'outputSchema' => ['workflows' => 'array'],
                'requiredPermissions' => ['workflow.definitions.read'],
            ],
            [
                'name' => 'workflow.event.trigger',
                'description' => 'Raise an event and run every active, matching Workflow registered for it',
                'inputSchema' => ['event_type' => 'string', 'event_data' => 'object'],
                'outputSchema' => ['triggered_count' => 'integer', 'workflows' => 'array'],
                'requiredPermissions' => ['workflow.definitions.execute'],
            ],
            [
                'name' => 'workflow.log.list',
                'description' => "List the tenant's Workflow trigger history, optionally filtered by workflow",
                // workflow_id and limit are both optional.
                'inputSchema' => [],
                'outputSchema' => ['logs' => 'array'],
                'requiredPermissions' => ['workflow.definitions.read'],
            ],
        ];
    }
}
