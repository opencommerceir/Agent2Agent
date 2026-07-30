<?php

namespace App\SDK\Exceptions;

/**
 * Thrown for HTTP 422 — the request envelope or the capability's `input`
 * payload failed validation on the server.
 */
final class ValidationException extends MCPException
{
}
