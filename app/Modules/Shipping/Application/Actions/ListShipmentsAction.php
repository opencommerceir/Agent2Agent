<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ShipmentData;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;

final class ListShipmentsAction
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
    ) {
    }

    /**
     * @param array{status?: string, order_id?: int, limit?: int} $input
     * @return array{shipments: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $status = isset($input['status']) ? TrackingStatus::from($input['status']) : null;
        $orderId = isset($input['order_id']) ? (int) $input['order_id'] : null;
        $limit = isset($input['limit']) ? (int) $input['limit'] : self::DEFAULT_LIMIT;

        $shipments = $this->shipments->list($tenantId, $status, $orderId, $limit);

        return [
            'shipments' => array_map(fn ($shipment) => ShipmentData::fromEntity($shipment)->toArray(), $shipments),
        ];
    }
}
