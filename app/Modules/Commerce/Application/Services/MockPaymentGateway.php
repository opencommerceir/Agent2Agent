<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;

/**
 * The only PaymentGatewayInterface implementation that exists (no real
 * Stripe/PayPal integration yet — same "deferred, needs live credentials
 * to test against honestly" reasoning HANDOFF gave for Commerce's real
 * Connectors). Always succeeds unless the caller explicitly opts into
 * failure via `payment_details['simulate_failure'] = true` — a
 * deliberate, documented test-triggering convention (the same idea real
 * gateways' test-mode "magic failing card numbers" use), so
 * ProcessPaymentAction's decline path is actually exercisable in tests
 * without needing real network mocking.
 */
final class MockPaymentGateway implements PaymentGatewayInterface
{
    public function charge(Money $amount, PaymentMethod $method, array $paymentDetails): PaymentGatewayResult
    {
        if ($paymentDetails['simulate_failure'] ?? false) {
            return new PaymentGatewayResult(
                successful: false,
                transactionId: null,
                rawResponse: ['error' => 'card_declined', 'message' => 'The mock gateway was told to simulate a decline.'],
            );
        }

        return new PaymentGatewayResult(
            successful: true,
            transactionId: 'mock_txn_'.bin2hex(random_bytes(8)),
            rawResponse: [
                'gateway' => 'mock',
                'amount' => $amount->amount(),
                'currency' => $amount->currency(),
                'method' => $method->value,
                'status' => 'succeeded',
            ],
        );
    }
}
