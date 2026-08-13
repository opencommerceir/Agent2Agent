<?php

declare(strict_types=1);

namespace Nexus\Sdk;

/**
 * Thrown for any non-2xx response. Carries the Nexus error envelope's own
 * `code`/`message` fields (see the Public API docs at /nexus/docs) rather
 * than a generic HTTP-status-only message.
 */
final class NexusApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
