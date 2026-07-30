<?php

namespace OpenCommerce\SDK\Config;

/**
 * Immutable connection settings for a single MCPClient instance.
 */
final class MCPConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $token,
        public readonly int $timeout = 30,
        public readonly bool $verifySSL = true,
    ) {
    }
}
