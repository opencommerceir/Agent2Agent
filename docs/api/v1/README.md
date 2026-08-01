# MCP API — v1

**Status: stable, deprecated.** v1 is the platform's original MCP wire
format — every capability that exists today was built and is still served
under it. It was marked deprecated on **2026-08-02** (the day v2 shipped,
Phase 4 Stage 7) and is scheduled to sunset on **2028-01-01**. See
[../migration/v1-to-v2.md](../migration/v1-to-v2.md) before building any
new integration against it.

- **Base URL**: `/mcp/v1`
- **Auth**: `Authorization: Bearer <agent-token>` — see
  [authentication.md](authentication.md).
- **Capabilities**: the full, generated list of every capability v1 (and
  v2 — the set is identical, only the envelope differs) can execute lives
  in [`docs/api-reference.md`](../../api-reference.md), not duplicated
  here. See [capabilities.md](capabilities.md) for how discovery works.
- **Errors**: see [errors.md](errors.md).

## Response Envelope

```json
{
    "data": {},
    "meta": {}
}
```

`data` is whatever the capability's own `outputSchema` describes. `meta`
always carries `capability` (the name that was called) and
`execution_time` (milliseconds); `GET /mcp/v1/capabilities` instead
carries `count`.

## Every v1 response carries these headers

Added by `App\Core\Interfaces\HTTP\Middleware\ApiVersioning` (Phase 4,
Stage 7) on every request under `/mcp/v1/*`, success or error alike:

| Header | Value | Meaning |
|---|---|---|
| `X-API-Version` | `v1` | Which version actually served this request. |
| `Deprecation` | `true` | This version is deprecated — see below. |
| `Sunset` | `Sat, 01 Jan 2028 00:00:00 GMT` | The date v1 stops being served (RFC 8594). |
| `Link` | `<https://docs.opencommerce.ir/migration/v1-to-v2>; rel="successor-version"` | Where to read about migrating. |
| `Warning` | `299 - "API v1 is deprecated. Please migrate to v2 by 2028-01-01"` | Human-readable summary of the above. |

None of this changes v1's actual behavior — every existing v1 integration
keeps working exactly as it does today, unmodified, until the sunset date.
The headers exist so a client can *notice* it should move, not so it's
forced to before it's ready.
