<?php

namespace App\SDK\Exceptions;

/**
 * Thrown for HTTP 403 — the agent is authenticated but lacks a permission
 * the requested capability requires.
 */
final class AuthorizationException extends MCPException
{
}
