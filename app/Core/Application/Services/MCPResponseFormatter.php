<?php

namespace App\Core\Application\Services;

use Illuminate\Http\JsonResponse;

/**
 * Shapes the success envelope for every MCP HTTP response. Error envelopes
 * used to be built here too (an error() method) — that moved to
 * MCPExceptionHandler once a global handler existed, so there is exactly
 * one place that formats MCP errors instead of two.
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
}
