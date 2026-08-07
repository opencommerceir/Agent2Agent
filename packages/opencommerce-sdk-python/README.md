# OpenCommerce Python SDK

A small, **dependency-free** Python client for the OpenCommerce Platform's
**MCP Gateway** — the layer that lets AI Agents (and any other Python code:
a script, a LangChain tool, a FastAPI service, ...) discover and execute
business capabilities exposed by an OpenCommerce deployment.

- No third-party HTTP library required — built entirely on Python's own standard library (`urllib`).
- Typed: `dataclass`-based value objects, not raw dicts, at every public boundary.
- Every HTTP-level failure becomes a typed exception — no status codes to check by hand.
- Works identically against a self-hosted OpenCommerce instance or OpenCommerce's own hosted infrastructure — only `base_url`/`token` change.
- Supports both the `v1` and `v2` wire envelopes transparently.

This SDK mirrors the [official PHP SDK](../opencommerce-sdk)'s own API
surface field-for-field, so a team using OpenCommerce from more than one
language finds the same shape everywhere.

---

## Installation

```bash
pip install opencommerce-platform-sdk
```

*(Distribution name is `opencommerce-platform-sdk` — `opencommerce-sdk`
was already registered on PyPI by an unrelated project, §7.36 — but the
importable package is still `opencommerce_sdk`, unchanged: `import opencommerce_sdk`.)*

*(Inside this monorepo, you can also just add
`packages/opencommerce-sdk-python` to your `PYTHONPATH`, or
`pip install -e packages/opencommerce-sdk-python` for local development.)*

---

## Quick Start (5 minutes)

You need an **Agent token** before you can call anything — MCP Gateway
authenticates every request. See the main repo's
[`packages/opencommerce-sdk/README.md`](../opencommerce-sdk/README.md#quick-start-5-minutes)
for how to mint one via Tinker, or ask whoever administers your
OpenCommerce tenant (self-hosted or on OpenCommerce.ir) for one.

```python
from opencommerce_sdk import MCPClient, MCPConfig

config = MCPConfig(
    base_url="http://localhost:8000/mcp/v1",
    token="the-token-you-were-given",
)
client = MCPClient(config)

capabilities = client.discover_capabilities()
result = client.execute("demo.tools.echo", {"message": "Hello!"})

print(result.data)
# {'echo': 'Hello!', 'timestamp': '2026-...'}
```

Pointing at a different deployment (self-hosted vs. OpenCommerce.ir) is
just a different `base_url`:

```python
# OpenCommerce's own hosted infrastructure
config = MCPConfig.for_version(host="https://api.opencommerce.ir", version="v1", token="...")

# A self-hosted instance
config = MCPConfig.for_version(host="https://mcp.my-company.com", version="v1", token="...")
```

See [`examples/sample-agent.py`](../../examples/sample-agent.py) in the
main repo for a complete, runnable script exercising discovery, execution,
and error handling.

---

## API Reference

### `MCPClient`

| Method | Returns | Description |
|---|---|---|
| `discover_capabilities()` | `list[Capability]` | Every capability this Agent's token can see. |
| `execute(name, input=None)` | `ExecutionResult` | Runs a capability. Raises `MCPException` (or a subclass) on any failure. |
| `get_capability(name)` | `Capability` | Fetches one capability by name (raises `NotFoundException` if it isn't there). |

### `ExecutionResult`

| Attribute | Description |
|---|---|
| `.data` | The capability's output, as a `dict`. |
| `.meta` | Response metadata (`capability`, `execution_time`, ...). |

There is no `.error`/`is_success()` — a failed call always raises instead,
Python's own convention for errors.

### Exceptions

All inherit from `MCPException` (`.error_code`, `.status_code`,
`str(exc)` for the message):

| Exception | HTTP status |
|---|---|
| `AuthenticationException` | 401 |
| `AuthorizationException` | 403 |
| `NotFoundException` | 404 |
| `ValidationException` | 422 |
| `MCPException` (base) | anything else (429, 500, ...) |

```python
from opencommerce_sdk import MCPException
from opencommerce_sdk.exceptions import ValidationException

try:
    client.execute("commerce.order.place", {})
except ValidationException as exc:
    print(f"Bad input: {exc}")
except MCPException as exc:
    print(f"Request failed ({exc.error_code}): {exc}")
```

---

## Testing your own code against this SDK

`MCPClient` accepts an optional second constructor argument implementing
the `Transport` protocol (`request(method, url, headers, json_body, timeout) -> (status, body)`),
so your own tests never need real network access either:

```python
from opencommerce_sdk import MCPClient, MCPConfig

class FakeTransport:
    def request(self, method, url, headers, json_body=None, timeout=30):
        return 200, {"data": {"echo": json_body["input"]["message"]}, "meta": {}}

client = MCPClient(MCPConfig(base_url="https://example.test/mcp/v1", token="t"), FakeTransport())
```

---

## Running this SDK's own tests

```bash
cd packages/opencommerce-sdk-python
python -m unittest discover -s tests -t . -v
```

No network access and no extra dependencies are needed — every test
injects a fake `Transport`.

---

## License

MIT — same license as the OpenCommerce Platform itself.
