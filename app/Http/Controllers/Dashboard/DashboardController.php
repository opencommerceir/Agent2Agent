<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use Illuminate\View\View;

/**
 * Home page: aggregate stats + quick links only — every number comes from
 * an existing Repository's own `all()`/`listByTenant()`/`list()`, summed
 * here (a presentation-layer count, not a business decision) rather than
 * a new aggregate query method, since this is a small, admin-only,
 * infrequently-loaded page (Dashboard Controllers Rule: no business logic
 * in Controllers — only reading existing Repositories/Actions and handing
 * their output to a View).
 */
class DashboardController extends Controller
{
    private const MAX_PER_TENANT = 1000;

    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly AgentRepositoryInterface $agents,
        private readonly OrderRepositoryInterface $orders,
        private readonly NotificationRepositoryInterface $notifications,
    ) {
    }

    public function index(): View
    {
        $tenants = $this->tenants->all();

        $totalOrders = 0;
        $totalNotifications = 0;

        foreach ($tenants as $tenant) {
            $totalOrders += count($this->orders->listByTenant($tenant->id(), null, self::MAX_PER_TENANT));
            $totalNotifications += count($this->notifications->list($tenant->id(), null, null, self::MAX_PER_TENANT));
        }

        return view('dashboard.index', [
            'totalTenants' => count($tenants),
            'totalAgents' => count($this->agents->all()),
            'totalOrders' => $totalOrders,
            'totalNotifications' => $totalNotifications,
        ]);
    }
}
