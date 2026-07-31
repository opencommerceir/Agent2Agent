<?php

namespace App\Modules\Finance\Interfaces\MCP;

/**
 * The capability manifest for the Finance module — what
 * FinanceCapabilitiesSeeder registers into the Capability Registry and
 * FinanceServiceProvider wires into CapabilityHandlerRegistry. Kept as
 * plain data here, separate from the seeder's idempotency plumbing, the
 * same split Commerce's/CRM's own capability manifests established.
 *
 * Only 8 of Finance's 9 Actions are wired here — UpdateTaxRateAction was
 * built and tested but wasn't among the 8 capabilities requested this
 * stage (see its own docblock).
 */
final class FinanceCapabilities
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
                'name' => 'finance.tax.create',
                'description' => 'Register a tax rate for a region (or the tenant-wide DEFAULT fallback)',
                'inputSchema' => ['region' => 'string', 'rate_percentage' => 'integer'],
                'outputSchema' => ['tax_rate' => 'array'],
                'requiredPermissions' => ['finance.tax.manage'],
            ],
            [
                'name' => 'finance.tax.get',
                'description' => 'Get the tax rate configured for a region',
                'inputSchema' => ['region' => 'string'],
                'outputSchema' => ['tax_rate' => 'array'],
                'requiredPermissions' => ['finance.tax.read'],
            ],
            [
                'name' => 'finance.tax.list',
                'description' => "List the tenant's configured tax rates, optionally filtered by active state",
                // is_active is optional.
                'inputSchema' => [],
                'outputSchema' => ['tax_rates' => 'array'],
                'requiredPermissions' => ['finance.tax.read'],
            ],
            [
                'name' => 'finance.invoice.create',
                'description' => 'Create an Invoice from an already-placed Order',
                // region is optional — omitted, it falls back to the
                // tenant's DEFAULT tax rate, and failing that, zero tax
                // (CreateInvoiceAction's own docblock).
                'inputSchema' => ['order_id' => 'integer'],
                'outputSchema' => ['invoice' => 'array'],
                'requiredPermissions' => ['finance.invoices.create'],
            ],
            [
                'name' => 'finance.invoice.issue',
                'description' => 'Issue a draft Invoice',
                'inputSchema' => ['invoice_id' => 'integer'],
                'outputSchema' => ['invoice' => 'array'],
                'requiredPermissions' => ['finance.invoices.manage'],
            ],
            [
                'name' => 'finance.invoice.get',
                'description' => 'Get an Invoice by id',
                'inputSchema' => ['invoice_id' => 'integer'],
                'outputSchema' => ['invoice' => 'array'],
                'requiredPermissions' => ['finance.invoices.read'],
            ],
            [
                'name' => 'finance.invoice.list',
                'description' => "List the tenant's Invoices, optionally filtered by status or customer",
                // status and customer_id are both optional.
                'inputSchema' => [],
                'outputSchema' => ['invoices' => 'array'],
                'requiredPermissions' => ['finance.invoices.read'],
            ],
            [
                'name' => 'finance.tax.calculate',
                'description' => 'Calculate the tax and total for a given amount in a given region',
                'inputSchema' => ['amount' => 'integer', 'currency' => 'string', 'region' => 'string'],
                'outputSchema' => ['tax_amount' => 'integer', 'total_amount' => 'integer'],
                'requiredPermissions' => ['finance.tax.read'],
            ],
        ];
    }
}
