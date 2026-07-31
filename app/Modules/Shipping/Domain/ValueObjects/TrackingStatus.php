<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

/**
 * A Shipment's current state. `Exception` means "something went wrong
 * in transit" (a carrier-reported problem), not a PHP exception — it is
 * recoverable (Shipment::changeStatus()'s own transition map allows
 * Exception -> InTransit), unlike the two real terminal states
 * (Delivered/Returned).
 */
enum TrackingStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Exception = 'exception';
}
