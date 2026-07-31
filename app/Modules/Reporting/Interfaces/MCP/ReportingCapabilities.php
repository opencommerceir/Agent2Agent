<?php

namespace App\Modules\Reporting\Interfaces\MCP;

/**
 * The capability manifest for the Reporting module — what
 * ReportingCapabilitiesSeeder registers into the Capability Registry and
 * ReportingServiceProvider wires into CapabilityHandlerRegistry. Kept as
 * plain data here, separate from the seeder's idempotency plumbing, the
 * same split every prior module's own capability manifest established.
 *
 * All 5 requested names are already exactly 3 dot-separated segments —
 * same as Loyalty's Stage, no rename needed (HANDOFF gotcha #2).
 *
 * `GetReportAction`/`ListReportsAction` are the two built, tested
 * Actions not wired here — no `report.definition.get`/`.list`
 * capability was among the 5 requested (see either Action's own
 * docblock — HANDOFF §6's "built, tested, not yet exposed to Agents"
 * gap, same shape every prior module has carried at least one of).
 */
final class ReportingCapabilities
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
                'name' => 'report.sales.generate',
                'description' => 'Generate a Sales Report for a date range: total sales, total orders, average order value, sales by day',
                'inputSchema' => ['start_date' => 'date', 'end_date' => 'date'],
                'outputSchema' => ['report' => 'array'],
                'requiredPermissions' => ['reporting.sales.read'],
            ],
            [
                'name' => 'report.products.top',
                'description' => 'Generate a Top Products Report for a date range',
                // limit is optional (defaults to 10).
                'inputSchema' => ['start_date' => 'date', 'end_date' => 'date'],
                'outputSchema' => ['report' => 'array'],
                'requiredPermissions' => ['reporting.products.read'],
            ],
            [
                'name' => 'report.customers.top',
                'description' => 'Generate a Top Customers Report for a date range',
                // limit is optional (defaults to 10).
                'inputSchema' => ['start_date' => 'date', 'end_date' => 'date'],
                'outputSchema' => ['report' => 'array'],
                'requiredPermissions' => ['reporting.customers.read'],
            ],
            [
                'name' => 'report.revenue.generate',
                'description' => 'Generate a Revenue Report for a date range: gross revenue, tax collected, discounts applied, net revenue',
                'inputSchema' => ['start_date' => 'date', 'end_date' => 'date'],
                'outputSchema' => ['report' => 'array'],
                'requiredPermissions' => ['reporting.revenue.read'],
            ],
            [
                'name' => 'report.loyalty.generate',
                'description' => 'Generate a Loyalty Report for a date range: points earned/redeemed, active accounts, top earners',
                'inputSchema' => ['start_date' => 'date', 'end_date' => 'date'],
                'outputSchema' => ['report' => 'array'],
                'requiredPermissions' => ['reporting.loyalty.read'],
            ],
        ];
    }
}
