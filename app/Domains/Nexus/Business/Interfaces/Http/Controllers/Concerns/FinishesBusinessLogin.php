<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers\Concerns;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\RedirectResponse;
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

    protected function requiresMfaChallenge(BusinessOwner $owner): bool
    {
        return $owner->mfa_enabled_at !== null;
    }

    /**
     * Password/OAuth credentials verified but MFA still owes a code —
     * stashes just enough to finish later (owner id + the "remember me"
     * the credential step already decided) and defers finishBusinessLogin()
     * until BusinessMfaChallengeController::verify() confirms a real code.
     */
    protected function startMfaChallenge(BusinessOwner $owner, Request $request, bool $remember = false): RedirectResponse
    {
        $request->session()->put('nexus.mfa.pending', [
            'owner_id' => $owner->id,
            'remember' => $remember,
        ]);

        return redirect()->route('nexus.business.mfa-challenge.show');
    }

    /**
     * The one post-login destination decision every entry point (password,
     * OAuth, MFA challenge) shares — kept here so `must_change_password`
     * only has one place to check.
     */
    protected function redirectAfterLogin(BusinessOwner $owner): RedirectResponse
    {
        if ($owner->must_change_password) {
            return redirect()->route('nexus.business.password.force-change');
        }

        return redirect()->intended(route('nexus.business.dashboard'));
    }
}
