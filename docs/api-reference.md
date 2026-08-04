# OpenCommerce Platform — MCP API Reference

> **Known stale as of Phase 5, Stage 5 (§7.25).** This table was generated
> once, during the Tech Debt Sprint (between Phase 4 Stages 1–2), and has
> not been regenerated since — it covers only the modules/capabilities
> that existed at that point (Commerce through Shipping, roughly). It does
> not yet include Notifications, Analytics, API Versioning's own v2
> surface, Performance Optimization, or any of Phase 5's own capabilities
> (Product Variants, Multi-warehouse Inventory, Bulk Operations, Advanced
> Discount Rules, Subscriptions). The platform now has 113 MCP
> capabilities across 10 modules — see `HANDOFF.md` §6 for the
> authoritative, current table (name/phase/permission/notes) until this
> file is regenerated in full from every module's own
> `Interfaces/MCP/*Capabilities.php` manifest, as its own intro paragraph
> below describes it should be.

## Overview

Every capability an AI Agent can call goes through one gateway, described
below. This document is generated from each module's own capability
manifest (`app/Modules/*/Interfaces/MCP/*Capabilities.php` — the single
source of truth the seeders themselves register from), not hand-copied
from anywhere else, so it stays accurate to what's actually wired.

- **Base URL**: `/mcp/v1`
- **Auth**: `Authorization: Bearer <agent-token>` on every request — no
  session, no cookie (see `docs/conventions.md`'s API Conventions).
- **Rate limit**: 100 requests/minute per Agent by default
  (`MCP_RATE_LIMIT_PER_MINUTE` in `.env`), enforced independently per Agent
  — one Agent hitting its limit never affects another.
- **Language**: pass `?lang=fa` (or `en`) as a query parameter, or an
  `Accept-Language` header — checked in that order, falling back to the
  calling Agent's own Tenant's configured default, then English. Only
  affects the error envelope's `localized_message` field (below); every
  capability's own `data` payload is unaffected. Supported: `en`, `fa`.

---

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/mcp/v1/capabilities` | Discover every capability the calling Agent's tenant has registered. |
| `POST` | `/mcp/v1/execute` | Execute one capability: `{"capability": "domain.resource.action", "input": {...}}`. |

---

## Response Envelope

Success:

```json
{
    "data": {},
    "message": "Success"
}
```

Error:

```json
{
    "error": {
        "code": "SOME_CODE",
        "message": "Human-readable reason",
        "localized_message": "Same reason, translated per the Language rules above"
    }
}
```

`message` is always the original, exception-specific text in English
(e.g. `"Order [42] does not exist."`) — never translated, so scripts
parsing it keep working regardless of caller language. `localized_message`
is a second, purely additive field: a generic, translated label for the
error *code* itself (e.g. `NOT_FOUND` → "منبع درخواستی یافت نشد" in
Farsi), meant for displaying to a human end user, not for programmatic
matching.

---

## Error Codes

| Code | HTTP Status | Meaning |
|---|---|---|
| `UNAUTHORIZED` | 401 | Missing/invalid Agent token, or Agent not active. |
| `FORBIDDEN` | 403 | Token is valid, but the Agent's roles don't grant the capability's required permission(s). |
| `NOT_FOUND` | 404 | Unknown capability, or a referenced resource doesn't exist (or belongs to another tenant). |
| `CONFLICT` | 409 | A legitimate business-rule rejection — insufficient stock, insufficient points, an invalid status transition, a duplicate that isn't allowed. |
| `VALIDATION_ERROR` | 422 | The `input` payload was missing a required field or was otherwise malformed. |
| `TOO_MANY_REQUESTS` | 429 | The calling Agent exceeded its per-minute rate limit. |
| `INTERNAL_ERROR` | 500 | Unexpected server-side failure. |

---

## Capabilities by Module

Every entry below lists the capability's declared `inputSchema` fields.
Fields not listed are genuinely optional (HANDOFF's own convention:
`MCPRequestValidationService` treats every declared field as required, so
optional fields are simply left out of the schema rather than marked
nullable) — check the linked Action or this doc's inline notes for what
an omitted field defaults to.

### Demo

Three sample capabilities proving the Capability Registry / MCP Gateway
mechanism end to end — not part of any business domain.

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `demo.tools.echo` | Echoes back the provided message with a timestamp. | `message: string` | `echo: string, timestamp: string` | `demo.echo.execute` |
| `demo.tools.time` | Returns the current server time. | — | `utc: string, unix: int` | `demo.time.read` |
| `demo.tools.calculator` | Performs a simple arithmetic operation (add, subtract, multiply, divide). | `operation: string, a: number, b: number` | `result: number` | `demo.calculator.execute` |

---

### Commerce

Product, Category, Cart, Inventory, Order, Customer, Payment, Coupon,
Discount, and the WooCommerce Connector.

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `commerce.product.search` | Search for products by query (active products only). | `query: string, limit: integer` | `products: array` | `commerce.products.read` |
| `commerce.cart.add` | Add a product to the calling Agent's cart. Reserves Inventory. | `product_id: integer, quantity: integer` | `cart: array, message: string` | `commerce.cart.manage` |
| `commerce.cart.get` | Get the calling Agent's current cart. | — | `cart: array` | `commerce.cart.read` |
| `commerce.order.place` | Place an Order from the calling Agent's own cart. No tax/discount applied — use `commerce.checkout.process` for real pricing. | `cart_id: integer` | `order: array` | `commerce.orders.create` |
| `commerce.order.get` | Get an Order by id (tenant-wide, not owner-scoped). | `order_id: integer` | `order: array` | `commerce.orders.read` |
| `commerce.order.list` | List the tenant's Orders, optionally filtered by status. | — | `orders: array` | `commerce.orders.read` |
| `commerce.customer.create` | Register a new Customer. | `first_name: string, last_name: string, email: string` | `customer: array` | `commerce.customers.create` |
| `commerce.customer.get` | Get a Customer by id. | `customer_id: integer` | `customer: array` | `commerce.customers.read` |
| `commerce.customer.list` | List the tenant's Customers, optionally filtered by status. | — | `customers: array` | `commerce.customers.read` |
| `commerce.checkout.calculate` | Preview the pricing for a cart, optionally with a coupon and a tax region — no side effects. | `cart_id: integer` | `pricing: array` | `commerce.checkout.read` |
| `commerce.checkout.process` | Charge payment for a cart and place the resulting Order (the full Cart → Payment → Order flow). | `cart_id: integer, payment_method: string` | `order: array, payment: array` | `commerce.checkout.create` |
| `commerce.payment.refund` | Refund a completed Payment, restoring its Order's Inventory. | `payment_id: integer` | `payment: array, message: string` | `commerce.payments.refund` |
| `commerce.coupon.create` | Create a new discount Coupon. | `code: string, discount_type: string, discount_value: integer` | `coupon: array` | `commerce.coupons.create` |
| `commerce.woocommerce.sync` | Upsert a page of WooCommerce products into the catalog by SKU. | — | `result: array` | `commerce.connectors.sync` |
| `commerce.woocommerce.get` | Live lookup of a single product straight from the WooCommerce Connector — not the local catalog. | `external_id: string` | `product: array` | `commerce.connectors.read` |

---

### CRM

Support Tickets, Ticket Comments, Customer Notes, and Tags.

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `crm.ticket.create` | Open a new support Ticket for a Customer. Validates `customer_id` against Commerce's own Customer records. | `customer_id: integer, subject: string, description: string` | `ticket: array` | `crm.tickets.create` |
| `crm.ticket.get` | Get a Ticket by id (cross-tenant id → 404, not 403). | `ticket_id: integer` | `ticket: array` | `crm.tickets.read` |
| `crm.ticket.list` | List the tenant's Tickets, optionally filtered by status or customer. | — | `tickets: array` | `crm.tickets.read` |
| `crm.comment.create` | Add a comment to an existing Ticket. | `ticket_id: integer, content: string` | `comment: array` | `crm.tickets.update` |
| `crm.note.create` | Add a note to a Customer. | `customer_id: integer, content: string` | `note: array` | `crm.customers.update` |

---

### Finance

Per-tenant tax rates and Invoices.

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `finance.tax.create` | Register a tax rate for a region (or the tenant-wide `DEFAULT` fallback). | `region: string, rate_percentage: integer` | `tax_rate: array` | `finance.tax.manage` |
| `finance.tax.get` | Get the tax rate configured for a region. | `region: string` | `tax_rate: array` | `finance.tax.read` |
| `finance.tax.list` | List the tenant's configured tax rates, optionally filtered by active state. | — | `tax_rates: array` | `finance.tax.read` |
| `finance.invoice.create` | Create an Invoice from an already-placed Order. | `order_id: integer` | `invoice: array` | `finance.invoices.create` |
| `finance.invoice.issue` | Issue a draft Invoice (Draft → Issued only). | `invoice_id: integer` | `invoice: array` | `finance.invoices.manage` |
| `finance.invoice.get` | Get an Invoice by id. | `invoice_id: integer` | `invoice: array` | `finance.invoices.read` |
| `finance.invoice.list` | List the tenant's Invoices, optionally filtered by status or customer. | — | `invoices: array` | `finance.invoices.read` |
| `finance.tax.calculate` | Calculate the tax and total for a given amount in a given region — strict, no fallback; an unconfigured region 404s. | `amount: integer, currency: string, region: string` | `tax_amount: integer, total_amount: integer` | `finance.tax.read` |

---

### Workflows

Event-driven automation: "when X happens and Y is true, do Z."

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `workflow.definition.create` | Create a Workflow: an event type, a set of matching rules (AND-combined), and the actions to run when they all match. Requires ≥1 rule and ≥1 action. | `name: string, event_type: string, rules: array, actions: array` | `workflow: array` | `workflow.definitions.manage` |
| `workflow.definition.get` | Get a Workflow by id. | `workflow_id: integer` | `workflow: array` | `workflow.definitions.read` |
| `workflow.definition.list` | List the tenant's Workflows, optionally filtered by status or event type. | — | `workflows: array` | `workflow.definitions.read` |
| `workflow.event.trigger` | Raise an event and run every active, matching Workflow registered for it — the same code path Domain Event Listeners call internally. | `event_type: string, event_data: object` | `triggered_count: integer, workflows: array` | `workflow.definitions.execute` |
| `workflow.log.list` | List the tenant's Workflow trigger history, optionally filtered by workflow. | — | `logs: array` | `workflow.definitions.read` |

`event_type` accepts `inventory_low` (wired to `InventoryLowListener`),
`cart_abandoned` (wired to `CartAbandonedListener`, triggered by the
scheduled `commerce:check-abandoned-carts` command), or
`order_high_value` (modeled, not yet wired to any Listener).

---

### Loyalty

Points, Rewards, and Redemptions.

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `loyalty.account.get` | Get a Customer's LoyaltyAccount — strict lookup, 404 if none exists yet. | `customer_id: integer` | `account: array` | `loyalty.accounts.read` |
| `loyalty.account.create` | Open a LoyaltyAccount for a Customer — 409 if one already exists. | `customer_id: integer` | `account: array` | `loyalty.accounts.create` |
| `loyalty.points.earn` | Credit points to a Customer's LoyaltyAccount — find-or-create, unlike `.get`. | `customer_id: integer, points: integer, description: string` | `transaction: array, new_balance: integer` | `loyalty.points.manage` |
| `loyalty.points.redeem` | Spend points on a Reward — `points` must match the Reward's own `points_required` exactly. | `customer_id: integer, points: integer, reward_id: integer` | `redemption: array, new_balance: integer` | `loyalty.points.redeem` |
| `loyalty.reward.create` | Define a Reward Customers can spend points on. | `name: string, reward_type: string, points_required: integer` | `reward: array` | `loyalty.rewards.manage` |
| `loyalty.reward.get` | Get a Reward by id. | `reward_id: integer` | `reward: array` | `loyalty.rewards.read` |
| `loyalty.reward.list` | List the tenant's Rewards, optionally filtered by `is_active`. | — | `rewards: array` | `loyalty.rewards.read` |
| `loyalty.transaction.list` | List a Customer's PointTransaction history. | `customer_id: integer` | `transactions: array` | `loyalty.transactions.read` |

Point expiration runs automatically via the scheduled
`loyalty:expire-points` command (daily, 02:00) — not itself an MCP
capability.

---

### Reporting

Read-only analytics — the platform's only CQRS-style read model, querying
Commerce's/Loyalty's own tables directly for aggregate performance
(`docs/architecture.md`/`docs/decisions.md` have the full reasoning).

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `report.sales.generate` | Total sales, total orders, average order value, sales by day. Excludes Cancelled/Refunded orders. | `start_date: date, end_date: date` | `report: array` | `reporting.sales.read` |
| `report.products.top` | Top products by quantity sold. | `start_date: date, end_date: date` | `report: array` | `reporting.products.read` |
| `report.customers.top` | Top customers by total spent. | `start_date: date, end_date: date` | `report: array` | `reporting.customers.read` |
| `report.revenue.generate` | Gross revenue, tax collected, discounts applied, net revenue (excludes tax). Only counts Orders with ≥1 completed Payment. | `start_date: date, end_date: date` | `report: array` | `reporting.revenue.read` |
| `report.loyalty.generate` | Points earned/redeemed, currently-active accounts, top earners. | `start_date: date, end_date: date` | `report: array` | `reporting.loyalty.read` |

---

### Shipping

ShippingMethods, Shipments, and TrackingEvents — plus (Phase 4 Stage 2) a
real Connector Pattern integration with an external shipping provider
(`mock` by default; `usps`/`fedex`/`dhl` are modeled, unimplemented future
intents).

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `shipping.method.create` | Define a ShippingMethod: a base rate, a per-kg rate, and an estimated delivery window. | `name: string, base_rate: integer, rate_per_kg: integer, estimated_days_min: integer, estimated_days_max: integer` | `method: array` | `shipping.methods.create` |
| `shipping.method.list` | List the tenant's ShippingMethods, optionally filtered by `is_active`. | — | `methods: array` | `shipping.methods.read` |
| `shipping.rate.calculate` | Preview the shipping cost for a given weight under a given ShippingMethod (local calculator) — no side effects. | `shipping_method_id: integer, weight_grams: integer` | `rate: array` | `shipping.rates.read` |
| `shipping.shipment.create` | Fulfill an Order with a real Shipment: weighs its Products, prices it, generates a tracking number, and records the assignment on the Order. | `order_id: integer, shipping_method_id: integer` | `shipment: array` | `shipping.shipments.create` |
| `shipping.shipment.get` | Get a Shipment by id. | `shipment_id: integer` | `shipment: array` | `shipping.shipments.read` |
| `shipping.shipment.list` | List the tenant's Shipments, optionally filtered by status or `order_id`. | — | `shipments: array` | `shipping.shipments.read` |
| `shipping.shipment.transition` | Transition a Shipment's authoritative status (`pending` → `in_transit` → `delivered`, or `returned`/`exception`). | `shipment_id: integer, status: string` | `shipment: array` | `shipping.shipments.update` |
| `shipping.tracking.add` | Append one entry to a Shipment's tracking history — does not itself change the Shipment's own status. | `shipment_id: integer, status: string, description: string` | `event: array` | `shipping.shipments.update` |
| `shipping.provider.rates` | Get live rates for a weight/destination from an external shipping provider. | `weight_grams: integer, destination: object {street, city, state?, postalCode?, country}` | `rates: array` | `shipping.providers.read` |
| `shipping.provider.fulfill` | Hand an already-created Shipment to an external provider, recording its own tracking number onto it. | `shipment_id: integer` | `provider_shipment: array` | `shipping.providers.create` |
| `shipping.tracking.sync` | Pull tracking updates from an external provider and fold in whatever is genuinely new — idempotent, updates the Shipment's own status if the newest event is a legal transition. Looks the Shipment up by its own internal `tracking_number` (from `shipping.shipment.create`), not the provider's. | `tracking_number: string` | `events: array, synced_count: integer` | `shipping.providers.sync` |

`provider` is optional on all three provider capabilities — omitted, it
defaults to `SHIPPING_PROVIDER` (`.env`, default `mock`).

---

### Notifications

The platform's first genuinely cross-cutting module — reacts to events
from Shipping, Commerce, and Loyalty, and can also be called directly.
Four channels: `email` (real, via Laravel's own mailer), `webhook` (real,
plain HTTP POST), `sms` (an explicit stub — no real gateway exists yet),
`in_app` (the persisted Notification row itself).

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `notification.message.send` | Render the active Template for a type+channel with `variables` and send it directly to a raw recipient (email/phone/URL). No Preference check — there is no recipient id to check one against. | `type: string, recipient: string, channel: string, variables: object` | `notification: array` | `notifications.messages.send` |
| `notification.template.create` | Create a NotificationTemplate: a subject/body pair with `{{variable}}` placeholders for a given type+channel. | `type: string, channel: string, subject_template: string, body_template: string` | `template: array` | `notifications.templates.manage` |
| `notification.template.get` | Get a NotificationTemplate by id. | `template_id: integer` | `template: array` | `notifications.templates.read` |
| `notification.template.list` | List the tenant's NotificationTemplates, optionally filtered by type or channel. | — | `templates: array` | `notifications.templates.read` |
| `notification.channel.configure` | Configure (create or update) a tenant's NotificationChannel settings. | `channel: string, config: object` | `channel: array` | `notifications.channels.manage` |
| `notification.message.get` | Get a sent Notification by id. | `notification_id: integer` | `notification: array` | `notifications.messages.read` |
| `notification.message.list` | List the tenant's sent Notifications, optionally filtered by type or status. | — | `notifications: array` | `notifications.messages.read` |
| `notification.preference.set` | Enable or disable one notification type+channel combination for a recipient (`customer` or `agent`). | `recipient_type: string, recipient_id: integer, notification_type: string, channel: string, is_enabled: boolean` | `preference: array` | `notifications.preferences.manage` |

`notification_type`/`type` accepts `order_placed` (wired to
`OrderPlacedNotificationListener`), `shipment_status_changed` (wired to
`ShipmentStatusChangedListener`), `points_earned` (wired to
`PointsEarnedListener`, always `in_app`), or `ticket_created` (modeled,
not yet wired to any Listener). Preferences are opt-*out*: no Preference
row at all means "send" — a recipient only ever suppresses a type+channel
by explicitly disabling it.

---

### Analytics

KPIs, daily Snapshots, and Dashboard/export data — reuses Reporting's own
Query Builders for every KPI Reporting already aggregates (Revenue, Total
Orders, Top Products, Loyalty points/accounts); only Conversion Rate,
Revenue Growth Rate, New/Total Customers, Customer Retention Rate/Lifetime
Value, and Low Stock Products are computed independently.

| Capability | Description | Input | Output | Permission |
|---|---|---|---|---|
| `analytics.kpi.calculate` | Calculate a single KPI for a date range — cached 1 hour. | `kpi_type: string, time_period: string, start_date: date, end_date: date` | `kpi: array` | `analytics.kpis.read` |
| `analytics.kpi.list` | List the tenant's own KPI definitions (created lazily on first calculation). | — | `kpis: array` | `analytics.kpis.read` |
| `analytics.dashboard.stats` | The 6 headline KPIs + Top 5 Products + 5 most recent Orders, for the current calendar month. | — | `stats: array` | `analytics.dashboard.read` |
| `analytics.snapshot.generate` | Compute and upsert today's AnalyticsSnapshot for the tenant. | — | `snapshot: array` | `analytics.snapshots.create` |
| `analytics.report.export` | Export the 6-KPI summary report as CSV or PDF — returns a downloadable URL (only `report_type: kpi_summary` is implemented so far). | `report_type: string, format: string, start_date: date, end_date: date` | `file_url: string` | `analytics.reports.export` |

`kpi_type` accepts `revenue`, `revenue_growth_rate`, `total_orders`,
`average_order_value`, `total_customers`, `new_customers`,
`conversion_rate`, `top_products`, `low_stock_products`,
`loyalty_points_earned`, `loyalty_points_redeemed`,
`active_loyalty_accounts`, `customer_retention_rate`, or
`customer_lifetime_value`. `time_period` accepts `hourly`, `daily`,
`weekly`, `monthly`, or `yearly` — it only labels the computed
`KPIValue`'s own period bucket, it does not itself change what
`start_date`/`end_date` you pass. None of these 5 capabilities accept a
`tenant_id` input — like every other capability in this API, they scope
exclusively to the calling Agent's own tenant.

Point-in-time daily Snapshots also run automatically via the scheduled
`analytics:generate-snapshot` command (daily, 01:00) — not itself an MCP
capability.

---

## Capabilities Not Yet Wired to MCP

The following Actions are fully built and tested but have no MCP
capability registered for them yet (HANDOFF §6/§8 has the full reasoning
for each — mostly "not among what was requested that stage," not
unfinished work): `RemoveFromCartAction`, `UpdateCartItemQuantityAction`,
`ClearCartAction`, `CancelOrderAction`, `UpdateOrderStatusAction`,
`GetCustomerOrdersAction`, `GetPaymentAction`, CRM's `UpdateTicketAction`/
`GetCustomerNotesAction`/`CreateTagAction`/`AssignTagToCustomerAction`,
Finance's `UpdateTaxRateAction`, Workflows' `UpdateWorkflowAction`,
Loyalty's `ExpirePointsAction` (runs on a schedule instead, see above),
and Reporting's `GetReportAction`/`ListReportsAction`.

---

## Getting an Agent Token

See `packages/opencommerce-sdk/README.md`'s Quick Start section, or any
`registerAgentWithPermissions()` helper in `tests/Feature/*/`*`CapabilityTest.php`
for the full Tenant → Organization → Agent → Role → Permission → Token
chain needed to call a capability end to end.
