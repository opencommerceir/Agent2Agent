<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Payment;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a successful charge has been persisted as a Payment.
 */
final class PaymentWasProcessed
{
    public function __construct(
        public readonly Payment $payment,
    ) {
    }
}
