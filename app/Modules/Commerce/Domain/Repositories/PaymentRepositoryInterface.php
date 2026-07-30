<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Payment;

interface PaymentRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Payment;

    public function findByOrderId(int $orderId, int $tenantId): ?Payment;

    public function save(Payment $payment): Payment;
}
