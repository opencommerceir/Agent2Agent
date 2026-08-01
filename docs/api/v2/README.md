# MCP API — v2

**Status: current.** Shipped 2026-08-02 (Phase 4, Stage 7 — API
Versioning). Same platform, same 70 capabilities, same authentication,
same permission model, same error codes as v1 — the only thing v2 changes
is the shape of the response envelope. See [changes.md](changes.md) for
the exact diff and [../migration/v1-to-v2.md](../migration/v1-to-v2.md)
for a step-by-step migration guide.

- **Base URL**: `/mcp/v2`
- **Auth**: identical to v1 — see [../v1/authentication.md](../v1/authentication.md).
- **Capabilities**: identical list to v1 — see [../v1/capabilities.md](../v1/capabilities.md).

## Response Envelope

```json
{
    "result": {},
    "metadata": {
        "api_version": "v2",
        "capability": "commerce.product.search",
        "execution_time": 12,
        "timestamp": "2026-08-02T10:00:00+00:00"
    }
}
```

`GET /mcp/v2/capabilities` follows the same convention:

```json
{
    "capabilities": [],
    "metadata": {
        "api_version": "v2",
        "count": 70,
        "timestamp": "2026-08-02T10:00:00+00:00"
    }
}
```

v2 is not deprecated — it carries only the `X-API-Version` header, none
of v1's `Deprecation`/`Sunset`/`Link`/`Warning` headers
(`config('api.deprecation')`, `config/api.php`, has no `v2` entry).
