<?php

namespace App\Domains\Nexus\Growth\Interfaces\Http\Controllers;

use App\Domains\Nexus\Growth\Application\Actions\GetReferralStatusAction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-facing referral dashboard — same 'business.auth' guard shape
 * NegotiationViewerController already uses.
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly GetReferralStatusAction $getReferralStatus,
    ) {
    }

    public function index(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::growth.referrals.index', [
            'status' => $this->getReferralStatus->execute($businessId),
        ]);
    }
}
