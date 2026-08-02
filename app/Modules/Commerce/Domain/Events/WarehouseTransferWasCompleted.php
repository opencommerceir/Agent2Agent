<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\WarehouseTransfer;

final class WarehouseTransferWasCompleted
{
    public function __construct(
        public readonly WarehouseTransfer $transfer,
    ) {
    }
}
