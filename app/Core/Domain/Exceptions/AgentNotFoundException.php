<?php

namespace App\Core\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Not needed anywhere before Phase 4 Stage 5's Dashboard Agents Management
 * page — same reasoning TenantNotFoundException's own docblock gives:
 * every prior Agent lookup happened in an already-trusted context.
 */
final class AgentNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
