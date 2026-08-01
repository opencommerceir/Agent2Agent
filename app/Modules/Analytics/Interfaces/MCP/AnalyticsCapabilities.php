<?php

namespace App\Modules\Analytics\Interfaces\MCP;

/**
 * The capability manifest for the Analytics module — what
 * AnalyticsCapabilitiesSeeder registers into the Capability Registry and
 * AnalyticsServiceProvider wires into CapabilityHandlerRegistry.
 *
 * **One real correction from this stage's own request**: the request's
 * own input schemas for `analytics.dashboard.stats`/`analytics.snapshot.generate`
 * named an optional `tenant_id` input, and `analytics.kpi.list` named one
 * too. Every other MCP capability in this codebase — without a single
 * exception — scopes exclusively to `AuthContext::$tenantId` (the
 * authenticated Agent's own tenant), never a caller-supplied tenant id;
 * accepting one here would let any Agent read a *different* Tenant's
 * revenue/customers/orders just by passing its id, a real cross-tenant
 * data leak. Dropped from all three input schemas — tenant scoping is
 * exactly as implicit here as it is everywhere else in this codebase.
 */
final class AnalyticsCapabilities
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
                'name' => 'analytics.kpi.calculate',
                'description' => 'Calculate a single KPI for a date range, cached for 1 hour',
                'inputSchema' => ['kpi_type' => 'string', 'time_period' => 'string', 'start_date' => 'string', 'end_date' => 'string'],
                'outputSchema' => ['kpi' => 'array'],
                'requiredPermissions' => ['analytics.kpis.read'],
            ],
            [
                'name' => 'analytics.kpi.list',
                'description' => "List the tenant's own KPI definitions",
                // is_active is optional.
                'inputSchema' => [],
                'outputSchema' => ['kpis' => 'array'],
                'requiredPermissions' => ['analytics.kpis.read'],
            ],
            [
                'name' => 'analytics.dashboard.stats',
                'description' => 'The 6 headline KPIs + Top 5 Products + 5 most recent Orders for the current calendar month',
                'inputSchema' => [],
                'outputSchema' => ['stats' => 'array'],
                'requiredPermissions' => ['analytics.dashboard.read'],
            ],
            [
                'name' => 'analytics.snapshot.generate',
                'description' => "Compute and persist today's AnalyticsSnapshot for the tenant (upserts if one already exists for today)",
                'inputSchema' => [],
                'outputSchema' => ['snapshot' => 'array'],
                'requiredPermissions' => ['analytics.snapshots.create'],
            ],
            [
                'name' => 'analytics.report.export',
                'description' => 'Export the KPI summary report as CSV or PDF, returns a downloadable file URL',
                'inputSchema' => ['report_type' => 'string', 'format' => 'string', 'start_date' => 'string', 'end_date' => 'string'],
                'outputSchema' => ['file_url' => 'string'],
                'requiredPermissions' => ['analytics.reports.export'],
            ],
        ];
    }
}
