<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\BulkOperation;

/**
 * Dispatched only for a whole-operation Failed outcome (via
 * `BulkOperation::fail()`, or `complete()` when zero rows succeeded) —
 * never for a Partial outcome, which dispatches `BulkOperationCompleted`
 * instead (its own status is `partial`, still a "the run finished, here's
 * what happened" event, not a failure one).
 */
final class BulkOperationFailed
{
    public function __construct(
        public readonly BulkOperation $operation,
    ) {
    }
}
