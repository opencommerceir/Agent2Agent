← [Integration and Usage Paths](20-integration-and-usage-paths.md) | Back to: [Table of Contents](00-table-of-contents.md)

# 21. SDKs and Connecting from Different Programming Languages

The previous file explained the two big-picture ways to use this platform (self-hosting vs. connecting to hosted infrastructure). This file goes into the practical detail of "connecting": if your project is written in Python, Go, or Node.js/TypeScript, exactly how do you connect to OpenCommerce?

## The four official SDKs

| Language | Package name | Path in the repo | Runtime dependency |
|---|---|---|---|
| PHP | `opencommerce/sdk` (Composer) | `packages/opencommerce-sdk` | Guzzle |
| Python | `opencommerce-sdk` (PyPI) | `packages/opencommerce-sdk-python` | **None** (standard library only) |
| Node.js / TypeScript | `@opencommerce/sdk` (npm) | `packages/opencommerce-sdk-js` | **None** (native `fetch` only) |
| Go | (local for now — see file 19) | `packages/opencommerce-sdk-go` | **None** (`net/http` only) |

Important: the three newer SDKs were deliberately built with **zero external dependencies** — each one only uses its own language's standard library. This means installing any of them never forces a version-pinned HTTP library onto your project. (The PHP SDK is the one exception, since PHP itself has no standard-library HTTP client at all.)

All four SDKs follow the exact same contract: one config object, one client with three operations (`discoverCapabilities`/`execute`/`getCapability`), and an error hierarchy matching the server's own HTTP status codes.

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
go get github.com/<org>/opencommerce-sdk-go   # once published — still local-only today, see the SDK's own README
```

```go
config := opencommerce.NewConfig("http://localhost:8000/mcp/v1", "agent_token")
client := opencommerce.NewClient(config)
ctx := context.Background()

capabilities, err := client.DiscoverCapabilities(ctx)
result, err := client.Execute(ctx, "commerce.product.search", map[string]interface{}{"query": "laptop"})
fmt.Println(result.Data)
```

**One deliberate difference from the other three SDKs**: every Go method takes a `context.Context` as its first parameter — this is exactly Go's own standard idiom for timeouts and cancellation, not an inconsistency. Error handling uses Go's native `error` type instead of exceptions (`errors.As` to branch on a specific error type).

Full detail: `packages/opencommerce-sdk-go/README.md`

## PHP

For completeness (the original SDK every other one mirrors):

```bash
composer require opencommerce/sdk
```

Full detail: `packages/opencommerce-sdk/README.md`

## What if your language has no official SDK?

No problem — the MCP Gateway (file 5) is nothing more than a standard **HTTP + JSON** API. Any language (Rust, Java, Ruby, C#, whatever) can connect directly with its own HTTP library:

```
POST {base_url}/execute
Authorization: Bearer <agent-token>
Content-Type: application/json

{"capability": "commerce.product.search", "input": {"query": "laptop"}}
```

...and read the response using the standard shape (file 5): `data`/`meta` for v1, or `result`/`metadata` for v2. Writing your own small SDK for your language — following the exact same pattern these four already use (one Config, one Client, one injectable Transport layer for testing) — is a few hours of work, not a few weeks.

## Decision table: which connection method fits me?

| Situation | Suggested approach |
|---|---|
| My project is PHP/Python/Node.js/TypeScript/Go | Install the official SDK for my language |
| My language has no official SDK | Speak raw HTTP+JSON directly; build a thin client of my own if needed |
| I want to call one specific, already-known capability (e.g. "place this order") | Level 1: a direct capability call (`execute`) |
| I just want to hand over a plain-text goal and let the platform decide what to do | Level 2: the goal-driven Agent Orchestrator path (files 12–15) |

## Runnable example scripts

All four languages have a complete, runnable script in the project's `examples/` folder — each calls the exact same four demo capabilities (`demo.tools.echo`, `demo.tools.time`, `demo.tools.calculator`) and ends with the same deliberate negative test (calling a capability that doesn't exist, to see a real 404) — so you can compare identical behavior across languages with your own eyes:

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

- Four official SDKs exist today: PHP, Python, Node.js/TypeScript, Go — all sharing one contract.
- The three newer ones are deliberately dependency-free.
- If your language has no official SDK, that's fine — MCP is just standard HTTP+JSON.
- Depending on your need, either call a capability directly (Level 1) or use the goal-driven AI agent engine (Level 2).

---

This is the last file in the tutorial series. For deeper, more technical detail on any point, `HANDOFF.md` at the project root is always the final authority.

The Persian version of this same tutorial lives in `tutorials/fa/`.

← [Integration and Usage Paths](20-integration-and-usage-paths.md) | Back to: [Table of Contents](00-table-of-contents.md)
