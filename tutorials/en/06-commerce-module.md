← [The MCP Gateway](05-mcp-gateway.md) | Next: [CRM, Finance, Workflows, Loyalty, Reporting](07-crm-finance-workflows-loyalty-reporting.md) →

# 6. The Commerce Module — the Platform's Commercial Heart (Phase 2)

`Commerce` is the first and largest domain module — 23 domain entities, roughly 81 Actions, 19 Repositories, and 58 MCP capabilities. This file walks through the six stages that built it.

## Stage 1 — Product and Category management

- Entities: `Product`, `Category`
- Value Objects: `Money` (an integer in the smallest currency unit + an ISO currency code — **never a float**, since JSON has no exact decimal type), `SKU` (normalized to uppercase), `ProductStatus`
- Rule: `commerce.product.search` only ever returns **Active** products — a business decision, written in the Action, not the Repository (a Repository must never encode a business rule).
- SKU is unique per tenant.

## Stage 2 — Cart and Inventory management

- Entities: `Cart`, `CartItem`, `Inventory`
- **This is the stage where `AuthContext` was born** — and where, once, `App\Modules\Commerce` was accidentally imported into `App\Core`, and caught and fixed the same moment. This is one of the project's foundational lessons.

### The two-phase Inventory lifecycle — one of the most important concepts in the whole project

Inventory always has two distinct states:

```
reserve() / release()   ← a soft hold. When something is added to a cart.
                           Never decreases quantity_on_hand.
commit() / restore()    ← a hard transition. When an order is finalized/cancelled.
                           commit() actually decreases quantity_on_hand and lifts the soft hold.
                           restore() is commit()'s exact inverse.
```

These four methods live on the `Inventory` entity itself, and **a third state must never be introduced** — everything must fit into either "held in a cart" or "actually sold."

## Stage 3 — Order management

- Entities: `Order`, `OrderItem` (fully immutable, no mutator methods at all)
- `OrderNumber` in the shape `ORD-YYYYMMDD-XXXXX` (random suffix + collision check)
- `PlaceOrderAction` wraps the whole Cart→Inventory→Order flow in one `DB::transaction`.
- Some statuses are terminal (`Cancelled`/`Refunded`/`Delivered`) and cannot be reached through the generic `changeStatus()` method — only dedicated methods like `cancel()`/`refund()` can reach them, so their inventory side effects can never be bypassed.

## Stage 4 — Customer management

- Entity: `Customer`
- Value Objects: `Email`, `Address`, `CustomerStatus`
- `orders.customer_id` was added as an optional, additive column (orders didn't originally have a concept of a customer) — the first example of an important pattern: **widening with an optional trailing parameter** instead of branching or duplicating an Action.

## Stage 5 — Checkout and payment

- Entities: `Payment`, `Coupon`, `Discount`
- Domain services: `PricingService` (a single formula owner: `Total = Subtotal + Tax − Discount`) and `CouponValidationService`

### The payment-before-order flow

```
Cart → pricing calculation → coupon validation → PaymentGatewayInterface::charge()
   → only on success → PlaceOrderAction → persist Payment → apply the coupon
```

This is why `payments.order_id` is never null — a declined charge never reaches a point where either an Order or a Payment row is created.

`MockPaymentGateway` is still the only implementation of *this* synchronous `PaymentGatewayInterface::charge()` contract (the one `commerce.checkout.process` uses) — it always succeeds unless `simulate_failure: true` is explicitly passed, a clean way to test the decline path without any real network mocking. As of §7.37 (see file 21), there's also a second, parallel, **redirect-based** payment path with two real implementations — Zibal (Iranian) and Stripe (international) — built behind a separate `RedirectPaymentGatewayInterface`/`PaymentGatewayRegistry` pair specifically because a real gateway's "redirect the buyer, then verify server-to-server" flow doesn't fit this synchronous `charge()` shape at all. Both paths coexist unchanged; `MockPaymentGateway` and this synchronous flow are untouched by that addition.

## Stage 6 — Real connectors (WooCommerce)

This is where the **Connector Pattern** was implemented for real, for the first time:

```
ConnectorInterface / ProductConnectorInterface   ← the contract
ConnectorRegistry                                 ← an in-memory registry, register-by-name
WooCommerceProductConnector                        ← a real implementation (Guzzle)
MockProductConnector                                ← a simulated implementation (the test default)
```

This pattern gets reused later in Shipping and Notifications (file 8) — a standard shape for "choosing between multiple external implementations by name."

An important technical detail: a Connector's dependency is injected exactly **once**, at `boot()` time. If you need a different implementation in a test, rebinding the Interface alone won't affect an already-registered connector — you have to register a fresh instance directly.

## Summary table of Commerce's core entities

| Entity | Role |
|---|---|
| Product / Category | The product catalog |
| Cart / CartItem | The active cart (a mutable preview) |
| Inventory | Two-phase inventory |
| Order / OrderItem | The final order (an immutable record) |
| Customer | The customer |
| Payment / Coupon / Discount | Payment and discounting |

## A few key business decisions to know

- `commerce.order.place` **never** applies tax or discounts — only `commerce.checkout.process` computes real pricing. That means there are, today, two ways to place an order with meaningfully different pricing behavior — a documented, known piece of technical debt (file 19).
- A wrong coupon code returns a 409 (Conflict), not a 404 — because `InvalidCouponException` is reused both for "the coupon doesn't exist at all" and "the coupon exists but can't be used right now."

Commerce is the foundation for everything we see later — from Advanced Commerce (file 11) to the AI agents that ultimately call these exact same Commerce capabilities (file 12 onward).

---
← [The MCP Gateway](05-mcp-gateway.md) | Next: [CRM, Finance, Workflows, Loyalty, Reporting](07-crm-finance-workflows-loyalty-reporting.md) →
