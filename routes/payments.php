<?php

use App\Modules\Commerce\Infrastructure\Controllers\PaymentCallbackController;
use App\Modules\Commerce\Infrastructure\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Real Payment Gateway Routes (§7.37)
|--------------------------------------------------------------------------
|
| Loaded by CommerceServiceProvider::boot() via loadRoutesFrom(), the
| same "a module owns and loads its own routes" shape routes/mcp.php/
| routes/agents.php already use — independent of bootstrap/app.php's
| web/api split, so these carry no CSRF/session middleware (an external
| gateway calling back has neither).
|
| /payments/{gateway}/callback is deliberately ONE shared route for
| every registered gateway, not one route per gateway — it's the
| concrete mechanism that makes adding a new gateway never require a new
| route: `InitiatePaymentAction` always hands every gateway's own
| initiate() call this same URL (with ?session={id} attached) as the
| $callbackUrl. Only Stripe additionally gets its own dedicated webhook
| route below, since it's the one gateway here with a real, separate
| async signal beyond the browser redirect — a future gateway that also
| needs one adds its own the same way.
|
*/

Route::get('/payments/{gateway}/callback', [PaymentCallbackController::class, 'show'])->name('payments.callback');

Route::post('/payments/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('payments.stripe.webhook');
