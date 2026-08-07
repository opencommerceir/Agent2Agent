← [The Core](04-the-core.md) | Next: [The Commerce Module](06-commerce-module.md) →

# 5. The MCP Gateway and the Capability Model

## What is MCP?

**MCP (Model Context Protocol)** is a protocol that lets AI agents communicate with tools/capabilities in a standardized way. In OpenCommerce, the MCP Gateway plays exactly this role: the single entry point for any agent that wants to do anything on the platform.

## The path of a request, step by step

When an agent sends `POST /mcp/v1/execute`, this exact sequence runs:

```
1. Authenticate   → reads the Bearer token, resolves the real Agent
2. Rate Limit     → EnforceRateLimitAction (default: 100 requests/minute per Agent)
3. Authorize      → CheckPermissionAction (does this Agent have this capability's permission?)
4. Execute        → CapabilityExecutionService (actually runs the module's handler)
```

This sequence is security-critical and **must never be duplicated or copy-pasted** — which is exactly why, when API v2 was added, these four steps were extracted into one shared base class (`AbstractMCPGatewayController`) so v1 and v2 could never drift apart on this security path (file 10).

## Request body

```json
POST /mcp/v1/execute
Authorization: Bearer <agent-token>
Content-Type: application/json

{
  "capability": "commerce.product.search",
  "input": { "query": "shoes" }
}
```

## The response envelope

v1 (unchanged since day one):
```json
{ "data": { ... }, "meta": { ... } }
```

v2 (the newer format):
```json
{ "result": { ... }, "metadata": { "api_version": "v2", "timestamp": "..." } }
```

**Important:** v1 and v2 are **one platform wearing two different envelopes, not two different platforms.** Every capability, permission, error code, and authentication mechanism is identical between the two versions — only the response shape differs.

## The error response shape

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Order not found: id=42",
    "localized_message": "..."
  }
}
```

- `message` → the original, untranslated English text
- `localized_message` → translated based on the detected language (file 9)

Common codes: `NOT_FOUND` (404), `CONFLICT` (409), `VALIDATION_ERROR` (422), `TOO_MANY_REQUESTS` (429), `INTERNAL_ERROR` (500).

## Capability discovery

```
GET /mcp/v1/capabilities
```

This route returns the full list of capabilities available to this Agent, with descriptions, required input/output shapes, and required permissions — exactly what an AI agent needs to "figure out what it can do."

**Note:** discovery is only "documentation." Real authorization is always, separately, checked at **execution time** — seeing a capability listed in Discovery is never a guarantee you're actually allowed to call it.

## Capability naming rules

```
domain.resource.action    (exactly three segments, dot-separated)
```

Correct examples:
```
commerce.product.search
crm.ticket.create
agent.reasoning.explain
```

This constraint has forced several requested names to be rewritten throughout the project's history. For example:

| Requested name (rejected) | Final name |
|---|---|
| `crm.ticket.comment.add` (4 segments) | `crm.comment.create` |
| `workflow.create` (2 segments) | `workflow.definition.create` |
| `commerce.variant.attribute.create` (4 segments) | `commerce.attribute.create` |
| `commerce.subscription.plan.create` (4 segments) | `commerce.plan.create` |

This pattern repeats so often it's become a **standard rule of thumb**: when a requested name has 4 segments, one of the middle words is usually promoted to its own independent "resource."

## Handlers registered in the ServiceProvider

Every module registers a handler for each of its own capabilities inside its own `ServiceProvider`:

```php
// inside CommerceServiceProvider::boot()
$registry->register('commerce.product.search', function (array $input, AuthContext $context) {
    return app(SearchProductsAction::class)->execute($input, $context->tenantId);
});
```

**A gotcha worth flagging clearly:** registering the **handler** (the real execution) and registering the **description** (for Discovery/the seeder) are two completely separate steps. If a capability fails with "no execution handler found" even though you're sure it's wired in the ServiceProvider, check whether the test actually seeded the matching `XxxCapabilitiesSeeder`.

## Why isn't business logic here?

The MCP Gateway only does: authenticate, rate-limit, authorize, execute, format the response. **No business decision** (like "is this discount allowed?") is ever made here — that all lives inside the relevant module's `Action`. This is exactly the golden rule introduced in file 2.

## The HTTP counterpart for the Agent Orchestrator module

Since Phase 6, one additional HTTP surface exists (in addition to MCP, not instead of it):

```
POST /api/agents/{agent_type}      ← run a goal with a given persona
GET  /api/agents/executions/{id}   ← the result of a past run
```

The interesting part: both routes call the **exact same Actions** that the `agent.goal.execute` and `agent.execution.get` capabilities call through MCP. This means a module can be reachable both directly via MCP and via its own dedicated HTTP surface, without ever duplicating logic — a pattern documented as "pattern #19" in file 17.

## Summary

- Every request goes through four fixed steps: authenticate → rate-limit → authorize → execute.
- A capability name is always exactly three segments.
- Discovery is documentation only; enforcement always happens at execution time.
- No business logic lives inside the MCP Gateway.
- v1 and v2 only differ in response shape, never in behavior.

Now that we understand how requests enter the system, it's time to look at the **first and largest domain module** — Commerce.

---
← [The Core](04-the-core.md) | Next: [The Commerce Module](06-commerce-module.md) →
