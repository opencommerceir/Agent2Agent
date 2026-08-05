<?php

namespace App\Modules\AgentOrchestrator\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * A "missing piece implied by the request" addition (HANDOFF §3 pattern
 * #12) — the request named `GetExecutionResultAction` and a
 * `GET /api/agents/executions/{id}` endpoint but no exception for the
 * "unknown or cross-tenant id" case every other `Get*Action` in this
 * codebase needs one for (`TicketNotFoundException`, `OrderNotFoundException`,
 * ...). Implements `NotFoundExceptionInterface` so it maps to 404 through
 * Core's own marker-interface mechanism without Core ever importing this
 * module's class.
 */
final class ExecutionNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
