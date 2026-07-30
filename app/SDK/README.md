# OpenCommerce PHP SDK

A small client for talking to the MCP Gateway from an AI Agent. Lives at
`App\SDK\*` inside this application today.

## Scope note

This SDK is built as part of *this* Laravel app (`App\SDK`, uses the
`Illuminate\Support\Facades\Http` client) rather than as a standalone
Composer package. That's fine for demonstrating the pattern and for
in-repo testing, but a real third-party-installable PHP SDK
(`composer require opencommerce/php-sdk`) would need its own package with
its own namespace and a framework-agnostic HTTP client (plain Guzzle or
PSR-18) instead of Laravel's facade — `AuthenticatedRequest` is the only
class that would need to change to make that split.

## Usage

```php
use App\SDK\MCPClient;

$client = new MCPClient(
    baseUrl: 'https://api.opencommerce.ir/mcp/v1',
    token: 'agent_token_here',
);

// Discover what this platform can do
$capabilities = $client->discoverCapabilities();

// Execute a capability
$result = $client->execute('commerce.product.search', [
    'query' => 'laptop',
]);

if ($result->isSuccess()) {
    print_r($result->getData());
}
```

## Error handling

Every HTTP-level failure raises an exception — there is no "check
`$result->isSuccess()`" path for errors, only for the happy case (see
`ExecutionResult`'s docblock for why):

```php
use App\SDK\Exceptions\AuthenticationException;
use App\SDK\Exceptions\AuthorizationException;
use App\SDK\Exceptions\NotFoundException;
use App\SDK\Exceptions\ValidationException;
use App\SDK\Exceptions\MCPException;

try {
    $client->execute('commerce.product.search', ['query' => 'laptop']);
} catch (AuthenticationException $e) {
    // invalid / expired / revoked token
} catch (AuthorizationException $e) {
    // authenticated, but missing a required permission
} catch (NotFoundException $e) {
    // unknown capability
} catch (ValidationException $e) {
    // malformed request or input
} catch (MCPException $e) {
    // anything else (e.g. a 500 from the server)
}
```

## What's not here

- **Caching** for `discoverCapabilities()` — deliberately skipped (see
  `CapabilityDiscovery`'s docblock); wrap `MCPClient` yourself if you want it.
- **Retries / backoff** — not requested for this phase; add at the
  `AuthenticatedRequest` level if needed later.
- **Async execution** — `execute()` is synchronous only.
