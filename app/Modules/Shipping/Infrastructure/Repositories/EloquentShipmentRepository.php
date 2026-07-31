<?php

namespace App\Modules\Shipping\Infrastructure\Repositories;

use App\Modules\Shipping\Domain\Entities\Shipment as ShipmentEntity;
use App\Modules\Shipping\Domain\Entities\TrackingEvent as TrackingEventEntity;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;
use App\Modules\Shipping\Domain\ValueObjects\Weight;
use App\Modules\Shipping\Infrastructure\Models\Shipment as ShipmentModel;
use App\Modules\Shipping\Infrastructure\Models\TrackingEvent as TrackingEventModel;
use DateTimeImmutable;

class EloquentShipmentRepository implements ShipmentRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?ShipmentEntity
    {
        $model = ShipmentModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function trackingNumberExists(string $trackingNumber, int $tenantId): bool
    {
        return ShipmentModel::query()
            ->where('tenant_id', $tenantId)
            ->where('tracking_number', $trackingNumber)
            ->exists();
    }

    public function findByTrackingNumber(string $trackingNumber, int $tenantId): ?ShipmentEntity
    {
        $model = ShipmentModel::query()
            ->where('tenant_id', $tenantId)
            ->where('tracking_number', $trackingNumber)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function list(int $tenantId, ?TrackingStatus $status, ?int $orderId, int $limit): array
    {
        $builder = ShipmentModel::query()->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        if ($orderId !== null) {
            $builder->where('order_id', $orderId);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (ShipmentModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ShipmentEntity $shipment): ShipmentEntity
    {
        $model = $shipment->id()
            ? ShipmentModel::query()->where('tenant_id', $shipment->tenantId())->findOrFail($shipment->id())
            : new ShipmentModel();

        $model->tenant_id = $shipment->tenantId();
        $model->order_id = $shipment->orderId();
        $model->shipping_method_id = $shipment->shippingMethodId();
        $model->tracking_number = $shipment->trackingNumber()->value();
        $model->status = $shipment->status()->value;
        $model->weight_grams = $shipment->weight()->grams();
        $model->shipping_cost_amount = $shipment->shippingCost()->amount();
        $model->shipping_cost_currency = $shipment->shippingCost()->currency();
        $model->shipped_at = $shipment->shippedAt();
        $model->delivered_at = $shipment->deliveredAt();
        $model->provider_name = $shipment->providerName();
        $model->provider_tracking_number = $shipment->providerTrackingNumber();
        $model->save();

        return $this->toEntity($model);
    }

    public function saveTrackingEvent(TrackingEventEntity $event): TrackingEventEntity
    {
        $model = new TrackingEventModel();
        $model->shipment_id = $event->shipmentId();
        $model->status = $event->status()->value;
        $model->location = $event->location();
        $model->description = $event->description();
        $model->occurred_at = $event->occurredAt();
        $model->save();

        return $this->toTrackingEventEntity($model);
    }

    public function listTrackingEvents(int $shipmentId, int $tenantId): array
    {
        return TrackingEventModel::query()
            ->where('shipment_id', $shipmentId)
            ->whereHas('shipment', fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (TrackingEventModel $model) => $this->toTrackingEventEntity($model))
            ->all();
    }

    private function toEntity(ShipmentModel $model): ShipmentEntity
    {
        return new ShipmentEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            orderId: $model->order_id,
            shippingMethodId: $model->shipping_method_id,
            trackingNumber: new TrackingNumber($model->tracking_number),
            status: TrackingStatus::from($model->status),
            weight: new Weight($model->weight_grams),
            shippingCost: Money::fromAmount($model->shipping_cost_amount, $model->shipping_cost_currency),
            shippedAt: $model->shipped_at ? DateTimeImmutable::createFromInterface($model->shipped_at) : null,
            deliveredAt: $model->delivered_at ? DateTimeImmutable::createFromInterface($model->delivered_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            providerName: $model->provider_name,
            providerTrackingNumber: $model->provider_tracking_number,
        );
    }

    private function toTrackingEventEntity(TrackingEventModel $model): TrackingEventEntity
    {
        return new TrackingEventEntity(
            id: $model->id,
            shipmentId: $model->shipment_id,
            status: TrackingStatus::from($model->status),
            location: $model->location,
            description: $model->description,
            occurredAt: DateTimeImmutable::createFromInterface($model->occurred_at),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
