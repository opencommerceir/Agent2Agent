<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\Actions\CancelOrderAction;
use App\Modules\Commerce\Application\Actions\GetOrderAction;
use App\Modules\Commerce\Application\Actions\ListOrdersAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reuses Commerce's own ListOrdersAction/GetOrderAction/CancelOrderAction
 * directly — the same Actions commerce.order.list/.get's MCP handlers
 * call (CancelOrderAction itself is deliberately never wired to MCP,
 * HANDOFF §6, but is exactly as safe to call directly here).
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly ListOrdersAction $listOrders,
        private readonly GetOrderAction $getOrder,
        private readonly CancelOrderAction $cancelOrder,
    ) {
    }

    public function index(Request $request): View
    {
        $tenants = $this->tenants->all();
        $tenantId = $request->integer('tenant_id') ?: (($tenants[0] ?? null)?->id());
        $status = $request->string('status')->toString() ?: null;

        $orders = $tenantId !== null
            ? $this->listOrders->execute(array_filter(['status' => $status]), $tenantId)['orders']
            : [];

        return view('dashboard.orders.index', [
            'orders' => $orders,
            'tenants' => $tenants,
            'selectedTenantId' => $tenantId,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, int $orderId): View
    {
        $order = $this->getOrder->execute($orderId, (int) $request->integer('tenant_id'));

        return view('dashboard.orders.show', ['order' => $order]);
    }

    public function cancel(Request $request, int $orderId): RedirectResponse
    {
        $tenantId = (int) $request->integer('tenant_id');

        $this->cancelOrder->execute($orderId, $tenantId);

        return redirect()->route('dashboard.orders.index', ['tenant_id' => $tenantId])->with('status', t('messages.orders.cancelled'));
    }
}
