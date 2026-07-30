<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;

/**
 * The contract a real gateway (Stripe, PayPal, ...) would implement.
 * Deliberately returns a result object rather than throwing on a
 * declined charge — a decline is an expected, common outcome a gateway
 * reports, not an exceptional one; ProcessPaymentAction is the layer
 * that decides a failed PaymentGatewayResult becomes a thrown
 * PaymentFailedException.
 */
interface PaymentGatewayInterface
{
    /**
     * @param array<string, mixed> $paymentDetails
     */
    public function charge(Money $amount, PaymentMethod $method, array $paymentDetails): PaymentGatewayResult;
}
