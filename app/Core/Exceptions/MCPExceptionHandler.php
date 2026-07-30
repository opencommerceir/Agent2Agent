<?php

namespace App\Core\Exceptions;

use App\Core\Domain\Exceptions\AgentNotActiveException;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use App\Core\Domain\Exceptions\PermissionDeniedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * The single place that turns any Throwable escaping an MCP route into the
 * MCP error envelope ({"error":{"code","message"}}) — wired in
 * bootstrap/app.php, scoped to `mcp/*` requests only via handles(). Every
 * other route (a future Admin API, for instance) keeps Laravel's default
 * exception handling untouched.
 *
 * Controllers no longer catch these exceptions themselves the way Phase 4
 * did — that logic was duplicated per-controller; it now lives here once.
 * This is also why PermissionDeniedException is a real, reachable path
 * today: MCPGatewayController now calls CheckPermissionAction::authorize()
 * (which throws it) instead of execute() (a bool the controller had to
 * format into an error response itself).
 */
final class MCPExceptionHandler
{
    public static function handles(Request $request): bool
    {
        return $request->is('mcp/*');
    }

    public function render(Throwable $e, Request $request): JsonResponse
    {
        return match (true) {
            $e instanceof InvalidAgentTokenException,
            $e instanceof AgentNotActiveException => $this->respond('UNAUTHORIZED', $e->getMessage(), 401),

            $e instanceof PermissionDeniedException => $this->respond('FORBIDDEN', $e->getMessage(), 403),

            $e instanceof CapabilityNotFoundException => $this->respond('NOT_FOUND', $e->getMessage(), 404),

            $e instanceof ValidationException => $this->respond(
                'VALIDATION_ERROR',
                $e->validator->errors()->first() ?: 'The given data was invalid.',
                422,
            ),

            // Covers MCPRequestValidationService rejecting a capability's
            // `input` payload, and also every Demo capability handler's own
            // input checks (e.g. CalculateAction rejecting a non-numeric
            // operand) — both mean "the request body was wrong" to the
            // calling Agent, so both map to the same client-facing code.
            // Still worth re-checking this mapping if a future handler ever
            // throws InvalidArgumentException for something that *isn't*
            // bad input (e.g. a genuine internal state error).
            $e instanceof InvalidArgumentException => $this->respond('VALIDATION_ERROR', $e->getMessage(), 422),

            default => $this->respondToUnexpected($e),
        };
    }

    private function respondToUnexpected(Throwable $e): JsonResponse
    {
        report($e);

        $message = config('app.debug')
            ? $e->getMessage()
            : 'An unexpected error occurred.';

        return $this->respond('INTERNAL_ERROR', $message, 500);
    }

    private function respond(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
