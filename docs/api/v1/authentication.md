# v1 — Authentication

Every `/mcp/v1/*` request (execute and capability discovery alike)
requires a bearer Agent token:

```
Authorization: Bearer <agent-token>
```

Tokens are generated per-Agent (`GenerateAgentTokenAction`) and are
tenant-scoped — a token only ever authenticates against the one Tenant its
Agent belongs to. There is no session, no cookie, and no CSRF concept on
this route family at all: `/mcp/*` deliberately carries none of Laravel's
`web` middleware group (see `routes/mcp.php`'s own docblock) since it's a
stateless, machine-to-machine surface, not a browser session.

A missing, unknown, revoked, or expired token all produce the same
response:

```json
{
    "error": {
        "code": "UNAUTHORIZED",
        "message": "...",
        "localized_message": "..."
    }
}
```

with HTTP status `401`. An Agent that IS authenticated but lacks a
capability's own required permission gets `403 FORBIDDEN` instead — a
distinct failure mode, checked per-capability at `execute` time (never at
discovery time — `GET /mcp/v1/capabilities` lists everything regardless of
what the calling Agent can actually run).

This is unchanged in v2 — authentication/authorization is identical across
every wire version; only the response envelope shape differs. See
[../v2/changes.md](../v2/changes.md).
