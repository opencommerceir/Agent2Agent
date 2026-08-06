<?php

namespace App\Http\Controllers\Showcase;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\Actions\GetDashboardStatsAction;
use App\Modules\Commerce\Application\Actions\ListOrdersAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\View\View;

/**
 * The "live panel beside the chat" surface (Phase 2, Showcase prep,
 * §7.33) — three read-only tabs (products/orders/kpis) that reuse the
 * *exact same* Actions the Admin Dashboard's own
 * `ProductController`/`OrderController`/`DashboardController` already
 * call (`ListProductsAction`/`ListOrdersAction`/`GetDashboardStatsAction`),
 * never a second, parallel read implementation (HANDOFF §3 pattern #19).
 * The only real difference from the Dashboard's own equivalents: no
 * `?tenant_id=` multi-tenant selector — this panel is permanently scoped
 * to the one seeded `demo-showcase` Tenant, the same fixed-Tenant shape
 * `ShowcaseController` itself already establishes.
 *
 * Each method returns a rendered Blade partial (HTML), not JSON — the
 * Alpine panel injects the response body directly, so formatting (money,
 * status badges) lives once, server-side, in the partial itself, not
 * duplicated in client-side JS templating.
 */
class ShowcasePanelController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function products(ListProductsAction $action): View
    {
        $tenantId = $this->demoTenantId();
        $products = $tenantId !== null ? $action->execute([], $tenantId)['products'] : [];

        return view('showcase.partials.products', ['products' => $products]);
    }

    public function orders(ListOrdersAction $action): View
    {
        $tenantId = $this->demoTenantId();
        $orders = $tenantId !== null ? $action->execute(['limit' => 10], $tenantId)['orders'] : [];

        return view('showcase.partials.orders', ['orders' => $orders]);
    }

    public function kpis(GetDashboardStatsAction $action): View
    {
        $tenantId = $this->demoTenantId();
        $stats = $tenantId !== null ? $action->execute($tenantId)->toArray() : null;

        return view('showcase.partials.kpis', ['stats' => $stats]);
    }

    private function demoTenantId(): ?int
    {
        return $this->tenants->findBySlug(DemoShowcaseSeeder::TENANT_SLUG)?->id();
    }
}
