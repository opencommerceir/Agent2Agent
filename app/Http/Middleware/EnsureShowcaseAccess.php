<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `/showcase/*` gate (Showcase prep, Phase 3, §7.33) — a session
 * flag, not a real identity: completely independent of `auth`/`admin`
 * (Core's own `User`/`UserRole` system, §7.17) and of the Showcase demo
 * Agent's own bearer token (`ShowcaseController`'s own session key). This
 * middleware only ever answers "may this browser session view the
 * showcase at all," never "who is this" — there is no user record behind
 * it, just one shared passcode an operator hands out.
 *
 * `config('showcase.passcode')` blank (no `SHOWCASE_PASSCODE` in `.env`,
 * the default) disables the gate entirely — every request passes through
 * untouched, exactly how every existing Phase 1/2 Showcase test already
 * runs (they never set this env var). Only ever applied to the
 * `/showcase/*` route group, and deliberately excludes `/showcase/enter`
 * itself (routes/web.php), which must stay reachable to ever grant access
 * in the first place.
 */
class EnsureShowcaseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $passcode = config('showcase.passcode');

        if (blank($passcode)) {
            return $next($request);
        }

        if ($request->session()->get('showcase_access_granted') !== true) {
            return redirect()->route('showcase.enter');
        }

        return $next($request);
    }
}
