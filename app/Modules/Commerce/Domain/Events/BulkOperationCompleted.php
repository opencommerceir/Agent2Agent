<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\BulkOperation;

/**
 * Dispatched for both a fully-Completed and a Partial outcome — the
 * entity's own `status()` tells a Listener which one happened. There is
 * no separate "BulkOperationPartiallyCompleted" event; nothing in this
 * codebase splits a single terminal-state event into several just to
 * mirror an enum's own case count (see `TrackingEventWasAdded`'s own
 * single-event-many-statuses precedent).
 */
final class BulkOperationCompleted
{
    public function __construct(
        public readonly BulkOperation $operation,
    ) {
    }
}
