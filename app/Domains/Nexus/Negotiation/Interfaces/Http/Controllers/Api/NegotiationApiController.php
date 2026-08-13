<?php

namespace App\Domains\Nexus\Negotiation\Interfaces\Http\Controllers\Api;

use App\Domains\Nexus\Negotiation\Application\Actions\GetNegotiationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public REST API (Phase 9/M2) — `negotiation.read` scope. Reuses
 * GetNegotiationAction (Phase 2/M3, party-authorized) unchanged — the
 * same lookup nexus.negotiation.status and the Live Negotiation Viewer
 * already share.
 */
class NegotiationApiController extends Controller
{
    public function __construct(
        private readonly GetNegotiationAction $getNegotiation,
    ) {
    }

    public function show(Request $request, int $negotiation): JsonResponse
    {
        $data = $this->getNegotiation->execute($negotiation, (int) $request->attributes->get('nexus_business_id'));

        return response()->json(['data' => $data->toArray()]);
    }
}
