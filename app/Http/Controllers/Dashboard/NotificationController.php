<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Modules\Notifications\Application\Actions\ListNotificationsAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Filters by type/status via ListNotificationsAction (the same Action
 * `notification.message.list`'s MCP handler calls). No `language` filter:
 * a sent Notification (Domain\Entities\Notification) doesn't carry a
 * language field at all — only NotificationTemplate does (Phase 4 Stage
 * 4, §7.16) — so there is nothing to filter sent Notifications by. Flagged
 * as a real gap in HANDOFF rather than inventing a column that doesn't
 * reflect anything the domain model actually tracks.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly ListNotificationsAction $listNotifications,
    ) {
    }

    public function index(Request $request): View
    {
        $tenants = $this->tenants->all();
        $tenantId = $request->integer('tenant_id') ?: (($tenants[0] ?? null)?->id());
        $type = $request->string('type')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;

        $notifications = $tenantId !== null
            ? $this->listNotifications->execute(array_filter(['type' => $type, 'status' => $status]), $tenantId)['notifications']
            : [];

        return view('dashboard.notifications.index', [
            'notifications' => $notifications,
            'tenants' => $tenants,
            'selectedTenantId' => $tenantId,
            'selectedType' => $type,
            'selectedStatus' => $status,
        ]);
    }
}
