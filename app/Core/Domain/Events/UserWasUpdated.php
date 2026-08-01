<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\User;

/**
 * Dispatched after an existing User's name/email/role/status has been
 * changed and saved.
 */
final class UserWasUpdated
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
