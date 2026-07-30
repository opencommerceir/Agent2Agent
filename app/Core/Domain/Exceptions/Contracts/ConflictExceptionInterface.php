<?php

namespace App\Core\Domain\Exceptions\Contracts;

/**
 * Marker interface for "a legitimate business-rule conflict, not a
 * malformed request or a missing resource" exceptions (e.g. insufficient
 * inventory) — mapped by MCPExceptionHandler to a CONFLICT (409)
 * envelope. See NotFoundExceptionInterface's docblock for why this is a
 * Core-owned contract rather than Core importing a Domain Module's
 * exception classes directly.
 */
interface ConflictExceptionInterface
{
}
