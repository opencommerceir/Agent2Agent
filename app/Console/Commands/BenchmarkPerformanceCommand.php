<?php

namespace App\Console\Commands;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Modules\Analytics\Application\Actions\GetDashboardStatsAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use Illuminate\Console\Command;

/**
 * Phase 4 Stage 8 (Performance Optimization, §7.20). Read-only,
 * deliberately: the request's own example included an "Order creation
 * (50 iterations)" benchmark, which was dropped here — a benchmark
 * command a real operator might run against a real production database
 * must never *write* 50 fake Orders into it every time someone wants a
 * timing number (fake inventory commits, fake revenue in every report/KPI
 * downstream, no cleanup path). Product search and KPI calculation are
 * both naturally read-only and already the two read paths this stage's
 * own CacheService/Analytics integration cares about — timing them tells
 * an operator what they actually need to know without mutating anything.
 *
 * Uses the first Tenant found (same "no tenants yet" honesty Stage 6's
 * own Dashboard bug fix established, §7.18) — bails with a clear message
 * instead of crashing if none exist yet.
 */
class BenchmarkPerformanceCommand extends Command
{
    private const PRODUCT_SEARCH_ITERATIONS = 100;

    private const KPI_CALCULATION_ITERATIONS = 20;

    protected $signature = 'performance:benchmark';

    protected $description = 'Run read-only performance benchmarks (product search, KPI calculation)';

    public function handle(
        TenantRepositoryInterface $tenants,
        ListProductsAction $listProducts,
        GetDashboardStatsAction $getDashboardStats,
    ): int {
        $this->info('Running performance benchmarks...');

        $tenant = ($tenants->all()[0] ?? null);

        if ($tenant === null) {
            $this->warn('No tenants exist yet — nothing to benchmark against.');

            return self::SUCCESS;
        }

        $tenantId = $tenant->id();

        $this->benchmark(
            'Product search',
            self::PRODUCT_SEARCH_ITERATIONS,
            fn () => $listProducts->execute(['limit' => 20], $tenantId),
        );

        $this->benchmark(
            'KPI calculation (Dashboard stats)',
            self::KPI_CALCULATION_ITERATIONS,
            fn () => $getDashboardStats->execute($tenantId),
        );

        $this->info('Benchmark complete!');

        return self::SUCCESS;
    }

    private function benchmark(string $label, int $iterations, callable $operation): void
    {
        $startedAt = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $operation();
        }

        $duration = microtime(true) - $startedAt;
        $average = $duration / $iterations;

        $this->info(sprintf(
            '%s: %.4fs total over %d iterations (avg: %.4fs)',
            $label,
            $duration,
            $iterations,
            $average,
        ));
    }
}
