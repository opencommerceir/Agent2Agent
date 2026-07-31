<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Commerce\Domain\Entities\OrderItem;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money as CommerceMoney;
use App\Modules\Shipping\Application\DTOs\ShipmentData;
use App\Modules\Shipping\Domain\Entities\Shipment;
use App\Modules\Shipping\Domain\Events\ShipmentWasCreated;
use App\Modules\Shipping\Domain\Exceptions\OrderNotFoundException;
use App\Modules\Shipping\Domain\Exceptions\ShippingMethodNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;
use App\Modules\Shipping\Domain\Services\ShippingRateCalculator;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use App\Modules\Shipping\Domain\ValueObjects\Weight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * One Action = one business operation: fulfill an already-placed
 * Commerce Order with a real Shipment (rule §d.4's exact 6-step flow —
 * fetch Order, fetch ShippingMethod, weigh, price, create, generate a
 * Tracking Number, then write the assignment back onto the Order).
 *
 * Depends on Commerce's `OrderRepositoryInterface`/`ProductRepositoryInterface`
 * — Interfaces, never Commerce's Infrastructure/Model classes — the
 * same Dependency Inversion direction CRM/Finance/Loyalty already
 * established. Throws Shipping's *own* `OrderNotFoundException` for a
 * missing/cross-tenant order_id, never Commerce's concrete one (same
 * reasoning as CRM's own CustomerNotFoundException docblock).
 *
 * **Weight comes from each Product's `attributes['weight_grams']`, not a
 * first-class Product field** — Commerce's `Product` entity has no
 * dedicated Weight concept of its own (only the free-form `attributes`
 * bag Phase 1 already established for exactly this kind of ad-hoc,
 * module-specific data), and adding one would mean modifying Commerce's
 * Domain Entity/migration for a concern Commerce itself has no use for.
 * A Product with no `weight_grams` attribute set contributes 0 grams —
 * not an error, since plenty of legitimate Products (a Product created
 * before Shipping existed, a digital good) may never need one.
 *
 * The whole operation is one DB transaction: the Shipment and the
 * Order's own shipping fields change together or not at all — mirrors
 * PlaceOrderAction's own transaction boundary.
 */
final class CreateShipmentAction
{
    private const MAX_TRACKING_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly ShippingMethodRepositoryInterface $shippingMethods,
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly ShippingRateCalculator $calculator,
    ) {
    }

    public function execute(int $tenantId, int $orderId, int $shippingMethodId): ShipmentData
    {
        return DB::transaction(function () use ($tenantId, $orderId, $shippingMethodId) {
            $order = $this->orders->findById($orderId, $tenantId);

            if (! $order) {
                throw new OrderNotFoundException("Order [{$orderId}] does not exist.");
            }

            $method = $this->shippingMethods->findById($shippingMethodId, $tenantId);

            if (! $method) {
                throw new ShippingMethodNotFoundException("ShippingMethod [{$shippingMethodId}] does not exist.");
            }

            $weight = $this->calculateOrderWeight($order->items(), $tenantId);

            $rate = $this->calculator->calculate(
                $method->baseRate(),
                $method->ratePerKg(),
                $weight,
                $method->estimatedDaysMin(),
                $method->estimatedDaysMax(),
            );

            $trackingNumber = $this->generateUniqueTrackingNumber($tenantId);

            $shipment = Shipment::create($tenantId, $orderId, $shippingMethodId, $trackingNumber, $weight, $rate->cost());
            $shipment = $this->shipments->save($shipment);

            Event::dispatch(new ShipmentWasCreated($shipment));

            $order->assignShipping(
                $shippingMethodId,
                $shipment->id(),
                CommerceMoney::fromAmount($rate->cost()->amount(), $rate->cost()->currency()),
            );
            $this->orders->save($order);

            return ShipmentData::fromEntity($shipment);
        });
    }

    /**
     * @param list<OrderItem> $items
     */
    private function calculateOrderWeight(array $items, int $tenantId): Weight
    {
        $totalGrams = 0;

        foreach ($items as $item) {
            $product = $this->products->findById($item->productId(), $tenantId);
            $weightPerUnit = (int) ($product?->attributes()['weight_grams'] ?? 0);
            $totalGrams += $weightPerUnit * $item->quantity()->value();
        }

        return new Weight($totalGrams);
    }

    private function generateUniqueTrackingNumber(int $tenantId): TrackingNumber
    {
        for ($attempt = 0; $attempt < self::MAX_TRACKING_NUMBER_ATTEMPTS; $attempt++) {
            $trackingNumber = TrackingNumber::generate();

            if (! $this->shipments->trackingNumberExists($trackingNumber->value(), $tenantId)) {
                return $trackingNumber;
            }
        }

        throw new RuntimeException('Could not generate a unique tracking number after several attempts.');
    }
}
