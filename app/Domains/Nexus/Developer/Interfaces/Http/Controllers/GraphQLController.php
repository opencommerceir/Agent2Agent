<?php

namespace App\Domains\Nexus\Developer\Interfaces\Http\Controllers;

use App\Domains\Nexus\Developer\Interfaces\GraphQL\PublicApiSchemaFactory;
use App\Http\Controllers\Controller;
use GraphQL\GraphQL;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The single GraphQL entry point (Phase 9/M5), authenticated the same way
 * every other routes/nexus/api.php endpoint is (EnsureValidApiKey — no
 * scope parameter here, since scope is enforced per-field inside
 * PublicApiSchemaFactory, not per-request).
 */
class GraphQLController extends Controller
{
    public function __construct(
        private readonly PublicApiSchemaFactory $schemaFactory,
    ) {
    }

    public function execute(Request $request): JsonResponse
    {
        $result = GraphQL::executeQuery(
            schema: $this->schemaFactory->build(),
            source: (string) $request->input('query', ''),
            rootValue: null,
            contextValue: [
                'businessId' => (int) $request->attributes->get('nexus_business_id'),
                'apiKey' => $request->attributes->get('nexus_api_key'),
            ],
            variableValues: $request->input('variables'),
            operationName: $request->input('operationName'),
        );

        return response()->json($result->toArray());
    }
}
