<?php

namespace App\Modules\Shipping\Domain\Events;

use App\Modules\Shipping\Domain\Entities\Shipment;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;

final class ShipmentStatusChanged
{
    public function __construct(
        public readonly Shipment $shipment,
        public readonly TrackingStatus $previousStatus,
    ) {
    }
}
