# v1 — Error Codes

Every failure on `/mcp/v1/*` (and `/mcp/v2/*` — identical, unchanged in
v2) is turned into the same envelope by
`App\Core\Exceptions\MCPExceptionHandler`, the single place that formats
every MCP error:

```json
{
    "error": {
        "code": "NOT_FOUND",
        "message": "Order not found: id=42",
        "localized_message": "منبع درخواستی یافت نشد"
    }
}
```

`message` is the exception's own, possibly domain-specific text.
`localized_message` is a generic, translated label for the error *code*
itself — pass `?lang=fa` or an `Accept-Language` header to get it in
Farsi instead of English (see `docs/api-reference.md`'s own Language
section).

| Code | HTTP Status | When |
|---|---|---|
| `UNAUTHORIZED` | 401 | Missing, unknown, revoked, or expired Agent token; inactive Agent. |
| `FORBIDDEN` | 403 | Valid token, but the calling Agent lacks the capability's required permission. |
| `TOO_MANY_REQUESTS` | 429 | The calling Agent exceeded `MCP_RATE_LIMIT_PER_MINUTE` (default 100/min). |
| `NOT_FOUND` | 404 | The named capability doesn't exist, or a referenced resource (Order, Product, ...) doesn't. |
| `CONFLICT` | 409 | A legitimate business-rule rejection — e.g. insufficient stock, insufficient loyalty points. |
| `VALIDATION_ERROR` | 422 | The request body, or a capability's own `input`, failed validation. |
| `INTERNAL_ERROR` | 500 | An unexpected server-side failure. `message` is generic in production (`app.debug=false`). |

These codes are **identical between v1 and v2** — Stage 7 (API
Versioning) deliberately did not introduce v2-specific error codes (no
`RESOURCE_NOT_FOUND`/`INPUT_VALIDATION_ERROR` split some early drafts of
the migration plan considered): renaming a code every existing v1 test
and integration already asserts on would be a real breaking change with
no corresponding real behavior change behind it. If a future version ever
genuinely needs different/more specific codes, that's a decision for that
version's own stage, made deliberately — not an incidental side effect of
this one.
