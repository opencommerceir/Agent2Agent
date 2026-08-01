<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\Actions\GetDashboardStatsAction;
use App\Modules\Analytics\Application\Services\ChartDataProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Home page — Phase 4 Stage 6 replaces the previous platform-wide totals
 * (Total Tenants/Agents/Orders/Notifications summed across every tenant)
 * with the same per-tenant, `?tenant_id=`-selected view every other data
 * page (Products/Orders/Notifications) already uses, since every one of
 * the 6 KPI cards is inherently tenant-scoped (HANDOFF §7.18 — Revenue/
 * AOV/Conversion Rate can't be meaningfully summed across tenants with
 * potentially different currencies). `GetDashboardStatsAction`/
 * `ChartDataProvider` do all the real work — this Controller only
 * resolves which tenant is selected and hands their output to the View.
 */
class DashboardController extends Controller
{
    private const REVENUE_CHART_DAYS = 30;

    private const ORDERS_CHART_DAYS = 7;

    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly GetDashboardStatsAction $getDashboardStats,
        private readonly ChartDataProvider $chartData,
    ) {
    }

    public function index(Request $request): View
    {
        $tenants = $this->tenants->all();
        $tenantId = $request->integer('tenant_id') ?: (($tenants[0] ?? null)?->id());

        return view('dashboard.index', [
            'tenants' => $tenants,
            'selectedTenantId' => $tenantId,
            'stats' => $tenantId !== null ? $this->getDashboardStats->execute($tenantId) : null,
            'revenueChart' => $tenantId !== null ? $this->chartData->revenueChart($tenantId, self::REVENUE_CHART_DAYS) : null,
            'ordersChart' => $tenantId !== null ? $this->chartData->ordersChart($tenantId, self::ORDERS_CHART_DAYS) : null,
        ]);
    }
}
