<?php

namespace App\Domains\Nexus\Credit\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * A legitimate business-rule rejection (not enough balance to cover an
 * action's cost), never a malformed request — implements
 * ConflictExceptionInterface so MCPExceptionHandler maps it straight to a
 * 409 CONFLICT envelope with zero changes to Core.
 */
final class InsufficientCreditException extends RuntimeException implements ConflictExceptionInterface
{
}
