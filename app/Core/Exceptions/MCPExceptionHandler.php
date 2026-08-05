<?php

namespace App\Core\Exceptions;

use App\Core\Domain\Exceptions\AgentNotActiveException;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Core\Domain\Exceptions\RateLimitExceededException;
use App\Core\Domain\Services\TranslationServiceInterface;
use App\Core\Application\Services\LanguageDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The single place that turns any Throwable escaping an MCP route into the
 * MCP error envelope ({"error":{"code","message"}}) — wired in
 * bootstrap/app.php, scoped via handles() to `mcp/*` and — Agent
 * Orchestrator, §7.26 — `api/agents/*` requests. Every other route (the
 * Admin Dashboard's own `/dashboard/*`, for instance) keeps Laravel's
 * default exception handling untouched.
 *
 * `api/agents/*` was added to handles() rather than duplicating this same
 * exception -> envelope mapping a second time inside AgentController —
 * that controller authenticates/rate-limits/authorizes through the exact
 * same Core Actions MCPGatewayController itself calls
 * (AgentAuthenticationService/EnforceRateLimitAction/CheckPermissionAction),
 * so it throws the exact same exception types
 * (InvalidAgentTokenException/RateLimitExceededException/
 * PermissionDeniedException) this class already maps correctly; only the
 * URL prefix differs, not the shape of what can go wrong.
 *
 * Controllers no longer catch these exceptions themselves the way Phase 4
 * did — that logic was duplicated per-controller; it now lives here once.
 * This is also why PermissionDeniedException is a real, reachable path
 * today: MCPGatewayController now calls CheckPermissionAction::authorize()
 * (which throws it) instead of execute() (a bool the controller had to
 * format into an error response itself).
 *
 * bootstrap/app.php now resolves this class through the container
 * (`app(MCPExceptionHandler::class)`) instead of `new MCPExceptionHandler()`
 * specifically so this constructor DI works (Phase 4 Stage 4, i18n) — see
 * that file's own render() closure.
 *
 * `error.message` stays exactly what it always was (the exception's own,
 * possibly domain-specific text, e.g. "Order not found: id=42") — no
 * existing caller/test should see that field change. The new
 * `error.localized_message` is purely additive: a generic, translated
 * label for the error *code* (errors.{code}, lowercased) in whichever
 * Language LanguageDetector resolves for this request. Only the
 * query-parameter and Accept-Language-header tiers apply here (never the
 * Tenant-default tier) — a request that fails authentication (e.g. a bad
 * token) has no reliably-known Tenant to look a default up for, and every
 * other failure mode (permission/validation/etc.) is just as easily
 * language-detected from the request itself without needing one.
 */
final class MCPExceptionHandler
{
    public function __construct(
        private readonly LanguageDetector $languageDetector,
        private readonly TranslationServiceInterface $translator,
    ) {
    }

    public static function handles(Request $request): bool
    {
        return $request->is('mcp/*') || $request->is('api/agents/*');
    }

    public function render(Throwable $e, Request $request): JsonResponse
    {
        return match (true) {
            $e instanceof InvalidAgentTokenException,
            $e instanceof AgentNotActiveException => $this->respond('UNAUTHORIZED', $e->getMessage(), 401, $request),

            $e instanceof PermissionDeniedException => $this->respond('FORBIDDEN', $e->getMessage(), 403, $request),

            // Neither 404- nor 409-shaped (§3.2) — its own dedicated code,
            // same reasoning WooCommerceApiException's docblock gives for
            // not implementing either Core marker interface.
            $e instanceof RateLimitExceededException => $this->respond('TOO_MANY_REQUESTS', $e->getMessage(), 429, $request),

            $e instanceof CapabilityNotFoundException,
            $e instanceof NotFoundExceptionInterface => $this->respond('NOT_FOUND', $e->getMessage(), 404, $request),

            // A legitimate business-rule rejection (e.g. the requested
            // quantity is genuinely unavailable), not a malformed request
            // or a missing resource — CONFLICT/409 fits better than
            // shoehorning it into VALIDATION_ERROR or NOT_FOUND. Any Domain
            // Module's exception can opt into this by implementing the
            // marker interface — Core never imports the Module's class.
            $e instanceof ConflictExceptionInterface => $this->respond('CONFLICT', $e->getMessage(), 409, $request),

            $e instanceof ValidationException => $this->respond(
                'VALIDATION_ERROR',
                $e->validator->errors()->first() ?: 'The given data was invalid.',
                422,
                $request,
            ),

            // Covers MCPRequestValidationService rejecting a capability's
            // `input` payload, and also every Demo capability handler's own
            // input checks (e.g. CalculateAction rejecting a non-numeric
            // operand) — both mean "the request body was wrong" to the
            // calling Agent, so both map to the same client-facing code.
            // Still worth re-checking this mapping if a future handler ever
            // throws InvalidArgumentException for something that *isn't*
            // bad input (e.g. a genuine internal state error).
            $e instanceof InvalidArgumentException => $this->respond('VALIDATION_ERROR', $e->getMessage(), 422, $request),

            default => $this->respondToUnexpected($e, $request),
        };
    }

    /**
     * A latent gap this class always had, only ever surfaced once a
     * second route prefix (`api/agents/*`, §7.26) started sharing it: an
     * unmatched route (a bad `{agentType}` segment, a wrong HTTP verb)
     * throws Symfony's own `HttpExceptionInterface` — `NotFoundHttpException`/
     * `MethodNotAllowedHttpException` — carrying its own correct status
     * code (404/405), which this method used to flatten to 500 the same
     * as a genuinely unexpected error. Every `mcp/*` route was always
     * exact-string (`mcp/v1/execute`), so this never had a real chance to
     * fire there; `api/agents/{agentType}` is this codebase's first route
     * under either prefix with a `where()` constraint that can fail. Now
     * preserves the framework's own status code/message for this class of
     * exception instead of coercing it to INTERNAL_ERROR.
     */
    private function respondToUnexpected(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof HttpExceptionInterface) {
            return $this->respond('HTTP_ERROR', $e->getMessage() ?: 'Not found.', $e->getStatusCode(), $request);
        }

        report($e);

        $message = config('app.debug')
            ? $e->getMessage()
            : 'An unexpected error occurred.';

        return $this->respond('INTERNAL_ERROR', $message, 500, $request);
    }

    private function respond(string $code, string $message, int $status, Request $request): JsonResponse
    {
        $language = $this->languageDetector->detect($request);

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'localized_message' => $this->translator->translate('errors.'.strtolower($code), $language),
            ],
        ], $status);
    }
}
