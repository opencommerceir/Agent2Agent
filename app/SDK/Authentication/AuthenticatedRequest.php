<?php

namespace App\SDK\Authentication;

use App\SDK\Config\MCPConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Builds one pre-configured HTTP client (base URL, auth header, timeout,
 * SSL verification) so CapabilityDiscovery and CapabilityExecutor don't
 * each duplicate that setup — every outgoing MCP call goes through the
 * same, single place this is assembled.
 *
 * Built on Laravel's HTTP Client (Illuminate\Support\Facades\Http) rather
 * than a bare PSR-18/Guzzle client — this makes the SDK trivially
 * testable with Http::fake(), but it does mean the SDK, as written today,
 * can only run inside a booted Laravel application. A framework-agnostic
 * version for non-Laravel consumers would swap this one class for a
 * plain Guzzle client; nothing else in the SDK would need to change.
 */
final class AuthenticatedRequest
{
    private readonly TokenAuthenticator $authenticator;

    public function __construct(
        private readonly MCPConfig $config,
    ) {
        $this->authenticator = new TokenAuthenticator($config->token);
    }

    public function client(): PendingRequest
    {
        return Http::baseUrl($this->config->baseUrl)
            ->withHeaders($this->authenticator->headers())
            ->timeout($this->config->timeout)
            ->withOptions(['verify' => $this->config->verifySSL]);
    }
}
