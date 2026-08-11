<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessDashboardAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BusinessDashboardController extends Controller
{
    public function __construct(
        private readonly GetBusinessDashboardAction $getBusinessDashboard,
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
}
