<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Interfaces\Http\Requests\BusinessLoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Mirrors App\Core\Interfaces\HTTP\Controllers\Auth\LoginController, one
 * guard down. Uses Auth::guard('business')->attempt() directly rather
 * than a dedicated Action — BusinessOwner is a plain Authenticatable
 * credential with no Domain entity/business rules behind it (unlike
 * Core's own User), so there is no Application-layer step to delegate to.
 */
class BusinessLoginController extends Controller
{
    public function create(): View
    {
        return view('nexus::business.login');
    }

    public function store(BusinessLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('business')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => t('messages.nexus.business.login.invalid_credentials')]);
        }

        $request->session()->regenerate();

        // Phase 7/M3 — a team member invited with a temporary password
        // (InviteTeamMemberAction) is routed here before anywhere else,
        // regardless of `intended()`; a fresh registrant's Owner row never
        // has this flag set, so this is a no-op for the pre-Phase-7 flow.
        if (Auth::guard('business')->user()->must_change_password) {
            return redirect()->route('nexus.business.password.force-change');
        }

        return redirect()->intended(route('nexus.business.dashboard'));
    }
}
