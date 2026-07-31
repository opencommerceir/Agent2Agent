<?php

namespace App\Modules\Shipping\Domain\Events;

use App\Modules\Shipping\Domain\Entities\TrackingEvent;

final class TrackingEventWasAdded
{
    public function __construct(
        public readonly TrackingEvent $event,
    ) {
    }
}
