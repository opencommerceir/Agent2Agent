← [Analytics and API Versioning](10-analytics-and-api-versioning.md) | Next: [AI Agents: the Agent Orchestrator](12-agent-orchestrator.md) →

# 11. Phase 5 — Advanced Commerce

Phase 5 adds five advanced capabilities to Commerce. It's also methodologically important: this is the first time the team formally worked as an **"Orchestrator Agent"** — building a shared foundation first and testing it, then splitting the remaining work into genuinely independent slices and running them in parallel (via two sub-agents).

## Stage 1 — Product Variants

**The biggest architectural fork of this phase.** The original request wanted a `stock_quantity` column directly on `product_variants` — completely independent of the two-phase inventory cycle from file 6. Building it that way would have re-created a parallel, unsafe inventory mechanism, without soft reservation or concurrency protection — exactly the problem the Tech Debt Sprint (file 8) had already fixed once.

This was raised with the user before writing any code, and confirmed: instead of a new column, **`Inventory` itself was extended** — an optional `variantId` (the "optional trailing parameter" pattern again) was threaded through every existing inventory path (reserve, commit, restore). The result: every product variant uses the exact same safe, tested lifecycle; `product_variants` has no inventory column at all.

A genuinely new capability was needed: `Inventory::setQuantityOnHand()` — an absolute override ("there are now exactly N units") for initial stock provisioning, separate from the relative reserve/commit operations.

## Stage 2 — Multi-Warehouse Inventory (the first real run of the Orchestrator method)

- Entities: `Warehouse`, `WarehouseTransfer`, `WarehouseTransferItem`
- The same "widen with an optional parameter" pattern repeated: `Inventory` also gained a `warehouseId`.
- A new method, `receiveStock()` — deliberately kept **separate** from `restore()`, since they mean different things: `restore()` means "reversing a prior commit," while `receiveStock()` means "genuinely new stock just arrived" (e.g. from a warehouse transfer).
- The transfer workflow (`Request → Approve → Complete`) uses the exact same reserve/commit inventory cycle, not a separate mechanism.
- An interesting integration with Shipping: shipping cost is calculated based on the nearest warehouse — `CalculateShippingRateAction` directly constructor-injects Commerce's `FindNearestWarehouseAction`, with no Interface between them, since this is a read-only operation with no side effect.

## Stage 3 — Bulk Operations (the project's first real background Job)

- Entities: `BulkOperation`, `BulkOperationItem`
- The project's first real background Job — before this, no module had a Jobs folder at all.

### The golden rule for writing a Job in this codebase

A Job's constructor only ever takes primitives (IDs, strings, plain arrays) — **never** a Repository or a Service. Why? Because a Job's constructor arguments get serialized onto the queue. Any real dependency is instead injected directly into `handle()` (exactly like a controller method).

Processing happens in batches of 100, each batch inside its own `DB::transaction()`. If one row fails, only that row is marked as failed and the rest of the batch continues — only a genuinely unrecoverable error rolls back the whole batch.

## Stage 4 — Advanced Discount Rules

**The second big architectural fork of this phase.** The request wanted a new `AppliedDiscount` entity that, read literally, duplicated exactly what `Discount` (from Phase 2) already did. The solution: reuse the existing "Cart/Order duality" pattern (a mutable preview vs. an immutable final record) one level up — `AppliedDiscount` is Cart-only (no `order_id`), while `Discount` (the Order side) only gained one new optional field (`discountRuleId`).

### The discount rule combination logic (Stackability)

Read literally from the request's own text, not common intuition: `Stackable` rules only combine with other `Stackable` rules, `Exclusive` rules only combine with other `Exclusive` rules — not "exclusive means alone." This exact rule was unit-tested against the request's own numeric worked example before it was ever wired to any Action.

### A deliberately documented scope boundary

Automatic, coupon-less Cart-level discounts do not automatically reach the real checkout total — only a coupon explicitly linked to a discount rule reaches real Checkout. This is a deliberate decision that kept the change to Checkout's most heavily-tested Actions small and additive.

## Stage 5 — Subscriptions and Recurring Orders (the last stage of Phase 5)

- Entities: `SubscriptionPlan`, `Subscription`, `SubscriptionInvoice`
- State machine: `Trial → Active → Paused → PastDue → Cancelled`
- Billing charges directly through the same `PaymentGatewayInterface` Checkout uses — **not** through a Cart→Order→Payment path, since a subscription plan isn't a product with inventory.

### A real bug only found at the seam between two parallel pieces

This stage had two independent halves: subscription lifecycle (pause/resume/cancel/upgrade) and the recurring billing engine (retries, Jobs, the scheduler). Both were correctly tested on their own. But the final integration review found a real bug: when a subscription reached `PastDue` on its very first charge (with no retry grace, per business rule), a later retry attempt tried to mark it `PastDue` a second time — and the state machine, as originally written, didn't tolerate that self-transition, so the entire transaction rolled back silently inside a Job's own `try/catch` wrapper. The fix: add the same "self-transition tolerance" that `renew()` already had for `Active → Active`.

**The lesson here:** a bug that only appears at the seam between two correctly-built, correctly-tested parallel pieces is a real risk of parallel development — and it's only caught by a real, end-to-end integration test that follows the whole chain.

## Summary table of Phase 5

| Stage | Main challenge | Solution |
|---|---|---|
| Product Variants | A proposed parallel inventory column | Extend `Inventory` with `variantId` |
| Multi-Warehouse | First real Orchestrator run | Shared foundation → two parallel sub-agents |
| Bulk Operations | First background Job | Primitive constructor, dependencies in `handle()` |
| Advanced Discounts | A proposed parallel discount entity | Reuse the existing Cart/Order duality |
| Subscriptions | A bug at the seam of two parallel pieces | Self-transition tolerance |

By the end of Phase 5, Commerce is effectively complete — 885 tests, zero regressions. Now it's time for the most interesting part of the project: the layer that lets all these capabilities be driven by an **AI agent**.

---
← [Analytics and API Versioning](10-analytics-and-api-versioning.md) | Next: [AI Agents: the Agent Orchestrator](12-agent-orchestrator.md) →
