<?php

namespace App\Domains\Nexus\Developer\Interfaces\Http\Middleware;

use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Developer\Application\Actions\AuthenticateApiKeyAction;
use App\Domains\Nexus\Developer\Domain\Exceptions\InvalidApiKeyException;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Public REST API (Phase 9/M2, routes/nexus/api.php) —
 * the ApiKey-scoped counterpart to how AgentAuthenticationService gates
 * MCP requests. Unlike MCP (authenticated inside the controller, so
 * per-Agent rate limiting can use the resolved Agent id), this runs as
 * real route middleware: an ApiKey's businessId is already known from the
 * key itself, no controller-side resolution step needed, so there is no
 * equivalent reason to defer authentication past the middleware layer.
 *
 * A missing/invalid/revoked/expired key is answered directly here (401),
 * not routed through MCPExceptionHandler — InvalidApiKeyException is a
 * Nexus-domain type and Core must never import it (Decision 007). A
 * suspended Business or a missing scope instead throws Core's own
 * PermissionDeniedException, which MCPExceptionHandler (its handles()
 * widened in this same milestone to also cover `nexus/api/*`) already
 * maps to 403 — no new envelope-building code needed for that path.
 */
final class EnsureValidApiKey
{
    public function __construct(
        private readonly AuthenticateApiKeyAction $authenticateApiKey,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $plainKey = $request->bearerToken();

        if ($plainKey === null) {
            return $this->unauthorized('Missing Authorization: Bearer <api key> header.');
        }

        try {
            $apiKey = $this->authenticateApiKey->execute($plainKey);
        } catch (InvalidApiKeyException $e) {
            return $this->unauthorized($e->getMessage());
        }

        if ($requiredScope !== null && ! $apiKey->hasScope(ApiKeyScope::from($requiredScope))) {
            throw new PermissionDeniedException("This API key is missing the required '{$requiredScope}' scope.");
        }

        $business = $this->businesses->findById($apiKey->businessId());

        if ($business !== null && ! $business->isActive()) {
            throw new PermissionDeniedException("Business [{$apiKey->businessId()}] is suspended.");
        }

        $request->attributes->set('nexus_api_key', $apiKey);
        $request->attributes->set('nexus_business_id', $apiKey->businessId());

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
        ], 401);
    }
}
