<?php

namespace App\Core\Application\Services;

use Illuminate\Http\JsonResponse;

/**
 * The single place that shapes every MCP HTTP response, so success/error
 * envelopes can never drift between the two controllers.
 */
final class MCPResponseFormatter
{
    public function success(array $data, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    public function error(string $code, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
