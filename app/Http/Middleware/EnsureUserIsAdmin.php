<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Not named in this stage's own request, but its explicit Authorization
 * rule ("only a User with role admin may reach the Dashboard") implies
 * this — the missing-piece-the-request-implies pattern this codebase's
 * every prior stage has hit at least once (HANDOFF §3 pattern #12).
 * Applied to the whole `/dashboard/*` route group, after `auth` (so an
 * unauthenticated request hits the login redirect first, not a 403).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Core\Infrastructure\Models\User $user */
        $user = Auth::user();

        abort_unless($user->role === 'admin', 403);

        return $next($request);
    }
}
