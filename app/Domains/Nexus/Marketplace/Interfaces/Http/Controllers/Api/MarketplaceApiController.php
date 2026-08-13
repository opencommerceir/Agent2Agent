<?php

namespace App\Domains\Nexus\Marketplace\Interfaces\Http\Controllers\Api;

use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public REST API (Phase 9/M2) — `marketplace.read` scope. Reuses
 * SearchMarketplaceAction (Phase 2/M1) unchanged, including its CostGate
 * call — a search through this REST endpoint costs exactly the same
 * credits as the identical nexus.marketplace.search MCP capability;
 * billing does not depend on which channel the caller used.
 */
class MarketplaceApiController extends Controller
{
    public function __construct(
        private readonly SearchMarketplaceAction $searchMarketplace,
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        $result = $this->searchMarketplace->execute(
            (int) $request->attributes->get('nexus_business_id'),
            $request->query('query'),
            $request->query('industry'),
        );

        return response()->json(['data' => $result]);
    }
}
