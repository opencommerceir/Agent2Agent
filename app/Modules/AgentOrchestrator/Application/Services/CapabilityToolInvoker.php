<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityExecutionService;
use App\Core\Domain\Exceptions\CapabilityNotFoundException as CoreCapabilityNotFoundException;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\AgentOrchestrator\Domain\Exceptions\CapabilityNotFoundException;
use App\Modules\AgentOrchestrator\Domain\Services\ToolInvokerInterface;
use Illuminate\Support\Facades\Log;

/**
 * The one implementation of ToolInvokerInterface — invokes a capability
 * through the exact same building blocks `AbstractMCPGatewayController`
 * itself uses (`GetCapabilityAction` -> permission check -> `CapabilityExecutionService`),
 * so a capability called through this Orchestrator is authorized,
 * validated, and executed identically to one called directly over
 * `/mcp/v1/execute` — this module's own explicit "reuse the existing MCP/
 * Capability execution machinery, don't build a second ToolInvoker/
 * execution path" requirement. No business logic lives here: this class
 * does not know what any capability *does*, only how to look one up,
 * confirm the calling Agent may use it, and run it.
 */
final class CapabilityToolInvoker implements ToolInvokerInterface
{
    public function __construct(
        private readonly GetCapabilityAction $getCapability,
        private readonly CheckPermissionAction $checkPermission,
        private readonly CapabilityExecutionService $capabilityExecution,
    ) {
    }

    public function invoke(string $capability, array $input, AuthContext $context): array
    {
        try {
            $capabilityData = $this->getCapability->execute($capability);

            foreach ($capabilityData->requiredPermissions as $permissionKey) {
                $this->checkPermission->authorize(MemberType::Agent, $context->agentId, $context->tenantId, $permissionKey);
            }

            Log::info('Capability discovered', ['capability' => $capability]);

            $execution = $this->capabilityExecution->execute($capabilityData, $input, $context);
        } catch (CoreCapabilityNotFoundException $e) {
            throw new CapabilityNotFoundException(
                "Capability [{$capability}] does not exist or has no execution handler.",
                previous: $e,
            );
        }

        return $execution['result'];
    }
}
