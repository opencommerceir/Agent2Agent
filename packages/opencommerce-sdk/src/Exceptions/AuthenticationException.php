<?php

namespace OpenCommerce\SDK\Exceptions;

/**
 * Thrown for HTTP 401 — the agent token was missing, invalid, revoked,
 * expired, or the agent is inactive.
 */
final class AuthenticationException extends MCPException
{
}
