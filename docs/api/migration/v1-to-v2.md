# Migration Guide: v1 to v2

## Breaking Changes

### 1. Response Format

**v1:**
```json
{
  "data": {},
  "meta": {}
}
```

**v2:**
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

**What to change in your integration:**
- Replace every read of `response.data` with `response.result`.
- Replace every read of `response.meta` with `response.metadata`.
- `metadata.capability`/`metadata.execution_time` are the same fields
  `meta.capability`/`meta.execution_time` already gave you, just moved.
- New fields you can now read (optional): `metadata.api_version`,
  `metadata.timestamp`.

That is the complete list. See [../v2/changes.md](../v2/changes.md) for
what did **not** change (error codes, authentication, capabilities,
permissions, the actual data returned).

### 2. Error Codes

**Not changed.** v2 still returns `NOT_FOUND`, `VALIDATION_ERROR`, and
every other code exactly as v1 does — see
[../v1/errors.md](../v1/errors.md). An earlier draft of this stage's own
scope considered splitting these into more specific codes
(`RESOURCE_NOT_FOUND`, `INPUT_VALIDATION_ERROR`) but that was deliberately
not built: it would be a real breaking change to every existing
integration's error handling with no corresponding new capability behind
it. If your error-handling code only ever branches on `error.code`, it
needs no changes at all to move to v2.

## How to migrate

1. Point your client at `/mcp/v2` instead of `/mcp/v1` (or set the
   `Accept: application/vnd.opencommerce.v2+json` header/`?version=v2`
   query parameter on requests that don't already carry an explicit
   `/v1/`/`/v2/` segment in the URL — note that an **explicit URL
   version always wins**: hitting `/mcp/v1/execute` will always return
   v1's envelope regardless of any header/query parameter you also send,
   by design — see this guide's own "Why the URL always wins" note
   below).
2. Update your response parsing per the table above.
3. Everything else — capability names, input schemas, permissions,
   authentication — needs no changes.

### Why the URL always wins

An earlier draft of this stage's own test plan asked for header-based
overriding of an *explicit* URL version (hitting `/mcp/v1/execute` with an
`Accept: v2` header and expecting a v2-shaped response). That was
deliberately not built this way: it would mean an integration that
explicitly pinned itself to `/mcp/v1/execute` could have its response
shape silently change out from under it because some intermediary (a
proxy, a shared HTTP client default) attached a header it didn't intend —
exactly the kind of breaking-change-by-surprise this whole versioning
system exists to prevent. Header and query-parameter detection are real
and fully supported; they only ever apply when the URL itself carries no
version segment.

## Deprecation Timeline

- **v1 deprecated**: 2026-08-02
- **v1 sunset**: 2028-01-01
- **Recommended migration**: before 2027-06-01

Every `/mcp/v1/*` response carries `Deprecation`/`Sunset`/`Link`/`Warning`
headers as a live reminder of this timeline — see
[../v1/README.md](../v1/README.md#every-v1-response-carries-these-headers).
v1 keeps working, unmodified, for every existing integration until the
sunset date above; nothing about shipping v2 changes v1's behavior today.
