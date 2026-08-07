← [CRM, Finance, Workflows, Loyalty, Reporting](07-crm-finance-workflows-loyalty-reporting.md) | Next: [i18n and the Admin Dashboard](09-i18n-and-admin-dashboard.md) →

# 8. Phase 4 (Part One) — Shipping, Notifications, and the Tech Debt Sprint

Phase 4 opened with a **Tech Debt Sprint** that resolved several real issues, then built the Shipping module, then a shipping-carrier connector, and finally the platform's first genuinely cross-cutting module: Notifications.

## The Tech Debt Sprint (between Stage 1 and Stage 2)

Seven items were fixed; the most important ones:

1. **A real inventory re-check bug**: the requested "fix" was actually the bug itself! `CheckInventoryAction` was already checking `available()`, which had already subtracted the same quantity into `quantityReserved` — meaning orders for more than half of on-hand stock would always fail. The real fix: a new method, `authorizeCommit()`, that checks `quantityOnHand` directly.
2. **A related race condition**, found while designing the fix above: two agents could simultaneously reserve more stock than actually existed. Fixed with row locking (`findByProductForUpdate`, i.e. `lockForUpdate()`) inside a transaction.
3. **Per-agent MCP rate limiting** (100/minute by default).
4. **A real Laravel scheduler** — the first time in the whole project `Schedule::command()` was used. This one capability activated two previously half-built mechanisms at once: loyalty point expiration, and abandoned-cart detection (`CartAbandonedListener`, written back in Phase 3 but with no trigger until now).

## Shipping — methods, shipments, tracking (Stage 1)

- Entities: `ShippingMethod`, `Shipment` (a state machine), `TrackingEvent`
- Shipment state machine: `pending → in_transit → delivered` (or `returned`/`exception`). Interesting detail: the `exception` status is **recoverable** — a real carrier problem can be resolved and the shipment resumes transit, unlike `delivered`/`returned`, which are terminal.
- Product weight is read from a free-form field (`Product.attributes['weight_grams']`), not a dedicated column — since this need was specific to Shipping.

### The first time a later module writes back onto an earlier module's own table

`Order` (owned by Commerce) gained three new optional fields (`shippingMethodId`, `shipmentId`, `shippingCost`) and one new mutator, `assignShipping()`. This is the only known exception to "only read another module's data, never write to it" — but even this exception happens through Commerce's own existing Repository Interface, never a direct database write.

## The shipping-carrier connector (Stage 2)

The Connector Pattern (seen for WooCommerce in file 6) was reused:

```
ShippingProviderInterface / ShippingProviderRegistry / MockShippingProviderAdapter
```

Only `mock` has a real implementation; `usps`/`fedex`/`dhl` are modeled only (waiting on real credentials).

A real bug found and fixed: `AddTrackingEventAction` had no way to record a historical timestamp (it always used "now") — a real problem when syncing with an external provider that returns older events. Fix: an optional trailing parameter was added (the same "widen with an optional trailing parameter" pattern again).

## Notifications — the platform's first genuinely cross-cutting module

Unlike every previous module (each serving one specific business capability), Notifications reacts to events from **three different modules at once**: `OrderWasPlaced` (Commerce), `ShipmentStatusChanged` (Shipping), `PointsWereEarned` (Loyalty) — each through that module's own Repository Interface, never its Model.

### The channel sender registry

The third time the "in-memory registry, register-by-name" pattern is built (after `ConnectorRegistry` and `ShippingProviderRegistry`):

```
ChannelSenderRegistry
├── EmailSender    → real (Laravel's own Mail facade)
├── WebhookSender   → real (Guzzle)
├── SmsSender       → an explicit stub (no real SMS credentials)
└── InAppSender      → a trivial no-op (the Notification record itself is the in-app alert)
```

### The project's first retry-with-exponential-backoff logic

`SendNotificationAction` retries up to 3 times (50ms, 100ms, 200ms apart), and instead of throwing, marks the state `Sent` or `Failed` — because a failed delivery channel is an ordinary business event, not a system error.

## Summary table

| Module/stage | Key feature |
|---|---|
| Tech Debt Sprint | Fixed the inventory bug + race condition + rate limit + a real scheduler |
| Shipping (Stage 1) | Shipment state machine + first write onto another module's table |
| Shipping Connector (Stage 2) | Second instance of the Connector Pattern |
| Notifications (Stage 3) | First cross-cutting module + third registry + first retry logic |

The "in-memory registry by name" pattern, seen three times in this file (Connector, ShippingProvider, ChannelSender), is one of the most reused architectural patterns in the whole project — wherever "choose between several named external implementations" was needed, this same shape was reused, never reinvented.

Next, we look at internationalization and the Admin Dashboard, which brings human authentication to the platform for the first time.

---
← [CRM, Finance, Workflows, Loyalty, Reporting](07-crm-finance-workflows-loyalty-reporting.md) | Next: [i18n and the Admin Dashboard](09-i18n-and-admin-dashboard.md) →
