<?php

namespace App\Modules\AgentOrchestrator\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * This module's own wrapper around Core's identically-named
 * `App\Core\Domain\Exceptions\CapabilityNotFoundException`, thrown by
 * `CapabilityToolInvoker` when the capability a Planner asked for either
 * isn't registered in the Capability Registry at all, or has no execution
 * handler wired to it. Never imports/rethrows Core's own exception type
 * directly — the same "a cross-module 'does this exist' check always
 * throws the calling module's own exception" rule CRM's
 * `CustomerNotFoundException`/Finance's `OrderNotFoundException` already
 * establish (HANDOFF §3 pattern #9), here applied one layer down (Core,
 * not a sibling Domain Module) since Core's own marker-interface mechanism
 * is exactly what this class exists to plug into.
 *
 * Only ever reaches an HTTP caller through this module's own
 * `agent.execution.*`/`/api/agents/*` surface — inside PlanExecutor's own
 * per-step loop it is caught like any other failure and recorded on the
 * ExecutionStep, never bubbles as an exception (this module's own
 * "continue past a failed step" rule).
 */
final class CapabilityNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
