<?php

namespace OpenCommerce\SDK\Exceptions;

use Exception;

/**
 * Base type for every error the SDK raises. Named `errorCode` (not
 * `code`) deliberately — PHP's own Exception::$code is an int and MCP's
 * error codes are strings ("UNAUTHORIZED", "NOT_FOUND", ...).
 *
 * fromResponse() is the single place that turns an MCP Gateway HTTP
 * response into the right exception subclass.
 */
class MCPException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode,
    ) {
        parent::__construct($message);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromResponse(int $status, array $body): self
    {
        $errorCode = $body['error']['code'] ?? 'UNKNOWN_ERROR';
        $message = $body['error']['message'] ?? "MCP request failed with HTTP {$status}.";

        return match ($status) {
            401 => new AuthenticationException($errorCode, $message, $status),
            403 => new AuthorizationException($errorCode, $message, $status),
            404 => new NotFoundException($errorCode, $message, $status),
            422 => new ValidationException($errorCode, $message, $status),
            default => new self($errorCode, $message, $status),
        };
    }
}
