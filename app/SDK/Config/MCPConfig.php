<?php

namespace App\SDK\Config;

/**
 * Immutable connection settings for a single MCPClient instance. Plain
 * data — no framework dependency — so the SDK's config shape stays stable
 * even if the HTTP layer underneath it ever changes.
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
