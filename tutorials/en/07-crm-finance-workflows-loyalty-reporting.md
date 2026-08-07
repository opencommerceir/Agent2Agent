← [The Commerce Module](06-commerce-module.md) | Next: [Shipping and Notifications](08-shipping-and-notifications.md) →

# 7. Phase 3 — CRM, Finance, Workflows, Loyalty, Reporting

Phase 3 is the first time the platform has **more than one domain module**. The "cross-module dependency" pattern is used for real here for the first time, and it needs to be well understood, because it repeats in every later module.

## The golden rule of cross-module dependency

> A module may only depend on another module's **Repository Interface** — never its Eloquent Model or its concrete Exception.

Example: when CRM needs to verify a `customer_id` is real, it depends on `Commerce\Domain\Repositories\CustomerRepositoryInterface` — never on Commerce's own Eloquent class.

**A related sub-rule:** when that lookup fails, the calling module always throws **its own** exception, never the other module's. CRM has its own dedicated `CustomerNotFoundException`, separate from Commerce's — both implement the same shared Marker Interface (file 4) and both end up producing a 404, but they remain fully independent classes.

## CRM — support tickets

- Entities: `Ticket`, `TicketComment`, `CustomerNote`, `Tag`
- `Ticket`'s state machine is stricter than `Order`'s: only **strictly forward** movement is allowed (`Open → InProgress → Resolved → Closed`) — even re-targeting the current status is rejected.
- 4 of CRM's 9 Actions are deliberately not wired to MCP yet (built and tested, just never requested).

## Finance — tax rates and invoices

- Entities: `TaxRate`, `Invoice`, `InvoiceItem`
- Finance has its own independent `Money` — a **deliberate duplicate**, not a shared class. Why? Because depending on another module's *concrete* class is exactly the coupling the golden rule above exists to prevent. About 40 lines of duplicated code is a much smaller cost than a wrong inter-module dependency.

### The platform's first two-way integration

This is the first time two modules depend on each other in **both directions**:
- Finance depends on Commerce's `OrderRepositoryInterface` (to build invoices from orders).
- But Commerce also needs a real tax rate from Finance to compute real checkout tax!

The elegant solution: **Commerce itself defines an Interface** (`TaxRateProviderInterface`), ships a harmless default no-op implementation (`NullTaxRateProvider`), and Finance (if installed) replaces it with a real implementation. In other words: **the module that needs something defines the shape of what it needs** — never the module that provides something reaching into the consumer. This is exactly the same shape `PaymentGatewayInterface` already established.

## Workflows — event-driven automation

- Entities: `Workflow`, `WorkflowRule`, `WorkflowAction`, `WorkflowLog`
- A workflow's rules are combined with **AND** logic — every rule must match.
- **The platform's first real Listener on a domain event**: `InventoryLowListener` listens for a new event, `InventoryWasCommitted`.

### An important pattern: a Listener only carries an ID, not the full data

`InventoryWasCommitted` only carries identifiers; the Listener re-reads fresh data through a Repository Interface — it never trusts whatever the event payload "thinks" it has. Real state is always re-read.

## Loyalty — points, rewards, redemptions

- Entities: `LoyaltyAccount`, `PointTransaction`, `Reward`, `Redemption`
- Formula: $1 = 100 cents = 1 loyalty point (integer division, always rounds down)
- **The second real Listener**: `OrderPlacedListener` listens for `OrderWasPlaced` — this time, since the event already carries the full `Order` entity, no extra Repository lookup was needed.
- `loyalty.points.earn` creates a loyalty account if none exists (Find-or-Create); `loyalty.account.get` does a strict lookup (404 if missing) — this difference is **deliberate**, not an inconsistency.

## Reporting — read-only analytics

- Entities: `Report` (a saved definition), `ReportResult` (a computed output)
- **The platform's first genuinely read-only module** — the only thing it ever writes is the record of having run a report.

### The first documented exception to the golden rule

To compute aggregate statistics (SUM/COUNT/GROUP BY), Reporting reads Commerce's/Loyalty's Eloquent Models **directly**, inside dedicated **Query Builder** classes (`Infrastructure/Queries/*`), instead of going through the Repository Interface. Why is this exception safe?

- These classes only ever **SELECT**, never write.
- It's confined to exactly 5 classes.
- Going through a Repository Interface for this would mean fetching thousands of full Entities and summing them in a PHP loop — precisely what the project's own rules forbid.

This exception (acting like a CQRS Read Model) is reused a second time by the Analytics module in file 10.

## Quick comparison of the five Phase 3 modules

| Module | Distinguishing feature |
|---|---|
| CRM | First one-directional cross-module dependency |
| Finance | First two-way dependency (consumer-defined Interface) |
| Workflows | First real Listener on a domain event |
| Loyalty | Second Listener + Find-or-Create vs. strict lookup |
| Reporting | First documented exception to "Interface only" |

These five patterns (one-directional dependency, two-way dependency, Listener, Find-or-Create, the Query Builder exception) repeat throughout every module built after this. Understanding them here makes understanding the rest of the project much easier.

---
← [The Commerce Module](06-commerce-module.md) | Next: [Shipping and Notifications](08-shipping-and-notifications.md) →
