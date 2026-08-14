<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Home page. Used to render Commerce KPI cards/charts (Phase 4 Stage 6)
 * via `GetDashboardStatsAction`/`ChartDataProvider`, but `AnalyticsServiceProvider`
 * (and Commerce, which those KPIs were built for) has been disabled since
 * Nexus Phase 0 — superseded by `app/Domains/Nexus/Analytics` — so
 * `KPIRepositoryInterface` was never bound and every request here threw a
 * `BindingResolutionException`. Redirects to the equivalent real Nexus
 * page instead of rendering a page built for a retired module.
 */
class DashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('dashboard.nexus.revenue.index');
    }
}
