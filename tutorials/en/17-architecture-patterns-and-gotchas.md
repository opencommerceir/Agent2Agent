← [The Showcase Demo](16-showcase-demo.md) | Next: [Install, Run and Test](18-install-run-and-test.md) →

# 17. Established Architecture Patterns and Important Gotchas

Throughout the previous files, these patterns kept resurfacing. This file collects all of them into one quick-reference "cheat sheet" — if you work on this project, keep this file handy.

## Part A — Architecture patterns (always follow these)

### 1. Explicit scalars everywhere; `AuthContext` only at the MCP boundary
Repositories and Actions always take `int $tenantId`/`int $agentId`, never the full `AuthContext`. Only ServiceProvider handler closures unpack it. (Documented exception: a few Interfaces inside the Agent Orchestrator that genuinely need to re-enter that same boundary — files 12 and 14.)

### 2. Marker Interfaces for error routing
Any domain exception that must map to a 404 or 409 implements `NotFoundExceptionInterface` or `ConflictExceptionInterface` — the Core never needs to know that module's concrete class.

### 3. Actions composing Actions is normal
Like `AddToCartAction` depending on `CheckInventoryAction`. Reusing correct logic always beats duplicating it.

### 4. Real separation between Preview and Apply
Any calculation with a side effect gets two separate Actions: one side-effect-free preview (`CalculatePricingAction`), one durable apply (`ApplyCouponAction`) — never a single Action with an `$apply = false` flag.

### 5. The two-phase Inventory lifecycle
`reserve()/release()` (a soft hold) vs. `commit()/restore()` (a hard transition). Never introduce a third state.

### 6. Widen with an optional trailing parameter
When an Action needs to do a bit more, add a new **optional trailing** parameter; every existing caller that doesn't pass it stays 100% unchanged. This is the most-used pattern in the entire project.

### 7. An "optional" MCP input field is simply left out of the schema
There's no `nullable: true`; an optional field just isn't declared, and is read defensively (`$input['x'] ?? null`) inside the Action.

### 8. Cross-module dependency goes through a Repository Interface only
Never depend on another module's Model or concrete Exception — only its domain Interface.

### 9. The calling module's own exception, never the target module's
A cross-module "does this exist" check always throws the *calling* module's own exception (even if both implement the same shared marker interface).

### 10. Two-way integration through a consumer-defined Interface
When module A needs something from module B, **A itself** defines the Interface and ships a harmless default; B (if installed) replaces it with a real implementation.

### 11. A Listener behaves like any other cross-module consumer
A Listener on another module's event re-reads fresh data through that module's Repository Interface — it never trusts the event payload alone.

### 12. Add an unrequested piece when the request implies it
If something (an exception, a Repository method) wasn't explicitly requested but skipping it means bypassing a convention or an ugly runtime failure, add it — always with a documented explanation.

### 13. A capability/permission name is always exactly three segments
`domain.resource.action`. If it comes out to 2 or 4 segments, it gets restructured.

### 14. Writing onto an older module's entity, only through a dedicated mutator
Like `Order::assignShipping()` — the mirror image of pattern #8, for writing instead of reading.

### 15. An in-memory registry for "choose between several named implementations"
`ConnectorRegistry`/`ShippingProviderRegistry`/`ChannelSenderRegistry` — always the same shape: `register()`/`get()`, bound as a singleton, populated in `boot()`.

### 16. A cross-cutting concern with no natural middleware pipeline is an explicit Action call
Like MCP rate limiting — since `mcp/*` routes have no middleware pipeline.

### 17. Retry-with-backoff lives inside the Action that owns the whole operation
Never inside the low-level Sender/Client it's retrying.

### 18. A Repository Interface can own its own child records
Like `TicketRepositoryInterface` also managing `TicketComment`.

### 19. A human-facing surface (Dashboard or demo) always reuses existing Actions
Never re-implements business logic for a new transport.

### 20. Reuse another module's own read-side building block for repeated aggregation
Like Analytics calling Reporting's Query Builders directly, instead of a parallel computation.

## Part B — Real-world gotchas (learned the hard way, in this project)

| # | Gotcha |
|---|---|
| 1 | `ServiceProvider::boot()` runs before the test database is migrated — so capability *descriptions* are always seeded, never registered in `boot()`. |
| 2 | A capability/permission name must be exactly 3 dot-separated segments. |
| 3 | JSON has no exact decimal type → every money field is always an integer (the smallest currency unit), never a float. |
| 4 | PHP is 8.2.12 — constructing an object inside a class constant isn't possible (needs 8.3+). |
| 5 | Registering a capability's **handler** and registering its **description** are two separate steps — forgetting the seeder produces a "no handler found" error even though the ServiceProvider is correctly wired. |
| 6 | `orders.agent_id` is a real, non-nullable foreign key — tests that place orders need a real registered Agent row, not an arbitrary number like `1`. |
| 7 | Inventory reservation math only balances if you know which phase you're in — always assert both `quantityOnHand()` and `quantityReserved()` in tests, not just `available()`. |
| 8 | `OrderItem`/`Discount` have no `id` field on their Domain entity — because nothing ever looks one up by its own id. |
| 9 | A Connector's dependency is injected exactly once, at `boot()` — rebinding the Interface later in a test has no effect; you must register a fresh instance directly into the registry. |
| 10 | A literal `*/` inside a docblock's prose silently closes the comment early, turning the rest into real (broken) PHP. |
| 11 | A translation key must always carry its group prefix: `t('messages.dashboard.title')`, never `t('dashboard.title')`. |
| 12 | `?->` only protects the method-call step, never the array-access step before it — the safe form is `($arr[0] ?? null)?->method()`. |

## Why these patterns matter

Every one of these patterns came out of a **real experience** building this exact project — not abstract theory. Whenever you saw, in files 6 through 16, a decision "confirmed" or "rejected," that was the moment one of these 20 patterns or 12 gotchas was formed or applied.

If you ever want to add a new capability to this platform, the best approach is:
1. Follow the seven-step pattern for building a capability (file 3).
2. Review this file before writing code, to see which pattern applies.
3. If a big architectural decision is needed (like the forks in file 11), ask first, then build.

The next file is fully hands-on: how to install, run, and test this project on your own machine — and finally, watch the live demo.

---
← [The Showcase Demo](16-showcase-demo.md) | Next: [Install, Run and Test](18-install-run-and-test.md) →
