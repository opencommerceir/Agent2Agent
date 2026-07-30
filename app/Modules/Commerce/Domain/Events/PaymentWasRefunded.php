<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Payment;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Payment has been refunded and its Order's Inventory
 * restored.
 */
final class PaymentWasRefunded
{
    public function __construct(
        public readonly Payment $payment,
    ) {
    }
}
