<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessDashboardAction;
use App\Domains\Nexus\Business\Application\Actions\SubmitSuspensionAppealAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BusinessDashboardController extends Controller
{
    public function __construct(
        private readonly GetBusinessDashboardAction $getBusinessDashboard,
        private readonly SubmitSuspensionAppealAction $submitSuspensionAppeal,
    ) {
    }

    public function index(): View
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return view('nexus::business.dashboard', [
            'owner' => $owner,
            ...$this->getBusinessDashboard->execute($owner->business_id),
        ]);
    }

    public function submitSuspensionAppeal(Request $request): RedirectResponse
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        $this->submitSuspensionAppeal->execute($owner->business_id, $request->string('message')->toString());

        return redirect()->route('nexus.business.dashboard')->with('status', t('messages.nexus.business.dashboard.appeal_submitted'));
    }
}
