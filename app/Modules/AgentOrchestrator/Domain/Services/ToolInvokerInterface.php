<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Core\Application\DTOs\AuthContext;

/**
 * Invokes exactly one MCP capability by name — the one seam this whole
 * module exists around: a Planner decides *what* to call, this Interface
 * is *how* it actually gets called, and `CapabilityToolInvoker`
 * (Application/Services) is the one implementation, backed entirely by
 * Core's own CapabilityExecutionService — see that class's own docblock
 * for why "no business logic" for this module means "never touch a
 * Domain Module's Repository/Action directly, always go through the same
 * MCP capability surface every other Agent caller uses."
 *
 * Takes AuthContext directly, unlike every other Domain Service Interface
 * in this codebase (HANDOFF §3 pattern #1: "Domain Repository interfaces
 * and Application Actions take plain int $tenantId/$agentId — never
 * AuthContext itself"). This Interface is the one deliberate, documented
 * exception: it is not performing this module's own persistence (where
 * that rule applies) — it is re-entering the exact same MCP capability
 * boundary `AbstractMCPGatewayController` itself crosses, and that
 * boundary's own contract (`CapabilityExecutionService::execute()`)
 * requires a complete, valid AuthContext (including the already-resolved
 * Language) that cannot be correctly reconstructed from bare scalars
 * without duplicating LanguageDetector's own logic here. The same
 * reasoning applies to PlanExecutorInterface, which only exists to carry
 * this same AuthContext one level further down to here.
 */
interface ToolInvokerInterface
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function invoke(string $capability, array $input, AuthContext $context): array;
}
