# Changes: v1 -> v2

## 1. Response envelope

| | v1 | v2 |
|---|---|---|
| Success payload key | `data` | `result` |
| Metadata key | `meta` | `metadata` |
| `capability` / `execution_time` | inside `meta` | inside `metadata`, unchanged |
| `api_version` | not present | inside `metadata`, always `"v2"` |
| `timestamp` (ISO 8601, request time) | not present | inside `metadata` |

This is the **entire** behavioral difference between v1 and v2 today.
Every capability, every permission, every error code, the authentication
mechanism, and the underlying data returned are byte-for-byte identical —
`ApiVersioningTest::test_bothVersions_returnTheSameUnderlyingData` proves
this directly (calling the same capability with the same input through
both versions and asserting `v1.data === v2.result`).

## 2. Error codes

**Unchanged.** v2 uses the exact same `UNAUTHORIZED`/`FORBIDDEN`/
`NOT_FOUND`/`CONFLICT`/`VALIDATION_ERROR`/`TOO_MANY_REQUESTS`/
`INTERNAL_ERROR` codes as v1 — see
[../v1/errors.md](../v1/errors.md)'s own closing note for why a code
rename was considered and deliberately not built this stage.

## 3. Headers

v2 responses carry `X-API-Version: v2` only. v1's
`Deprecation`/`Sunset`/`Link`/`Warning` headers never appear on a v2
response, since v2 has no entry in `config('api.deprecation')`.

## New Features in v2 — planned, not built this stage

The following were raised while scoping this stage as things a *future*
v2 might eventually want, and are recorded here so they aren't
rediscovered as if new, but none of them exist yet — Stage 7's own scope
was response-shape versioning infrastructure, not new platform
capabilities:

- **Batch operations** — executing several capabilities in one HTTP
  round trip. Would need its own request/response schema and its own
  design pass (partial-failure semantics, ordering guarantees); not a
  byte-for-byte extension of the current single-capability `execute`
  shape.
- **Webhook support** — the platform pushing events to a subscriber
  instead of only ever being polled. Would need a whole new
  subscription/delivery/retry model, closer in shape to the
  Notifications module (`App\Modules\Notifications`) than to anything
  in MCP itself.
- **Real-time updates** — a persistent connection (WebSocket/SSE) rather
  than request/response. A materially different transport, not a v3 of
  the current HTTP envelope.

Any of these becoming real work is a decision for whoever scopes the next
API stage, the same "raise it, don't silently build it" discipline this
whole session has followed throughout.
