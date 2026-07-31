<?php

namespace App\Modules\CRM\Domain\Events;

use App\Modules\CRM\Domain\Entities\CustomerNote;

/**
 * Domain event: a fact that already happened. Dispatched after a
 * CustomerNote has been persisted.
 */
final class NoteWasAddedToCustomer
{
    public function __construct(
        public readonly CustomerNote $note,
    ) {
    }
}
