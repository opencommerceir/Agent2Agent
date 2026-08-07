# OpenCommerce Go SDK

A small, **dependency-free** Go client for the OpenCommerce Platform's
**MCP Gateway** — the layer that lets AI Agents (and any other Go code: a
CLI tool, a background worker, a Lambda, ...) discover and execute
business capabilities exposed by an OpenCommerce deployment.

- Zero third-party dependencies — built entirely on the Go standard library (`net/http`, `encoding/json`).
- Idiomatic Go errors: `error` values you can branch on with `errors.As`, not exceptions.
- Every public method takes a `context.Context` first, Go's own standard idiom for cancellation and timeouts.
- Works identically against a self-hosted OpenCommerce instance or OpenCommerce's own hosted infrastructure — only `BaseURL`/`Token` change.
- Supports both the `v1` and `v2` wire envelopes transparently.

This SDK mirrors the [official PHP SDK](../opencommerce-sdk), the
[Python SDK](../opencommerce-sdk-python), and the
[Node.js/TypeScript SDK](../opencommerce-sdk-js)'s own API surface as
closely as idiomatic Go allows, so a team using OpenCommerce from more
than one language finds a familiar shape everywhere.

---

## Module path

`go.mod` declares `module github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go`
(§7.36) — this SDK's real, permanent home is a subdirectory of the main
`opencommerce-platform` monorepo, not a separate repository. Go's module
system resolves subdirectory modules like this one automatically via
`proxy.golang.org` once a `packages/opencommerce-sdk-go/vX.Y.Z`-prefixed
git tag exists on this repository — no separate publish step, no
registry account, unlike npm/PyPI.

---

## Installation

```bash
go get github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go
```

Inside this monorepo today (before any tag exists), add a `replace`
directive in your own `go.mod` pointing at `../opencommerce-sdk-go` (or
`packages/opencommerce-sdk-go`, depending on where your module lives) —
see `examples/go.mod` for exactly this.

---

## Quick Start (5 minutes)

You need an **Agent token** before you can call anything — MCP Gateway
authenticates every request. See the main repo's
[`packages/opencommerce-sdk/README.md`](../opencommerce-sdk/README.md#quick-start-5-minutes)
for how to mint one via Tinker, or ask whoever administers your
OpenCommerce tenant (self-hosted or on OpenCommerce.ir) for one.

```go
package main

import (
	"context"
	"fmt"
	"log"

	opencommerce "github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go"
)

func main() {
	config := opencommerce.NewConfig("http://localhost:8000/mcp/v1", "the-token-you-were-given")
	client := opencommerce.NewClient(config)
	ctx := context.Background()

	capabilities, err := client.DiscoverCapabilities(ctx)
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("%d capabilities available\n", len(capabilities))

	result, err := client.Execute(ctx, "demo.tools.echo", map[string]interface{}{"message": "Hello!"})
	if err != nil {
		log.Fatal(err)
	}
	fmt.Println(result.Data)
	// map[echo:Hello! timestamp:2026-...]
}
```

Pointing at a different deployment (self-hosted vs. OpenCommerce.ir) is
just a different host:

```go
// OpenCommerce's own hosted infrastructure
hosted := opencommerce.ForVersion("https://api.opencommerce.ir", "v1", token)

// A self-hosted instance
selfHosted := opencommerce.ForVersion("https://mcp.my-company.com", "v1", token)
```

See [`examples/sample-agent.go`](../../examples/sample-agent.go) in the
main repo for a complete, runnable program exercising discovery,
execution, and error handling.

---

## API Reference

### `Client`

| Method | Returns | Description |
|---|---|---|
| `DiscoverCapabilities(ctx)` | `([]Capability, error)` | Every capability this Agent's token can see. |
| `Execute(ctx, name, input)` | `(ExecutionResult, error)` | Runs a capability. Returns a non-nil `error` on any failure — see below. |
| `GetCapability(ctx, name)` | `(Capability, error)` | Fetches one capability by name (returns a `*NotFoundError` if it isn't there). |

### `ExecutionResult`

| Field | Description |
|---|---|
| `Data` | The capability's output, as a `map[string]interface{}`. |
| `Meta` | Response metadata (`capability`, `execution_time`, ...). |

There is no `Error()`/`IsSuccess()` field — a failed call always returns
a non-nil `error` instead, Go's own convention.

### Errors

Every error returned by this package satisfies the standard `error`
interface. Four narrower types embed the base `*MCPError` — branch on
one with `errors.As`:

| Type | HTTP status |
|---|---|
| `*AuthenticationError` | 401 |
| `*AuthorizationError` | 403 |
| `*NotFoundError` | 404 |
| `*ValidationError` | 422 |
| `*MCPError` (base) | anything else (429, 500, ...) |

```go
result, err := client.Execute(ctx, "commerce.order.place", map[string]interface{}{})
if err != nil {
	var validationErr *opencommerce.ValidationError
	if errors.As(err, &validationErr) {
		fmt.Printf("bad input: %s\n", validationErr.Message)
		return
	}

	var mcpErr *opencommerce.MCPError
	if errors.As(err, &mcpErr) {
		fmt.Printf("request failed (%s): %s\n", mcpErr.ErrorCode, mcpErr.Message)
	}
}
```

---

## Testing your own code against this SDK

`NewClientWithTransport` accepts anything implementing the `Transport`
interface (`Do(ctx, method, url, headers, jsonBody, timeoutSeconds) (TransportResponse, error)`),
so your own tests never need real network access either:

```go
type fakeTransport struct{}

func (fakeTransport) Do(
	_ context.Context, _, _ string, _ map[string]string, jsonBody map[string]interface{}, _ int,
) (opencommerce.TransportResponse, error) {
	input := jsonBody["input"].(map[string]interface{})
	return opencommerce.TransportResponse{
		Status: 200,
		Body:   map[string]interface{}{"data": map[string]interface{}{"echo": input["message"]}, "meta": map[string]interface{}{}},
	}, nil
}

client := opencommerce.NewClientWithTransport(
	opencommerce.NewConfig("https://example.test/mcp/v1", "t"),
	fakeTransport{},
)
```

---

## Running this SDK's own tests

```bash
cd packages/opencommerce-sdk-go
go build ./...
go vet ./...
go test ./... -v
```

No network access is needed — every test injects a fake `Transport`.

---

## License

MIT — same license as the OpenCommerce Platform itself.
