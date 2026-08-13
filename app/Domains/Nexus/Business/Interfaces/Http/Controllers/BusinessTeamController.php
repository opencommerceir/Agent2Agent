<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Application\Actions\ChangeTeamMemberRoleAction;
use App\Domains\Nexus\Business\Application\Actions\InviteTeamMemberAction;
use App\Domains\Nexus\Business\Application\Actions\ListTeamMembersAction;
use App\Domains\Nexus\Business\Application\Actions\RemoveTeamMemberAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BusinessTeamController extends Controller
{
    public function __construct(
        private readonly ListTeamMembersAction $listTeamMembers,
        private readonly InviteTeamMemberAction $inviteTeamMember,
        private readonly ChangeTeamMemberRoleAction $changeTeamMemberRole,
        private readonly RemoveTeamMemberAction $removeTeamMember,
    ) {
    }

    public function index(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::business.team.index', [
            'members' => $this->listTeamMembers->execute($businessId),
            'roles' => TeamMemberRole::cases(),
            'callingOwnerId' => Auth::guard('business')->id(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;
        $callingOwnerId = Auth::guard('business')->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string'],
        ]);

        $this->inviteTeamMember->execute(
            $businessId,
            $callingOwnerId,
            $validated['name'],
            $validated['email'],
            TeamMemberRole::from($validated['role']),
        );

        return redirect()->route('nexus.business.team.index');
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;
        $callingOwnerId = Auth::guard('business')->id();

        $validated = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $this->changeTeamMemberRole->execute($businessId, $callingOwnerId, $member, TeamMemberRole::from($validated['role']));

        return redirect()->route('nexus.business.team.index');
    }

    public function destroy(int $member): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;
        $callingOwnerId = Auth::guard('business')->id();

        $this->removeTeamMember->execute($businessId, $callingOwnerId, $member);

        return redirect()->route('nexus.business.team.index');
    }
}
