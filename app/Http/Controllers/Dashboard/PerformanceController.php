<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Application\Actions\OptimizeQueriesAction;
use App\Core\Application\Services\PerformanceMonitor;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `/dashboard/performance` — Phase 4 Stage 8 (Performance Optimization,
 * §7.20). Unlike every other Dashboard resource page, this one is not
 * tenant-scoped: PerformanceMonitor's own metrics (average response time,
 * cache hit rate, slow queries) are platform-operational data, not a
 * single Tenant's business data, so there's no `?tenant_id=` selector
 * here (the same reasoning `docs/api-reference.md`'s own MCP capabilities
 * never accept one either — this page just isn't asking a Tenant-scoped
 * question at all).
 */
class PerformanceController extends Controller
{
    public function __construct(
        private readonly PerformanceMonitor $monitor,
        private readonly OptimizeQueriesAction $optimizeQueries,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.performance.index', [
            'averageResponseTime' => $this->monitor->getAverageResponseTime(),
            'cacheHitRate' => $this->monitor->getCacheHitRate(),
            'requestCount' => $this->monitor->getRequestCount(),
            'slowQueries' => $this->monitor->getSlowQueries(10),
            'memoryUsageMb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            // Actually-open PDO connections in this process, not the
            // number of *configured* connections in config/database.php —
            // the latter (sqlite/mysql/mariadb/pgsql/sqlsrv, all always
            // present) would be a meaningless constant, not a real metric.
            'databaseConnections' => count(DB::getConnections()),
            'optimizationSuggestions' => $this->optimizeQueries->execute()['suggestions'],
        ]);
    }
}
