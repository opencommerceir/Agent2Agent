<?php

namespace App\Modules\Shipping\Application\DTOs;

use App\Modules\Shipping\Domain\Entities\Shipment;

final class ShipmentData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $orderId,
        public readonly int $shippingMethodId,
        public readonly string $trackingNumber,
        public readonly string $status,
        public readonly int $weightGrams,
        public readonly int $shippingCostAmount,
        public readonly string $shippingCostCurrency,
        public readonly ?string $shippedAt,
        public readonly ?string $deliveredAt,
    ) {
    }

    public static function fromEntity(Shipment $shipment): self
    {
        return new self(
            id: $shipment->id(),
            tenantId: $shipment->tenantId(),
            orderId: $shipment->orderId(),
            shippingMethodId: $shipment->shippingMethodId(),
            trackingNumber: $shipment->trackingNumber()->value(),
            status: $shipment->status()->value,
            weightGrams: $shipment->weight()->grams(),
            shippingCostAmount: $shipment->shippingCost()->amount(),
            shippingCostCurrency: $shipment->shippingCost()->currency(),
            shippedAt: $shipment->shippedAt()?->format(DATE_ATOM),
            deliveredAt: $shipment->deliveredAt()?->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'orderId' => $this->orderId,
            'shippingMethodId' => $this->shippingMethodId,
            'trackingNumber' => $this->trackingNumber,
            'status' => $this->status,
            'weightGrams' => $this->weightGrams,
            'shippingCostAmount' => $this->shippingCostAmount,
            'shippingCostCurrency' => $this->shippingCostCurrency,
            'shippedAt' => $this->shippedAt,
            'deliveredAt' => $this->deliveredAt,
        ];
    }
}
