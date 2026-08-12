<?php

namespace App\Domains\Nexus\Growth\Interfaces\Http\Controllers;

use App\Domains\Nexus\Growth\Application\Actions\ListSentInvitesAction;
use App\Domains\Nexus\Growth\Application\Actions\SendAgentInviteAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-facing "invite a lead" form — the human-operated counterpart to
 * the nexus.invite.send MCP capability an Agent can also call directly.
 * Same 'business.auth' guard shape ReferralController already uses.
 */
class InviteController extends Controller
{
    public function __construct(
        private readonly SendAgentInviteAction $sendInvite,
        private readonly ListSentInvitesAction $listSentInvites,
    ) {
    }

    public function index(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::growth.invites.index', [
            'invites' => $this->listSentInvites->execute($businessId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $validated = $request->validate([
            'invitee_name' => ['required', 'string', 'max:255'],
            'invitee_email' => ['required', 'email', 'max:255'],
        ]);

        $this->sendInvite->execute($businessId, $validated['invitee_name'], $validated['invitee_email']);

        return redirect()->route('nexus.growth.invites.index')->with('status', t('messages.nexus.growth.invites.sent'));
    }
}
