<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Domain\Exceptions\PaymentNotFoundException;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;

final class GetPaymentAction
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
    ) {
    }

    public function execute(int $id, int $tenantId): PaymentData
    {
        $payment = $this->payments->findById($id, $tenantId);

        if (! $payment) {
            throw new PaymentNotFoundException("Payment [{$id}] does not exist.");
        }

        return PaymentData::fromEntity($payment);
    }
}
