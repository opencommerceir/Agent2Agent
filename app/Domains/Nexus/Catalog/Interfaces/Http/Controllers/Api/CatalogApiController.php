<?php

namespace App\Domains\Nexus\Catalog\Interfaces\Http\Controllers\Api;

use App\Domains\Nexus\Catalog\Application\Actions\SearchCatalogAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public REST API (Phase 9/M2) — `catalog.read` scope. Reuses
 * SearchCatalogAction (Phase 1/M4) unchanged — the caller's own catalog,
 * optionally filtered by `?query=`.
 */
class CatalogApiController extends Controller
{
    public function __construct(
        private readonly SearchCatalogAction $searchCatalog,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->searchCatalog->execute(
            (int) $request->attributes->get('nexus_business_id'),
            $request->string('query')->toString(),
        );

        return response()->json([
            'data' => [
                'products' => array_map(fn ($product) => $product->toArray(), $result['products']),
                'services' => array_map(fn ($service) => $service->toArray(), $result['services']),
            ],
        ]);
    }
}
