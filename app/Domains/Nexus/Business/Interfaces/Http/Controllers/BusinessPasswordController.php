<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Application\Actions\CompleteForcedPasswordChangeAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The forced "change your temporary password" step InviteTeamMemberAction
 * (Phase 7/M3) requires before a freshly-invited team member reaches the
 * dashboard. Reachable whether or not `must_change_password` is actually
 * set — a team member may also want to change their password voluntarily
 * later — but BusinessLoginController only ever redirects here when it is.
 */
class BusinessPasswordController extends Controller
{
    public function __construct(
        private readonly CompleteForcedPasswordChangeAction $completeForcedPasswordChange,
    ) {
    }

    public function edit(): View
    {
        return view('nexus::business.password.force-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->completeForcedPasswordChange->execute(Auth::guard('business')->id(), $validated['password']);

        return redirect()->route('nexus.business.dashboard')->with('status', t('messages.nexus.business.password.changed'));
    }
}
