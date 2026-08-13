<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers\Concerns;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by every controller that can complete a Business-guard login
 * (password, OAuth — Phase 7/M7's MFA challenge will call this too, after
 * the code is verified). Centralizes a verified real Laravel gotcha:
 * `Illuminate\Session\DatabaseSessionHandler::userId()` reads
 * `$container->make(Guard::class)`, and `Guard::class` is container-aliased
 * to the `'auth.driver'` *singleton*, which resolves to
 * `$app['auth']->guard()` — the app's *default* guard (`web`) — the very
 * first time anything asks for it in the request, then stays cached for
 * the rest of the request regardless of which guard actually authenticates
 * afterward. Since the `business` guard is never the app default, every
 * business-guard login would otherwise persist `sessions.user_id = null`
 * (confirmed against the real vendor source, not assumed) — rebinding that
 * singleton instance to the now-authenticated business guard, right after
 * login, is the actual fix: DatabaseSessionHandler::write() (called later,
 * at request terminate) then resolves the correct id. A manual
 * `UPDATE sessions SET user_id = ...` right after login looks like it
 * would work but does not — that same terminate-time write() overwrites it
 * right back to null.
 */
trait FinishesBusinessLogin
{
    protected function finishBusinessLogin(BusinessOwner $owner, Request $request, bool $remember = false): void
    {
        Auth::guard('business')->login($owner, $remember);
        $request->session()->regenerate();

        App::instance(Guard::class, Auth::guard('business'));
    }
}
