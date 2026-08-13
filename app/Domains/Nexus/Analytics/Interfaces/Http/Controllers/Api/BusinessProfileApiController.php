<?php

namespace App\Domains\Nexus\Analytics\Interfaces\Http\Controllers\Api;

use App\Domains\Nexus\Agent\Application\DTOs\AgentData;
use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessDashboardAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public REST API (Phase 9/M2) — `business.read` scope. Reuses the exact
 * same read-model GetBusinessDashboardAction (Phase 1/M6, extended
 * through Phase 6) already serves to the business-portal dashboard — no
 * second "get my business" query built for this channel.
 */
class BusinessProfileApiController extends Controller
{
    public function __construct(
        private readonly GetBusinessDashboardAction $getBusinessDashboard,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $this->getBusinessDashboard->execute((int) $request->attributes->get('nexus_business_id'));

        return response()->json([
            'data' => [
                'business' => BusinessData::fromEntity($data['business'])->toArray(),
                'agent' => $data['agent'] ? AgentData::fromEntity($data['agent'])->toArray() : null,
                'productCount' => $data['productCount'],
                'serviceCount' => $data['serviceCount'],
                'creditBalance' => $data['creditBalance'],
                'activeNegotiations' => $data['activeNegotiations'],
                'reputationScore' => $data['reputationScore']->toArray(),
            ],
        ]);
    }
}
