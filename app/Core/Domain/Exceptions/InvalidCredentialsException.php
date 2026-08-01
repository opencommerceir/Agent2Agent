<?php

namespace App\Core\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by AuthenticateUserAction when the email doesn't resolve to a
 * User, the password doesn't verify, or the User is deactivated —
 * deliberately generic about which (same reasoning
 * `InvalidAgentTokenException`'s own docblock gives: distinguishing "no
 * such email" from "wrong password" to the caller would help an attacker
 * enumerate valid accounts). Never reaches MCPExceptionHandler — this is a
 * web-login-only exception, not an MCP one, so it implements neither Core
 * marker interface; LoginController catches it directly and re-renders the
 * login form with a translated error instead.
 */
final class InvalidCredentialsException extends RuntimeException
{
}
