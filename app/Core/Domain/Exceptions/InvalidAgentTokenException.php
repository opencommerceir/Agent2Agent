<?php

namespace App\Core\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a token presented for authentication does not exist,
 * does not match, has been revoked, or has expired. Deliberately generic
 * about which of these it was — leaking that distinction to a caller
 * would help an attacker enumerate valid token hashes.
 */
final class InvalidAgentTokenException extends RuntimeException
{
}
