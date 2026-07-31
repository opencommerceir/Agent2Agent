<?php

namespace App\Modules\Finance;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Commerce\Application\Services\TaxRateProviderInterface;
use App\Modules\Finance\Application\Actions\CalculateTaxAction;
use App\Modules\Finance\Application\Actions\CreateInvoiceAction;
use App\Modules\Finance\Application\Actions\CreateTaxRateAction;
use App\Modules\Finance\Application\Actions\GetInvoiceAction;
use App\Modules\Finance\Application\Actions\GetTaxRateAction;
use App\Modules\Finance\Application\Actions\IssueInvoiceAction;
use App\Modules\Finance\Application\Actions\ListInvoicesAction;
use App\Modules\Finance\Application\Actions\ListTaxRatesAction;
use App\Modules\Finance\Application\DTOs\InvoiceData;
use App\Modules\Finance\Application\DTOs\TaxRateData;
use App\Modules\Finance\Domain\Repositories\InvoiceRepositoryInterface;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use App\Modules\Finance\Infrastructure\Repositories\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\Repositories\EloquentTaxRateRepository;
use App\Modules\Finance\Infrastructure\Services\CommerceTaxRateProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Finance module — Phase 3.2, built on Phase 1/2's
 * infrastructure and Phase 3.1's CRM Foundation pattern without changing
 * either. Finance depends on Commerce only through Commerce's own
 * published Domain Repository Interfaces (`OrderRepositoryInterface`,
 * `ProductRepositoryInterface` — see CreateInvoiceAction), the same
 * Dependency Inversion direction CRM established for Module -> Module.
 *
 * The one exception, and it runs the *other* direction: Commerce defines
 * `Application\Services\TaxRateProviderInterface` for its own checkout
 * pricing to depend on, and this provider's register() overrides
 * Commerce's own default (NullTaxRateProvider) with
 * CommerceTaxRateProvider — real per-tenant tax rates flow into
 * Commerce's pricing without Commerce ever importing anything from
 * `App\Modules\Finance\*` (see TaxRateProviderInterface's own docblock).
 * This only works because bootstrap/providers.php registers Finance
 * *after* Commerce — register() runs for every provider before boot()
 * runs for any of them, so this rebind is guaranteed to win.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows Commerce's/
 * CRM's seeder pattern instead (FinanceCapabilitiesSeeder), same
 * RefreshDatabase-ordering reason documented there.
 */
class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaxRateRepositoryInterface::class, EloquentTaxRateRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);

        $this->app->bind(TaxRateProviderInterface::class, CommerceTaxRateProvider::class);
    }

    public function boot(): void
    {
        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('finance.tax.create', function (array $input, AuthContext $context) {
            /** @var TaxRateData $taxRate */
            $taxRate = $this->app->make(CreateTaxRateAction::class)->execute(
                tenantId: $context->tenantId,
                region: $input['region'],
                ratePercentage: (int) $input['rate_percentage'],
            );

            return ['tax_rate' => $taxRate->toArray()];
        });

        $handlers->register('finance.tax.get', function (array $input, AuthContext $context) {
            /** @var TaxRateData $taxRate */
            $taxRate = $this->app->make(GetTaxRateAction::class)->execute($context->tenantId, $input['region']);

            return ['tax_rate' => $taxRate->toArray()];
        });

        $handlers->register(
            'finance.tax.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListTaxRatesAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('finance.invoice.create', function (array $input, AuthContext $context) {
            /** @var InvoiceData $invoice */
            $invoice = $this->app->make(CreateInvoiceAction::class)->execute(
                tenantId: $context->tenantId,
                orderId: (int) $input['order_id'],
                region: $input['region'] ?? null,
            );

            return ['invoice' => $invoice->toArray()];
        });

        $handlers->register('finance.invoice.issue', function (array $input, AuthContext $context) {
            /** @var InvoiceData $invoice */
            $invoice = $this->app->make(IssueInvoiceAction::class)->execute((int) $input['invoice_id'], $context->tenantId);

            return ['invoice' => $invoice->toArray()];
        });

        $handlers->register('finance.invoice.get', function (array $input, AuthContext $context) {
            /** @var InvoiceData $invoice */
            $invoice = $this->app->make(GetInvoiceAction::class)->execute((int) $input['invoice_id'], $context->tenantId);

            return ['invoice' => $invoice->toArray()];
        });

        $handlers->register(
            'finance.invoice.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListInvoicesAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register(
            'finance.tax.calculate',
            fn (array $input, AuthContext $context) => $this->app->make(CalculateTaxAction::class)->execute(
                tenantId: $context->tenantId,
                amount: (int) $input['amount'],
                currency: $input['currency'],
                region: $input['region'],
            ),
        );
    }
}
