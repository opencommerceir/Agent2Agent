<?php

namespace App\Modules\Reporting;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Reporting\Application\Actions\GenerateLoyaltyReportAction;
use App\Modules\Reporting\Application\Actions\GenerateRevenueReportAction;
use App\Modules\Reporting\Application\Actions\GenerateSalesReportAction;
use App\Modules\Reporting\Application\Actions\GenerateTopCustomersReportAction;
use App\Modules\Reporting\Application\Actions\GenerateTopProductsReportAction;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Infrastructure\Repositories\EloquentReportRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Reporting module — Phase 3, Stage 5, a *read-only*
 * module built entirely on data Commerce/Loyalty already own. Unlike
 * every prior Phase 3 module, Reporting needs no Domain Event Listener
 * and changes nothing about any other module — it only reads.
 *
 * Only `ReportRepositoryInterface` is bound here (Reporting's own two
 * tables — `reports`/`report_results`). The five `Infrastructure\Queries\*`
 * Query Builders are deliberately NOT behind an interface and NOT bound
 * here: they're concrete, container-autowired classes, not a swappable
 * business abstraction — there is exactly one way to compute a SQL
 * aggregate against Commerce/Loyalty's current schema, so an
 * Interface + Eloquent-implementation split (the shape every real
 * Repository in this codebase has) would be pure ceremony here. See
 * SalesQueryBuilder's own docblock for the full reasoning behind this
 * module querying other modules' Eloquent Models directly at all.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows the
 * established seeder pattern instead (ReportingCapabilitiesSeeder), same
 * RefreshDatabase-ordering reason documented there.
 */
class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportRepositoryInterface::class, EloquentReportRepository::class);
    }

    public function boot(): void
    {
        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register(
            'report.sales.generate',
            fn (array $input, AuthContext $context) => $this->app->make(GenerateSalesReportAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                startDate: $input['start_date'],
                endDate: $input['end_date'],
            ),
        );

        $handlers->register(
            'report.products.top',
            fn (array $input, AuthContext $context) => $this->app->make(GenerateTopProductsReportAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                startDate: $input['start_date'],
                endDate: $input['end_date'],
                limit: isset($input['limit']) ? (int) $input['limit'] : null,
            ),
        );

        $handlers->register(
            'report.customers.top',
            fn (array $input, AuthContext $context) => $this->app->make(GenerateTopCustomersReportAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                startDate: $input['start_date'],
                endDate: $input['end_date'],
                limit: isset($input['limit']) ? (int) $input['limit'] : null,
            ),
        );

        $handlers->register(
            'report.revenue.generate',
            fn (array $input, AuthContext $context) => $this->app->make(GenerateRevenueReportAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                startDate: $input['start_date'],
                endDate: $input['end_date'],
            ),
        );

        $handlers->register(
            'report.loyalty.generate',
            fn (array $input, AuthContext $context) => $this->app->make(GenerateLoyaltyReportAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                startDate: $input['start_date'],
                endDate: $input['end_date'],
            ),
        );
    }
}
