<?php

namespace App\Domains\Nexus\Credit\Interfaces\Http\Controllers;

use App\Domains\Nexus\Credit\Application\Actions\ConfirmCreditPurchaseAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The gateway-agnostic browser-redirect landing page
 * (`GET /nexus/credit/payments/{gateway}/callback`) — mirrors Commerce's
 * own PaymentCallbackController exactly (§ that class's own docblock):
 * no auth/session assumed, every exception caught explicitly so an
 * external gateway's own browser redirect always lands on a real page.
 */
final class CreditPurchaseCallbackController extends Controller
{
    public function __construct(
        private readonly ConfirmCreditPurchaseAction $confirm,
    ) {
    }

    public function show(Request $request, string $gateway): View
    {
        $sessionId = (int) $request->query('session', 0);

        if ($sessionId <= 0) {
            return view('nexus::business.credit-purchase-result', [
                'successful' => false,
                'message' => 'Missing or invalid purchase session reference.',
                'creditsGranted' => 0,
            ]);
        }

        try {
            $result = $this->confirm->execute($sessionId);
        } catch (Throwable $e) {
            $result = ['successful' => false, 'creditsGranted' => 0, 'message' => $e->getMessage()];
        }

        return view('nexus::business.credit-purchase-result', [
            'successful' => $result['successful'],
            'message' => $result['message'],
            'creditsGranted' => $result['creditsGranted'],
        ]);
    }
}
