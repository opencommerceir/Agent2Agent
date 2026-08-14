<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Agent\Application\Actions\SetAutoRespondAction;
use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessDashboardAction;
use App\Domains\Nexus\Business\Application\Actions\SetDataResidencyRegionAction;
use App\Domains\Nexus\Business\Application\Actions\SubmitSuspensionAppealAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\DataResidencyRegion;
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
        private readonly SetDataResidencyRegionAction $setDataResidencyRegion,
        private readonly SetAutoRespondAction $setAutoRespond,
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

    public function updateDataResidency(Request $request): RedirectResponse
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        $region = DataResidencyRegion::from($request->string('data_residency_region')->toString());
        $this->setDataResidencyRegion->execute($owner->business_id, $region);

        return redirect()->route('nexus.business.dashboard')->with('status', t('messages.nexus.business.dashboard.data_residency.updated'));
    }

    public function updateAutoRespond(Request $request): RedirectResponse
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        $this->setAutoRespond->execute($owner->business_id, $request->boolean('auto_respond'));

        return redirect()->route('nexus.business.dashboard')->with('status', t('messages.nexus.business.dashboard.auto_respond.updated'));
    }
}
