<?php

namespace App\Domains\Nexus\Approval\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by ApprovalRequest's own state-machine guard — same shape
 * InvalidNegotiationStateException/InvalidCoalitionStateException already
 * established: extends InvalidArgumentException directly, no marker
 * interface.
 */
final class InvalidApprovalRequestStateException extends InvalidArgumentException
{
}
