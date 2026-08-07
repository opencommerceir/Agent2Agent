# OpenCommerce Laravel SDK

A thin Laravel wrapper around the framework-agnostic
[`opencommerce/sdk`](../opencommerce-sdk/README.md) — the PHP client for
the OpenCommerce Platform's **MCP Gateway**. This package adds nothing new
to what the underlying SDK can do; it only removes the one bit of
boilerplate every Laravel consumer of that SDK would otherwise write by
hand: constructing an `MCPConfig`/`MCPClient` pair from config/env on every
request.

- Auto-resolves a configured `MCPClient` singleton from `config/opencommerce.php`.
- Ships an `OpenCommerce` facade for quick, expressive calls.
- No new HTTP logic, no new DTOs, no new exceptions — every real behavior
  (discovery, execution, error mapping) still lives in `opencommerce/sdk`.
  This package is a **ServiceProvider + Facade**, nothing more.

---

## Installation

```bash
composer require opencommerce/sdk-laravel
```

Laravel's package auto-discovery registers the ServiceProvider and the
`OpenCommerce` facade alias automatically — no manual `config/app.php`
edit needed.

Publish the config file:

```bash
php artisan vendor:publish --tag=opencommerce-config
```

This creates `config/opencommerce.php` in your application.

---

## Configuration

Set these in your `.env`:

```env
# Option 1 — you already know the exact, version-suffixed URL:
OPENCOMMERCE_BASE_URL=https://api.opencommerce.ir/mcp/v1

# Option 2 — or let the package build it for you (base_url wins if both are set):
OPENCOMMERCE_HOST=https://api.opencommerce.ir
OPENCOMMERCE_VERSION=v1

OPENCOMMERCE_TOKEN=your-agent-token
OPENCOMMERCE_TIMEOUT=30
OPENCOMMERCE_VERIFY_SSL=true
```

Pointed at a self-hosted deployment instead of OpenCommerce.ir? Just
change `OPENCOMMERCE_HOST` (or `OPENCOMMERCE_BASE_URL`) — nothing else
about how you call the client changes, the same "same protocol, different
`baseUrl`" property every OpenCommerce SDK already has (see
`tutorials/en/20-integration-and-usage-paths.md`).

---

## Usage

### Via the facade

```php
use OpenCommerce\SDK\Laravel\Facades\OpenCommerce;

$capabilities = OpenCommerce::discoverCapabilities();

$result = OpenCommerce::execute('commerce.product.search', ['query' => 'laptop']);

$product = OpenCommerce::getCapability('commerce.product.search');
```

### Via dependency injection

```php
use OpenCommerce\SDK\MCPClient;

final class ProductSearchController
{
    public function __invoke(MCPClient $client)
    {
        $result = $client->execute('commerce.product.search', [
            'query' => request()->string('q'),
        ]);

        return response()->json($result->getData());
    }
}
```

`MCPClient` is bound as a real singleton — the same instance (and the same
underlying Guzzle connection) is reused for the lifetime of the request,
the container resolves it lazily on first use.

### Error handling

Identical to the underlying SDK — see
[`packages/opencommerce-sdk/README.md`](../opencommerce-sdk/README.md#exceptions):

```php
use OpenCommerce\SDK\Exceptions\{
    AuthenticationException, AuthorizationException,
    NotFoundException, ValidationException, MCPException,
};

try {
    OpenCommerce::execute('commerce.order.place', []);
} catch (ValidationException $e) {
    // fix the input and retry
} catch (MCPException $e) {
    // anything else — log $e->errorCode / $e->getMessage()
}
```

---

## What this package deliberately doesn't add

- No caching, retry, or async wrapping around `MCPClient` — the same
  scope boundary `packages/opencommerce-sdk/README.md`'s own "What's
  intentionally not here" section already documents; this package doesn't
  change that boundary, it only makes the same client easier to obtain
  inside a Laravel app.
- No queued-job or event integration — dispatching a queued Job that
  calls `OpenCommerce::execute()` inside its own `handle()` is a normal
  application concern, not something this package needs to provide.

---

## Running this package's own tests

Tested with [Orchestra Testbench](https://packages.tools/testbench) — a
real, booted Laravel container, no external network call:

```bash
cd packages/opencommerce-sdk-laravel
composer install
vendor/bin/phpunit tests
```

---

## Contributing

This package lives inside the [OpenCommerce Platform](../../README.md)
monorepo at `packages/opencommerce-sdk-laravel/`. Follow the main repo's
[git workflow](../../docs/git-workflow.md) and
[coding standards](../../docs/coding-standards.md) for contributions.
