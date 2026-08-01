<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Application\Services\CapabilityExecutionService;
use App\Core\Application\Services\LanguageDetector;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Interfaces\HTTP\Requests\MCP\ExecuteCapabilityRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Authenticate -> rate-limit -> authorize -> execute, identical across
 * every MCP wire version — the security-critical sequence that must never
 * exist as two independently-maintained copies (a fix applied to v1's own
 * copy but forgotten in v2's would be a real vulnerability, not just
 * inconsistent formatting). Added in Stage 7 (API Versioning) specifically
 * so MCPGatewayControllerV2 doesn't duplicate this: every version-specific
 * concern (v1's {data,meta} vs v2's {result,metadata}) is isolated to each
 * subclass's own formatResponse(), the only thing that actually differs.
 *
 * Stores the authenticated Agent's id on $request->attributes — new in
 * Stage 7 — purely so Infrastructure\Middleware\ApiVersioning (which runs
 * before this controller and again after, wrapping $next($request)) can
 * log which Agent hit a deprecated endpoint. Nothing in this class reads
 * that attribute back itself.
 */
abstract class AbstractMCPGatewayController extends Controller
{
    public function __construct(
        protected readonly AgentAuthenticationService $agentAuthentication,
        protected readonly EnforceRateLimitAction $enforceRateLimit,
        protected readonly GetCapabilityAction $getCapability,
        protected readonly CheckPermissionAction $checkPermission,
        protected readonly CapabilityExecutionService $capabilityExecution,
        protected readonly LanguageDetector $languageDetector,
    ) {
    }

    public function execute(ExecuteCapabilityRequest $request): JsonResponse
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $request->attributes->set('agent_id', $agent->id);

        $this->enforceRateLimit->authorize($agent->id);

        $capabilityName = $request->string('capability')->toString();
        $input = $request->input('input', []);

        $capability = $this->getCapability->execute($capabilityName);

        foreach ($capability->requiredPermissions as $permissionKey) {
            $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, $permissionKey);
        }

        $language = $this->languageDetector->detect($request, $agent->tenantId);
        $execution = $this->capabilityExecution->execute($capability, $input, AuthContext::forAgent($agent, $language));

        return $this->formatResponse($execution, $capabilityName);
    }

    /**
     * @param array{result: array, executionTimeMs: int} $execution
     */
    abstract protected function formatResponse(array $execution, string $capabilityName): JsonResponse;
}
