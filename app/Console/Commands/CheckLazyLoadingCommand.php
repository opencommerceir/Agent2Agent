<?php

namespace App\Console\Commands;

use App\Core\Application\Actions\OptimizeQueriesAction;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 Stage 8 (Performance Optimization, §7.20). A best-effort spot
 * check against a small, representative read scenario — not a full
 * request-cycle profiler (LazyLoadingDetector's own docblock already
 * explains why "execute a request" isn't literally meaningful for a
 * console command). Runs the exact three list-reads this stage's own
 * audit found real N+1 bugs in before fixing them
 * (EloquentOrderRepository::listByTenant()/EloquentWorkflowRepository::list(),
 * plus Product search for a Repository this stage's audit found already
 * eager-load-safe), against the first Tenant found, and reports any query
 * shape still repeating — proof this check would have caught the bugs
 * this stage fixed, and an ongoing regression guard against a future one
 * being reintroduced.
 */
class CheckLazyLoadingCommand extends Command
{
    protected $signature = 'performance:check-lazy-loading';

    protected $description = 'Run a representative read scenario and report any repeated (likely N+1) query shapes';

    public function handle(
        TenantRepositoryInterface $tenants,
        ProductRepositoryInterface $products,
        OrderRepositoryInterface $orders,
        WorkflowRepositoryInterface $workflows,
        OptimizeQueriesAction $optimizeQueries,
    ): int {
        $tenant = ($tenants->all()[0] ?? null);

        if ($tenant === null) {
            $this->warn('No tenants exist yet — nothing to check.');

            return self::SUCCESS;
        }

        $tenantId = $tenant->id();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $products->search($tenantId, null, null, 20, 0);
        $orders->listByTenant($tenantId, null, 20);
        $workflows->list($tenantId, null, null);

        $result = $optimizeQueries->execute();
        DB::disableQueryLog();

        if ($result['suspected_n_plus_one'] === []) {
            $this->info('No repeated (likely N+1) query shapes found in this scenario.');

            return self::SUCCESS;
        }

        $this->warn('Suspected N+1 query shapes:');

        foreach ($result['suggestions'] as $suggestion) {
            $this->line("  - {$suggestion}");
        }

        return self::SUCCESS;
    }
}
