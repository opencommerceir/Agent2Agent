<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Only ever deletes a row that both matches the given session id AND
 * belongs to the calling owner — the WHERE user_id clause is the whole
 * authorization check, not a separate lookup-then-check step, so there is
 * no window where a caller can probe whether a session id they don't own
 * exists.
 */
final class RevokeSessionAction
{
    public function execute(string $sessionId, int $ownerId): void
    {
        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $ownerId)
            ->delete();

        if ($deleted === 0) {
            throw new InvalidArgumentException("Session [{$sessionId}] does not belong to this owner.");
        }
    }
}
