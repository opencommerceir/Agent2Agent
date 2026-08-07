← [Integration and Usage Paths](20-integration-and-usage-paths.md) | Next: [Monetization and Business Use Cases](22-monetization-and-business-use-cases.md) →

# 21. SDKs and Connecting from Different Programming Languages

The previous file explained the two big-picture ways to use this platform (self-hosting vs. connecting to hosted infrastructure). This file goes into the practical detail of "connecting": if your project is written in PHP/Laravel, Python, Go, or Node.js/TypeScript, exactly how do you connect to OpenCommerce?

## The five official SDKs

| Language | Package name | Path in the repo | Runtime dependency |
|---|---|---|---|
| PHP (framework-agnostic) | `opencommerce/sdk` (Composer) | `packages/opencommerce-sdk` | Guzzle |
| PHP (Laravel-specific) | `opencommerce/sdk-laravel` (Composer) | `packages/opencommerce-sdk-laravel` | The PHP SDK above + `illuminate/support` |
| Python | `opencommerce-sdk` (PyPI) | `packages/opencommerce-sdk-python` | **None** (standard library only) |
| Node.js / TypeScript | `@opencommerce/sdk` (npm) | `packages/opencommerce-sdk-js` | **None** (native `fetch` only) |
| Go | `github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go` | `packages/opencommerce-sdk-go` | **None** (`net/http` only) |

Important: the Python, Node.js/TypeScript, and Go SDKs were deliberately built with **zero external dependencies** — each one only uses its own language's standard library. This means installing any of them never forces a version-pinned HTTP library onto your project. (The framework-agnostic PHP SDK is the one exception, since PHP itself has no standard-library HTTP client at all; the Laravel SDK naturally depends on it and on Laravel itself, since it's built specifically for that environment.)

All five SDKs follow the exact same contract: one config object, one client with three operations (`discoverCapabilities`/`execute`/`getCapability`), and an error hierarchy matching the server's own HTTP status codes.

## Python

```bash
pip install opencommerce-sdk
```

```python
from opencommerce_sdk import MCPClient, MCPConfig

config = MCPConfig(base_url="http://localhost:8000/mcp/v1", token="agent_token")
client = MCPClient(config)

capabilities = client.discover_capabilities()
result = client.execute("commerce.product.search", {"query": "laptop"})
print(result.data)
```

Error handling with ordinary Python exceptions:

```python
from opencommerce_sdk.exceptions import MCPException, ValidationException

try:
    client.execute("commerce.order.place", {})
except ValidationException as exc:
    print(f"Bad input: {exc}")
except MCPException as exc:
    print(f"Request failed ({exc.error_code}): {exc}")
```

Full detail: `packages/opencommerce-sdk-python/README.md`

## Node.js / TypeScript

```bash
npm install @opencommerce/sdk
```

```ts
import { MCPClient, MCPConfig } from "@opencommerce/sdk";

const config = new MCPConfig({ baseUrl: "http://localhost:8000/mcp/v1", token: "agent_token" });
const client = new MCPClient(config);

const capabilities = await client.discoverCapabilities();
const result = await client.execute("commerce.product.search", { query: "laptop" });
console.log(result.data);
```

This package is fully usable from plain JavaScript too — TypeScript is only there for type safety, never a requirement for the consuming project.

Full detail: `packages/opencommerce-sdk-js/README.md`

## Go

```bash
go get github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go
```

```go
import opencommerce "github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go"

config := opencommerce.NewConfig("http://localhost:8000/mcp/v1", "agent_token")
client := opencommerce.NewClient(config)
ctx := context.Background()

capabilities, err := client.DiscoverCapabilities(ctx)
result, err := client.Execute(ctx, "commerce.product.search", map[string]interface{}{"query": "laptop"})
fmt.Println(result.Data)
```

This SDK's `go.mod` declares this monorepo's own real path
(`github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go`),
not a local placeholder. Versioning goes through Git tags of the form
`packages/opencommerce-sdk-go/vX.Y.Z` — the moment such a tag is pushed
against this repository, the `go get` command above resolves for anyone
via `proxy.golang.org`, no account or separate publish step needed.

**One deliberate difference from the other SDKs**: every Go method takes a `context.Context` as its first parameter — this is exactly Go's own standard idiom for timeouts and cancellation, not an inconsistency. Error handling uses Go's native `error` type instead of exceptions (`errors.As` to branch on a specific error type).

Full detail: `packages/opencommerce-sdk-go/README.md`

## PHP (framework-agnostic)

The original SDK — the one every SDK above (Python, Node.js/TypeScript, Go) was deliberately mirrored from, field for field (files 3 and 18). Framework-agnostic — it needs no Laravel at all and runs from any plain PHP script.

```bash
composer require opencommerce/sdk
```

```php
use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\MCPClient;

$config = new MCPConfig(baseUrl: 'http://localhost:8000/mcp/v1', token: 'agent_token');
$client = new MCPClient($config);

$capabilities = $client->discoverCapabilities();
$result = $client->execute('commerce.product.search', ['query' => 'laptop']);
print_r($result->getData());
```

Error handling with ordinary PHP exceptions:

