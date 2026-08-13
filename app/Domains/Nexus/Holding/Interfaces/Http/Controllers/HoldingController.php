<?php

namespace App\Domains\Nexus\Holding\Interfaces\Http\Controllers;

use App\Domains\Nexus\Analytics\Application\Actions\GetHoldingDashboardAction;
use App\Domains\Nexus\Holding\Application\Actions\AcceptSubsidiaryInvitationAction;
use App\Domains\Nexus\Holding\Application\Actions\CreateHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\GetHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\GetMyHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\InviteSubsidiaryAction;
use App\Domains\Nexus\Holding\Application\Actions\LeaveHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\ListHoldingInvitationsForBusinessAction;
use App\Domains\Nexus\Holding\Application\Actions\RejectSubsidiaryInvitationAction;
use App\Domains\Nexus\Holding\Application\Actions\RemoveSubsidiaryAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-facing Multi-Business Account portal — portal-only, no MCP
 * capability (a Holding is a human administrative structure, same
 * "Admin/Margin Settings-shaped" reasoning Margin/LLM Settings already
 * follow: an Agent never needs to create or manage one).
 */
class HoldingController extends Controller
{
    public function __construct(
        private readonly GetMyHoldingAction $getMyHolding,
        private readonly GetHoldingAction $getHolding,
        private readonly GetHoldingDashboardAction $getHoldingDashboard,
        private readonly ListHoldingInvitationsForBusinessAction $listInvitations,
        private readonly CreateHoldingAction $createHolding,
        private readonly InviteSubsidiaryAction $inviteSubsidiary,
        private readonly AcceptSubsidiaryInvitationAction $acceptInvitation,
        private readonly RejectSubsidiaryInvitationAction $rejectInvitation,
        private readonly RemoveSubsidiaryAction $removeSubsidiary,
        private readonly LeaveHoldingAction $leaveHolding,
    ) {
    }

    public function index(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::holding.index', [
            'holding' => $this->getMyHolding->execute($businessId),
            'invitations' => $this->listInvitations->execute($businessId),
        ]);
    }

    public function create(): View
    {
        return view('nexus::holding.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $validated = $request->validate([
            'name_fa' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
        ]);

        $holding = $this->createHolding->execute($businessId, $validated['name_fa'], $validated['name_en']);

        return redirect()->route('nexus.holding.show', $holding->id);
    }

    public function show(int $holding): View
    {
        return view('nexus::holding.show', [
            'holding' => $this->getHolding->execute($holding),
            'dashboard' => $this->getHoldingDashboard->execute($holding),
            'businessId' => Auth::guard('business')->user()->business_id,
        ]);
    }

    public function invite(Request $request, int $holding): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $validated = $request->validate([
            'target_business_id' => ['required', 'integer'],
        ]);

        $this->inviteSubsidiary->execute($holding, $businessId, (int) $validated['target_business_id']);

        return redirect()->route('nexus.holding.show', $holding);
    }

    public function accept(int $subsidiary): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $this->acceptInvitation->execute($subsidiary, $businessId);

        return redirect()->route('nexus.holding.index');
    }

    public function reject(int $subsidiary): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $this->rejectInvitation->execute($subsidiary, $businessId);

        return redirect()->route('nexus.holding.index');
    }

    public function remove(int $holding, int $subsidiary): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $this->removeSubsidiary->execute($subsidiary, $businessId);

        return redirect()->route('nexus.holding.show', $holding);
    }

    public function leave(int $subsidiary): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $this->leaveHolding->execute($subsidiary, $businessId);

        return redirect()->route('nexus.holding.index');
    }
}
