<?php

namespace App\Domains\Nexus\Marketplace\Interfaces\Http\Controllers;

use App\Domains\Nexus\Marketplace\Application\Actions\GetBusinessNetworkAction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-facing Network Visualization page — the human-operated
 * counterpart to the nexus.marketplace.network MCP capability. Same
 * 'business.auth' guard shape every other Nexus business page uses.
 */
class NetworkController extends Controller
{
    public function __construct(
        private readonly GetBusinessNetworkAction $getBusinessNetwork,
    ) {
    }

    public function index(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::marketplace.network.index', [
            'network' => $this->getBusinessNetwork->execute($businessId),
            'businessId' => $businessId,
        ]);
    }
}
