<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Capability;

final class CapabilityWasUpdated
{
    public function __construct(
        public readonly Capability $capability,
    ) {
    }
}
