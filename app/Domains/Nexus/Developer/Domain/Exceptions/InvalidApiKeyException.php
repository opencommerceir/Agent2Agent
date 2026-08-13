<?php

namespace App\Domains\Nexus\Developer\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by AuthenticateApiKeyAction for any reason a plaintext key fails
 * to authenticate (not found, revoked, expired) — deliberately one
 * exception type for all three. Distinguishing them to the caller would
 * let an attacker enumerate valid-but-revoked keys, the same "don't leak
 * which reason" caution a login failure already follows in this codebase.
 */
final class InvalidApiKeyException extends RuntimeException
{
}
