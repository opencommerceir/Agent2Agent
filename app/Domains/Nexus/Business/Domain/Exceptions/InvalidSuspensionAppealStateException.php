<?php

namespace App\Domains\Nexus\Business\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by SuspensionAppeal's own state-machine guard (an illegal
 * transition) — mirrors InvalidNegotiationStateException exactly: extends
 * InvalidArgumentException directly, no marker interface.
 */
final class InvalidSuspensionAppealStateException extends InvalidArgumentException
{
}
