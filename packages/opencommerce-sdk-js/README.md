# OpenCommerce Node.js / TypeScript SDK

A small, **dependency-free** TypeScript client for the OpenCommerce
Platform's **MCP Gateway** — the layer that lets AI Agents (and any other
JS/TS code: a script, a Next.js API route, a LangChain.js tool, ...)
discover and execute business capabilities exposed by an OpenCommerce
deployment.

- Zero runtime dependencies — built entirely on the standard `fetch`/`AbortController` APIs, native in Node.js 18+, every modern browser, Deno, and Bun.
- Fully typed: `MCPClient`, `Capability`, `ExecutionResult`, and every exception ship real `.d.ts` declarations.
- Every HTTP-level failure becomes a typed exception — no status codes to check by hand.
- Works identically against a self-hosted OpenCommerce instance or OpenCommerce's own hosted infrastructure — only `baseUrl`/`token` change.
- Supports both the `v1` and `v2` wire envelopes transparently.

This SDK mirrors the [official PHP SDK](../opencommerce-sdk) and the
[Python SDK](../opencommerce-sdk-python)'s own API surface, so a team
using OpenCommerce from more than one language finds the same shape
everywhere.

---

## Installation

```bash
npm install @opencommerce/sdk
```

---

## Quick Start (5 minutes)

You need an **Agent token** before you can call anything — MCP Gateway
authenticates every request. See the main repo's
[`packages/opencommerce-sdk/README.md`](../opencommerce-sdk/README.md#quick-start-5-minutes)
for how to mint one via Tinker, or ask whoever administers your
OpenCommerce tenant (self-hosted or on OpenCommerce.ir) for one.

```ts
import { MCPClient, MCPConfig } from "@opencommerce/sdk";

const config = new MCPConfig({
  baseUrl: "http://localhost:8000/mcp/v1",
  token: "the-token-you-were-given",
});
const client = new MCPClient(config);

const capabilities = await client.discoverCapabilities();
const result = await client.execute("demo.tools.echo", { message: "Hello!" });

console.log(result.data);
// { echo: 'Hello!', timestamp: '2026-...' }
```

Works exactly the same from plain JavaScript (CommonJS or ESM) — this is
a regular, type-declaration-only package, nothing TypeScript-specific is
required at runtime.

Pointing at a different deployment (self-hosted vs. OpenCommerce.ir) is
just a different `baseUrl`:

```ts
// OpenCommerce's own hosted infrastructure
const hosted = MCPConfig.forVersion({ host: "https://api.opencommerce.ir", version: "v1", token: "..." });

// A self-hosted instance
const selfHosted = MCPConfig.forVersion({ host: "https://mcp.my-company.com", version: "v1", token: "..." });
```

See [`examples/sample-agent.ts`](../../examples/sample-agent.ts) in the
main repo for a complete, runnable script exercising discovery, execution,
and error handling.

---

## API Reference

### `MCPClient`

| Method | Returns | Description |
|---|---|---|
| `discoverCapabilities()` | `Promise<Capability[]>` | Every capability this Agent's token can see. |
| `execute(name, input?)` | `Promise<ExecutionResult>` | Runs a capability. Rejects with `MCPException` (or a subclass) on any failure. |
| `getCapability(name)` | `Promise<Capability>` | Fetches one capability by name (rejects with `NotFoundException` if it isn't there). |

### `ExecutionResult`

| Field | Description |
|---|---|
| `data` | The capability's output, as an object. |
| `meta` | Response metadata (`capability`, `execution_time`, ...). |

There is no `.error`/`isSuccess` — a failed call always rejects instead.

### Exceptions

All extend `MCPException` (`.errorCode`, `.statusCode`, `.message`):

| Exception | HTTP status |
|---|---|
| `AuthenticationException` | 401 |
| `AuthorizationException` | 403 |
| `NotFoundException` | 404 |
| `ValidationException` | 422 |
| `MCPException` (base) | anything else (429, 500, ...) |

```ts
import { MCPException, ValidationException } from "@opencommerce/sdk";

try {
  await client.execute("commerce.order.place", {});
} catch (error) {
  if (error instanceof ValidationException) {
    console.error(`Bad input: ${error.message}`);
  } else if (error instanceof MCPException) {
    console.error(`Request failed (${error.errorCode}): ${error.message}`);
  } else {
    throw error;
  }
}
```

---

## Testing your own code against this SDK

`MCPClient` accepts an optional second constructor argument implementing
the `Transport` interface, so your own tests never need real network
access either:

```ts
import { MCPClient, MCPConfig, type Transport } from "@opencommerce/sdk";

class FakeTransport implements Transport {
  async request(method: string, url: string, headers: Record<string, string>, jsonBody?: Record<string, unknown>) {
    return { status: 200, body: { data: { echo: (jsonBody?.input as any).message }, meta: {} } };
  }
}

const client = new MCPClient(
  new MCPConfig({ baseUrl: "https://example.test/mcp/v1", token: "t" }),
  new FakeTransport(),
);
```

### A note on self-signed certificates (`verifySSL: false`)

The default transport is built on the standard `fetch` API, which has no
cross-platform way to disable TLS certificate verification — browsers
never allow this from JavaScript at all, by design. If you need this for
local development against a self-signed certificate in Node.js, inject
your own `Transport` built on `node:https`'s `Agent({ rejectUnauthorized: false })`
instead of relying on `MCPConfig.verifySSL` (which the default transport
does not honor — a documented limitation, not an oversight).

---

## Development

```bash
npm install
npm run typecheck   # tsc --noEmit
npm test            # runs test/**/*.test.ts directly via Node's native test runner + TypeScript support
npm run build        # emits dist/*.js + dist/*.d.ts
```

Running the test suite directly from `.ts` source needs **Node.js 23.6+**
(or 22.6+ with `NODE_OPTIONS=--experimental-strip-types`) — Node's native
TypeScript support. Consumers of the *published* package only need
**Node.js 18+**, since they only ever run the compiled `dist/*.js` output.

---

## License

MIT — same license as the OpenCommerce Platform itself.
