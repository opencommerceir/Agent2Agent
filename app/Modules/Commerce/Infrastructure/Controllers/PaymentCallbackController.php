<?php

namespace App\Modules\Commerce\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\Actions\ConfirmRedirectPaymentAction;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The shared, gateway-agnostic browser-redirect landing page
 * (`GET /payments/{gateway}/callback`, §7.37) — every registered
 * `RedirectPaymentGatewayInterface` implementation's own `initiate()`
 * call receives this same route (with `?session={id}` already attached)
 * as its `$callbackUrl`, so adding a new gateway never needs a new
 * route or a new Controller.
 *
 * For gateways whose own flow has no separate async webhook (Zibal: this
 * *is* the only confirmation signal Zibal ever sends), this is the real
 * trigger. For gateways that also send an authoritative webhook
 * (Stripe), this route still safely calls the identical, idempotent
 * `ConfirmRedirectPaymentAction` — whichever of the two arrives first
 * actually finalizes the charge, the other becomes a no-op confirming
 * what already happened.
 *
 * No route middleware, no CSRF, no session — this route is loaded via
 * `CommerceServiceProvider::boot()`'s own `loadRoutesFrom()`, outside
 * `bootstrap/app.php`'s `web` group entirely, the identical mechanism
 * `routes/mcp.php`/`routes/agents.php` already use. Not covered by
 * `MCPExceptionHandler` (scoped to `mcp/*`/`api/agents/*` only) — every
 * exception is caught explicitly here instead, since an external
 * gateway's own browser redirect should always land on a real page, never
 * a raw Laravel error screen.
 */
final class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly ConfirmRedirectPaymentAction $confirm,
    ) {
    }

    public function show(Request $request, string $gateway): View
    {
        $sessionId = (int) $request->query('session', 0);

        if ($sessionId <= 0) {
            return view('payments.failed', ['message' => 'Missing or invalid payment session reference.']);
        }

        try {
            $result = $this->confirm->execute($sessionId);
        } catch (Throwable $e) {
            return view('payments.failed', ['message' => $e->getMessage()]);
        }

        return $result['successful']
            ? view('payments.confirmed', ['order' => $result['order']])
            : view('payments.failed', ['message' => $result['message']]);
    }
}
