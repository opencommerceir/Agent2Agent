<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Domain\Events\PaymentWasRefunded;
use App\Modules\Commerce\Domain\Exceptions\PaymentNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Payment::refund() itself guards "only a completed payment can be
 * refunded"; this Action layers the Order-side consequences on top:
 * Order::refund() (its own guard against double-refunding/refunding a
 * cancelled Order) and restoring each item's Inventory — the same
 * Inventory::restore() CancelOrderAction already uses, since both
 * "cancel" and "refund" mean the same thing to stock: the sale didn't
 * happen after all.
 */
final class RefundPaymentAction
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly OrderRepositoryInterface $orders,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    /**
     * $reason is accepted for the caller's own logging/messaging (the
     * `message` field commerce.payment.refund returns) — nothing in this
     * stage's schema persists a refund reason anywhere, so it is never
     * stored.
     */
    public function execute(int $paymentId, int $tenantId, ?string $reason = null): PaymentData
    {
        return DB::transaction(function () use ($paymentId, $tenantId) {
            $payment = $this->payments->findById($paymentId, $tenantId);

            if (! $payment) {
                throw new PaymentNotFoundException("Payment [{$paymentId}] does not exist.");
            }

            $payment->refund(); // throws InvalidArgumentException unless status is Completed

            $order = $this->orders->findById($payment->orderId(), $tenantId);

            if ($order) {
                $order->refund();

                foreach ($order->items() as $item) {
                    $inventory = $this->inventories->findByProduct($item->productId(), $tenantId);

                    if ($inventory) {
                        $inventory->restore($item->quantity());
                        $this->inventories->save($inventory);
                    }
                }

                $this->orders->save($order);
            }

            $payment = $this->payments->save($payment);

            Event::dispatch(new PaymentWasRefunded($payment));

            return PaymentData::fromEntity($payment);
        });
    }
}
