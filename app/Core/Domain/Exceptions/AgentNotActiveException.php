<?php

namespace App\Core\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a token is valid but the Agent it belongs to is
 * inactive or suspended. Kept distinct from InvalidAgentTokenException
 * because the token itself was legitimate — this is a separate,
 * safe-to-report authorization state, not a credential-guessing signal.
 */
final class AgentNotActiveException extends RuntimeException
{
}
