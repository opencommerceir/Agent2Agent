<?php

namespace OpenCommerce\SDK\Config;

/**
 * Immutable connection settings for a single MCPClient instance.
 *
 * `baseUrl` already carries the wire version in its own path
 * (`https://api.opencommerce.ir/mcp/v1`, `.../mcp/v2`, ...) — it always
 * has, since Phase 1. Server-side API Versioning (Phase 4, Stage 7) didn't
 * change that: a consumer picks a version today simply by pointing at a
 * different `baseUrl`, which is already the most explicit, "no hidden
 * behavior" way to pin one (see the server's own
 * VersionDetectorInterface docblock for why an explicit URL always wins
 * over a header there too). `forVersion()` below is purely additive sugar
 * for constructing that `baseUrl` correctly — it does not change what
 * this property means or how any existing caller already using it
 * behaves.
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

    /**
     * Builds `baseUrl` as `{host}/mcp/{version}` for you, so a caller
     * migrating from v1 to v2 (docs/api/migration/v1-to-v2.md) can change
     * one argument instead of hand-editing a URL string.
     *
     * ```php
     * $config = MCPConfig::forVersion(host: 'https://api.opencommerce.ir', version: 'v2', token: 'agent_token');
     * // baseUrl === 'https://api.opencommerce.ir/mcp/v2'
     * ```
     */
    public static function forVersion(
        string $host,
        string $version,
        string $token,
        int $timeout = 30,
        bool $verifySSL = true,
    ): self {
        return new self(
            baseUrl: rtrim($host, '/').'/mcp/'.$version,
            token: $token,
            timeout: $timeout,
            verifySSL: $verifySSL,
        );
    }
}
