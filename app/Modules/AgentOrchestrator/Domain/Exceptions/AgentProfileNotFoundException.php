<?php

namespace App\Modules\AgentOrchestrator\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when `agent_type` names no configured `config/agents/{type}.php`
 * profile — whether because it's a genuinely unknown type, or a real
 * `AgentType` case (e.g. a newly added one) that hasn't had its own
 * profile config written yet. Implements `NotFoundExceptionInterface` so
 * it maps to 404 through Core's own marker-interface mechanism, the same
 * as every other `*NotFoundException` in this codebase.
 */
final class AgentProfileNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
