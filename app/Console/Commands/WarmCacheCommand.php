<?php

namespace App\Console\Commands;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Modules\Analytics\Application\Actions\GetDashboardStatsAction;
use App\Modules\Commerce\Application\Actions\GetProductAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use Illuminate\Console\Command;

/**
 * Phase 4 Stage 8 (Performance Optimization, §7.20). The same
 * cross-tenant iteration shape every scheduled command in this codebase
 * already uses (GenerateAnalyticsSnapshotCommand/ExpireLoyaltyPointsCommand/
 * MarkAbandonedCartsCommand — TenantRepositoryInterface::all(), one pass
 * per Tenant).
 *
 * Warms two things, both by simply *calling* the real, already-cached
 * read path — never writing to the cache store directly, so there's only
 * one place (GetProductAction/CalculateKPIAction's own Cache::remember)
 * that decides a cache key's shape:
 * - Every active Product, via GetProductAction (the Action this stage's
 *   own CacheService integration lives on) — up to
 *   ListProductsAction::MAX_LIMIT (100) per tenant per call, that
 *   Action's own existing cap, not one this command invents.
 * - This Tenant's 6 headline KPIs, via GetDashboardStatsAction — already
 *   cached for 1 hour internally since Phase 4 Stage 6 (Cache::remember);
 *   calling it here just means the *first* real Dashboard/MCP request
 *   after a deploy hits a warm cache instead of a cold one.
 */
class WarmCacheCommand extends Command
{
    private const PRODUCTS_PER_TENANT = 100;

    protected $signature = 'cache:warm';

    protected $description = 'Warm the Product and KPI caches for every tenant';

    public function handle(
        TenantRepositoryInterface $tenants,
        ListProductsAction $listProducts,
        GetProductAction $getProduct,
        GetDashboardStatsAction $getDashboardStats,
    ): int {
        $this->info('Warming cache...');

        $productCount = 0;
        $tenantCount = 0;

        foreach ($tenants->all() as $tenant) {
            $tenantCount++;
            $tenantId = $tenant->id();

            $products = $listProducts->execute(['limit' => self::PRODUCTS_PER_TENANT], $tenantId);

            foreach ($products['products'] as $product) {
                $getProduct->execute($product['id'], $tenantId);
                $productCount++;
            }

            $getDashboardStats->execute($tenantId);
        }

        $this->info("Cache warmed! {$tenantCount} tenant(s), {$productCount} product(s).");

        return self::SUCCESS;
    }
}