```php
use OpenCommerce\SDK\Exceptions\{MCPException, ValidationException};

try {
    $client->execute('commerce.order.place', []);
} catch (ValidationException $e) {
    echo "Bad input: {$e->getMessage()}\n";
} catch (MCPException $e) {
    echo "Request failed ({$e->errorCode}): {$e->getMessage()}\n";
}
```

The one base SDK that needs an external dependency (Guzzle) — because PHP, unlike Python/Node.js/Go, has no standard-library HTTP client at all; the three dependency-free SDKs above chose their own language's standard library for exactly that reason.

Full detail + a copy-pasteable Tinker snippet for minting a real Agent token (the same one file 18 of this tutorial points to as well): `packages/opencommerce-sdk/README.md`

## PHP (Laravel-specific)

If the consuming project is itself a Laravel application, `opencommerce/sdk-laravel` does the `MCPConfig`/`MCPClient` construction for you, once, instead of you hand-building it everywhere you need it — a ServiceProvider that resolves a singleton `MCPClient` (the exact same class above) from `config/opencommerce.php`, plus an `OpenCommerce` facade. It adds no new HTTP logic of its own — just a thin convenience layer over the framework-agnostic PHP SDK above.

```bash
composer require opencommerce/sdk-laravel
php artisan vendor:publish --tag=opencommerce-config
```

```env
OPENCOMMERCE_HOST=https://api.opencommerce.ir
OPENCOMMERCE_VERSION=v1
OPENCOMMERCE_TOKEN=your-agent-token
```

```php
use OpenCommerce\SDK\Laravel\Facades\OpenCommerce;

$capabilities = OpenCommerce::discoverCapabilities();
$result = OpenCommerce::execute('commerce.product.search', ['query' => 'laptop']);
```

Or with ordinary Laravel dependency injection:

```php
use OpenCommerce\SDK\MCPClient;

public function __invoke(MCPClient $client)
{
    return $client->execute('commerce.product.search', ['query' => request('q')]);
}
```

Error handling is exactly what the PHP SDK above already provides — this package adds nothing to it, it only makes constructing the client easier.

Full detail: `packages/opencommerce-sdk-laravel/README.md`

## What if your language has no official SDK?

No problem — the MCP Gateway (file 5) is nothing more than a standard **HTTP + JSON** API. Any language (Rust, Java, Ruby, C#, whatever) can connect directly with its own HTTP library:

```
POST {base_url}/execute
Authorization: Bearer <agent-token>
Content-Type: application/json

{"capability": "commerce.product.search", "input": {"query": "laptop"}}
```

...and read the response using the standard shape (file 5): `data`/`meta` for v1, or `result`/`metadata` for v2. Writing your own small SDK for your language — following the exact same pattern these SDKs already use (one Config, one Client, one injectable Transport layer for testing) — is a few hours of work, not a few weeks.

## Decision table: which connection method fits me?

| Situation | Suggested approach |
|---|---|
| My project is a Laravel application | `opencommerce/sdk-laravel` — the fastest, cleanest fit: a facade plus dependency injection |
| My project is PHP (no Laravel)/Python/Node.js/TypeScript/Go | Install the official SDK for my language |
| My language has no official SDK | Speak raw HTTP+JSON directly; build a thin client of my own if needed |
| I want to call one specific, already-known capability (e.g. "place this order") | Level 1: a direct capability call (`execute`) |
| I just want to hand over a plain-text goal and let the platform decide what to do | Level 2: the goal-driven Agent Orchestrator path (files 12–15) |

## Runnable example scripts

All four base languages (PHP, Python, Node.js/TypeScript, Go) have a complete, runnable script in the project's `examples/` folder — each calls the exact same four demo capabilities (`demo.tools.echo`, `demo.tools.time`, `demo.tools.calculator`) and ends with the same deliberate negative test (calling a capability that doesn't exist, to see a real 404) — so you can compare identical behavior across languages with your own eyes. The Laravel SDK has no separate example script of its own, since it's the exact same PHP client above made more convenient for a Laravel environment specifically — its own usage examples live in `packages/opencommerce-sdk-laravel/README.md`:

```
examples/sample-agent.php
examples/sample-agent.py
examples/sample-agent.ts
examples/sample-agent.go
```

How to run them (after bringing up the server per file 18 and getting an Agent token):

```bash
php examples/sample-agent.php <token>
python examples/sample-agent.py <token>
node examples/sample-agent.ts <token>
cd examples && go run sample-agent.go <token>
```

## Summary

- Five official SDKs exist today: PHP, PHP-for-Laravel, Python, Node.js/TypeScript, Go — all sharing one contract.
- Three of them (Python, Node.js/TypeScript, Go) are deliberately dependency-free.
- The Laravel SDK is a thin convenience layer over the PHP SDK, not an independent client.
- If your language has no official SDK, that's fine — MCP is just standard HTTP+JSON.
- Depending on your need, either call a capability directly (Level 1) or use the goal-driven AI agent engine (Level 2).

---

One file remains: it answers a completely different, practical question — if you wanted to actually make money from this project, exactly where would you start? For deeper, more technical detail on any architectural point, `HANDOFF.md` at the project root is always the final authority.

The Persian version of this same tutorial lives in `tutorials/fa/`.

← [Integration and Usage Paths](20-integration-and-usage-paths.md) | Next: [Monetization and Business Use Cases](22-monetization-and-business-use-cases.md) →
