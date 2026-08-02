<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\BulkOperation;

final class BulkOperationStarted
{
    public function __construct(
        public readonly BulkOperation $operation,
    ) {
    }
}
