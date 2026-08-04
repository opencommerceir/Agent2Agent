# v1 — Capability Discovery

```
GET /mcp/v1/capabilities
Authorization: Bearer <agent-token>
```

Returns every capability registered on the platform — not filtered to
what the calling Agent is individually permitted to execute (permission
checks happen at `execute` time, per capability, not at discovery time).

```json
{
    "data": {
        "capabilities": [
            {
                "name": "commerce.product.search",
                "description": "Search the product catalog",
                "input_schema": {"query": "string"},
                "output_schema": {"products": "array"},
                "required_permissions": ["commerce.products.read"]
            }
        ]
    },
    "meta": {
        "count": 113
    }
}
```

For the full, generated table of all 113 capabilities that exist today —
what each one requires as input, returns, and which permission it
needs — see [`docs/api-reference.md`](../../api-reference.md). That file
is generated directly from each module's own capability manifest
(`app/Modules/*/Interfaces/MCP/*Capabilities.php`), the same data this
endpoint itself serves, so the two never drift apart.

v2's own discovery endpoint (`GET /mcp/v2/capabilities`) returns the
identical `capabilities` list — only the envelope around it changes, see
[../v2/changes.md](../v2/changes.md).
