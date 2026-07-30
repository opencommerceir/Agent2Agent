# OpenCommerce PHP SDK

A small, framework-agnostic PHP client for the OpenCommerce Platform's
**MCP Gateway** — the layer that lets AI Agents discover and execute
business capabilities exposed by an OpenCommerce deployment.

- No Laravel dependency. Built on [Guzzle](https://docs.guzzlephp.org/), so it runs in any PHP 8.2+ project.
- Type-safe: readonly DTOs and value objects, not raw arrays, at every public boundary.
- Every HTTP-level failure becomes a typed exception — no status codes to check by hand.

---

## Installation

```bash
composer require opencommerce/sdk
```

*(Inside the OpenCommerce monorepo itself, this package is linked via a
Composer `path` repository — see the root `composer.json` — so it's
already available there without a separate install step.)*

---

## Quick Start (5 minutes)

You need an **Agent token** before you can call anything — MCP Gateway
authenticates every request. If you're working inside the OpenCommerce
repo, the fastest way to get one is via Tinker:

```php
use App\Core\Application\Actions\{
    CreateTenantAction, CreateOrganizationAction, RegisterAgentAction,
    AddMemberToOrganizationAction, CreatePermissionAction, CreateRoleAction,
    AssignPermissionToRoleAction, AssignRoleToMemberAction, GenerateAgentTokenAction,
};
use App\Core\Domain\ValueObjects\MemberType;

$tenant = app(CreateTenantAction::class)->execute('My Company', 'my-company');
$org = app(CreateOrganizationAction::class)->execute($tenant->id, 'My Store', 'my-store');
$agent = app(RegisterAgentAction::class)->execute($tenant->id, $org->id, 'My Agent', 'shopping');
app(AddMemberToOrganizationAction::class)->execute($org->id, MemberType::Agent, $agent->id);

// Grant it the three Demo capabilities to try out
$role = app(CreateRoleAction::class)->execute($tenant->id, 'Demo Role', 'demo-role');
foreach (['demo.echo.execute', 'demo.time.read', 'demo.calculator.execute'] as $key) {
    $permission = app(CreatePermissionAction::class)->execute($key);
    app(AssignPermissionToRoleAction::class)->execute($role->id, $permission->id);
}
app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

echo app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;
```

Now, from any PHP script (this one doesn't need Laravel at all):

```php
require 'vendor/autoload.php';

use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\MCPClient;

$config = new MCPConfig(
    baseUrl: 'http://localhost:8000/mcp/v1',
    token: 'the-token-you-just-printed',
);
$client = new MCPClient($config);

$capabilities = $client->discoverCapabilities();
$result = $client->execute('demo.tools.echo', ['message' => 'Hello!']);

print_r($result->getData());
// ['echo' => 'Hello!', 'timestamp' => '2026-...']
```

See [`examples/sample-agent.php`](../../examples/sample-agent.php) in the
main repo for a complete, runnable script exercising discovery,
execution, and error handling.

---

## API Reference

### `MCPClient`

| Method | Returns | Description |
|---|---|---|
| `discoverCapabilities()` | `list<Capability>` | Every capability the platform exposes. |
| `execute(string $name, array $input = [])` | `ExecutionResult` | Runs a capability. Throws `MCPException` (or a subclass) on any failure. |
| `getCapability(string $name)` | `Capability` | Fetches one capability by name (filters the discovery list client-side — throws `NotFoundException` if it isn't there). |

### `ExecutionResult`

| Method | Description |
|---|---|
| `isSuccess()` | Always `true` — see note below. |
| `getData()` | The capability's output, as an array. |
| `getMeta()` | Response metadata (`capability`, `execution_time`, ...). |
| `getError()` | Always `null` — see note below. |

> **Why `isSuccess()`/`getError()` never actually report a failure:**
> every HTTP-level error is raised as an exception before an
> `ExecutionResult` is ever constructed, so any instance you hold in hand
> already succeeded. These methods exist for API predictability, not
> because you'll see them return otherwise today.

### Exceptions

All extend `MCPException`, which carries `errorCode` (string, e.g.
`"FORBIDDEN"`), the HTTP `statusCode`, and a human-readable `getMessage()`.

| Exception | HTTP Status | Meaning |
|---|---|---|
| `AuthenticationException` | 401 | Token missing, invalid, revoked, expired, or the agent is inactive. |
| `AuthorizationException` | 403 | Authenticated, but missing a permission the capability requires. |
| `NotFoundException` | 404 | The capability doesn't exist. |
| `ValidationException` | 422 | The request or `input` payload was malformed. |
| `MCPException` (base) | anything else (e.g. 500) | An unexpected server-side failure. |

```php
use OpenCommerce\SDK\Exceptions\{
    AuthenticationException, AuthorizationException,
    NotFoundException, ValidationException, MCPException,
};

try {
    $client->execute('commerce.product.search', ['query' => 'laptop']);
} catch (AuthenticationException $e) {
    // re-authenticate / refresh the token
} catch (AuthorizationException $e) {
    // this agent isn't allowed to do this
} catch (NotFoundException $e) {
    // no such capability
} catch (ValidationException $e) {
    // fix the input and retry
} catch (MCPException $e) {
    // anything else — log $e->errorCode / $e->getMessage()
}
```

---

## What's intentionally not here

- **No caching** for `discoverCapabilities()` — a cached list can go
  stale the moment a new capability is registered. Wrap `MCPClient`
  yourself if you want that trade-off.
- **No retries / backoff** — add at the HTTP layer if you need it.
- **No async execution** — `execute()` is synchronous only.

---

## Running this package's own tests

This package is tested completely independently of the main
application — no Laravel, no database:

```bash
cd packages/opencommerce-sdk
composer install
vendor/bin/phpunit tests
```

---

## Contributing

This package lives inside the [OpenCommerce Platform](../../README.md)
monorepo at `packages/opencommerce-sdk/`. Follow the main repo's
[git workflow](../../docs/git-workflow.md) and
[coding standards](../../docs/coding-standards.md) for contributions —
in particular: Domain-layer purity doesn't apply here (there is no
Domain layer in a client SDK), but type safety, readonly properties, and
one-exception-per-failure-mode do.
