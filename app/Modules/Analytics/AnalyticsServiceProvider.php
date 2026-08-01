<?php

namespace App\Modules\Analytics;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Analytics\Application\Actions\CalculateKPIAction;
use App\Modules\Analytics\Application\Actions\ExportReportAction;
use App\Modules\Analytics\Application\Actions\GenerateSnapshotAction;
use App\Modules\Analytics\Application\Actions\GetDashboardStatsAction;
use App\Modules\Analytics\Application\Actions\ListKPIsAction;
use App\Modules\Analytics\Domain\Repositories\AnalyticsSnapshotRepositoryInterface;
use App\Modules\Analytics\Domain\Repositories\KPIRepositoryInterface;
use App\Modules\Analytics\Infrastructure\Repositories\EloquentAnalyticsSnapshotRepository;
use App\Modules\Analytics\Infrastructure\Repositories\EloquentKPIRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Analytics module — Phase 4, Stage 6. Only
 * `KPIRepositoryInterface`/`AnalyticsSnapshotRepositoryInterface` are
 * bound here (Analytics' own tables). Reporting's five
 * `Infrastructure\Queries\*` Query Builders this module depends on
 * (`CalculateKPIAction`'s own docblock explains why) are, like every
 * other Query Builder in this codebase, plain container-autowired
 * concrete classes — nothing to bind, resolved automatically.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows the
 * established seeder pattern instead (AnalyticsCapabilitiesSeeder), same
 * RefreshDatabase-ordering reason documented there.
 */
class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KPIRepositoryInterface::class, EloquentKPIRepository::class);
        $this->app->bind(AnalyticsSnapshotRepositoryInterface::class, EloquentAnalyticsSnapshotRepository::class);
    }

    public function boot(): void
    {
        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('analytics.kpi.calculate', fn (array $input, AuthContext $context) => [
            'kpi' => $this->app->make(CalculateKPIAction::class)->execute(
                tenantId: $context->tenantId,
                kpiType: $input['kpi_type'],
                timePeriod: $input['time_period'],
                startDate: $input['start_date'],
                endDate: $input['end_date'],
            )->toArray(),
        ]);

        $handlers->register('analytics.kpi.list', fn (array $input, AuthContext $context) => [
            'kpis' => array_map(
                fn ($kpi) => $kpi->toArray(),
                $this->app->make(ListKPIsAction::class)->execute($context->tenantId, $input['is_active'] ?? null),
            ),
        ]);

        $handlers->register(
            'analytics.dashboard.stats',
            fn (array $input, AuthContext $context) => ['stats' => $this->app->make(GetDashboardStatsAction::class)->execute($context->tenantId)->toArray()],
        );

        $handlers->register(
            'analytics.snapshot.generate',
            fn (array $input, AuthContext $context) => ['snapshot' => $this->app->make(GenerateSnapshotAction::class)->execute($context->tenantId)->toArray()],
        );

        $handlers->register('analytics.report.export', fn (array $input, AuthContext $context) => [
            'file_url' => $this->app->make(ExportReportAction::class)->execute(
                tenantId: $context->tenantId,
                reportType: $input['report_type'],
                format: $input['format'],
                startDate: $input['start_date'],
                endDate: $input['end_date'],
            ),
        ]);
    }
}
