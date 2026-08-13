<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\ActiveSessionData;
use Illuminate\Support\Facades\DB;

/**
 * Reads `sessions.user_id` directly — the payoff of the login-time fix
 * (both BusinessLoginController and BusinessOauthController now set it
 * explicitly right after Auth::guard('business')->login(), correcting
 * Laravel's own default-guard resolution gap) is that this needs no
 * payload decoding, just a plain WHERE.
 */
final class ListMyActiveSessionsAction
{
    /**
     * @return list<ActiveSessionData>
     */
    public function execute(int $ownerId, string $currentSessionId): array
    {
        return DB::table('sessions')
            ->where('user_id', $ownerId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => new ActiveSessionData(
                id: $row->id,
                ipAddress: $row->ip_address,
                userAgent: $row->user_agent,
                lastActivity: $row->last_activity,
                isCurrent: $row->id === $currentSessionId,
            ))
            ->all();
    }
}
