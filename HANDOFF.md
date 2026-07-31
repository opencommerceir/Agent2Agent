# OpenCommerce Platform — Session Handoff

**Status: Phase 1 (Core + MCP Gateway) and Phase 2 (Commerce, all 6
Stages) are complete. Phase 3 (Domain Expansion) has begun — Stage 1,
CRM Foundation (Tickets, Ticket Comments, Customer Notes, Tags), is
complete. 314 tests passing, zero known regressions. Next up: whatever
Phase 3's next module turns out to be (Finance? AI Workflows?), or any
deferred item in §8/§9 (a real Shopify Connector, per-tenant tax config,
wiring CRM's remaining un-exposed Actions) if more polish is wanted
first.**

This file is a working-state snapshot for picking up development in a new
session. It assumes you've already read `CLAUDE.md` and `docs/*.md` (the
project's standing rules) — this document is "what actually got built and
why," not a repeat of the architecture doctrine.

If you are a fresh Claude Code session reading this: read this whole file
before touching code. Section 9 (Phase 2 detail) and section 10 (technical
debt) are the parts most likely to save you from repeating a mistake or
re-deciding something that was already deliberately decided.

---

## 1. What exists right now

### `app/Core/` — Identity, Tenancy, Registry, Permissions, MCP Gateway

Unchanged in shape since Phase 1, with one deliberate widening made during
Phase 2 (see §7.2 and §3): the Capability execution path now threads an
`AuthContext` (tenantId + agentId) into every handler, and two marker
interfaces (`NotFoundExceptionInterface`, `ConflictExceptionInterface`) were
added so Domain Modules can opt an exception into the MCP error envelope
*without Core ever importing a Domain Module's class*.

| Sub-area | Key classes | Notes |
|---|---|---|
| Tenant | `Domain/Entities/Tenant.php`, `Application/Actions/CreateTenantAction.php` | Unchanged since Phase 1. |
| Organization | `Domain/Entities/Organization.php`, `OrganizationMember.php` | Unchanged since Phase 1. |
| Agent Registry | `Domain/Entities/Agent.php`, `AgentToken.php`, related Actions | Unchanged since Phase 1. |
| Permission System | `Domain/Entities/{Permission,Role,MemberRole}.php`, `CheckPermissionAction` | Unchanged since Phase 1. |
| Capability Registry | `Domain/Entities/Capability.php`, related Actions | Unchanged since Phase 1. Still strict 3-segment `domain.resource.action` names. |
| **Capability Execution** | `Application/Services/CapabilityHandlerRegistry.php`, `CapabilityExecutionService.php` | **Handler contract changed in Phase 2**: `callable(array $input, AuthContext $context): array` — was `callable(array $input): array` in Phase 1, then briefly `callable(array $input, int $tenantId): array` early in Phase 2 before Cart ownership needed the Agent's own id too. See §9.2/§9.3 for the full history — do not re-litigate this, it was already widened twice and settled. |
| **AuthContext** | `Application/DTOs/AuthContext.php` | New in Phase 2. `{tenantId: int, agentId: int}`, built via `AuthContext::forAgent(AgentData $agent)`. Passed explicitly into every handler — never resolved from a container/global. Every Commerce Domain Repository interface and Application Action still takes plain `int $tenantId`/`int $agentId` scalars, not `AuthContext` itself — only `CommerceServiceProvider`'s handler closures unpack it. Do not push `AuthContext` down into Domain/Application signatures; that would invert the dependency direction (Domain must not depend on an Application-layer DTO). |
| **Marker interfaces** | `Domain/Exceptions/Contracts/{NotFoundExceptionInterface,ConflictExceptionInterface}.php` | New in Phase 2. `MCPExceptionHandler` matches on these interfaces (404 / 409) in addition to its own concrete exception classes — this is how Commerce's exceptions (`ProductNotFoundException`, `InsufficientInventoryException`, etc.) get mapped to the right HTTP status **without Core importing anything from `App\Modules\Commerce`**. Any new Domain Module exception that should map to 404/409 implements one of these; Core is never touched again for this. |
| MCP Gateway | `Interfaces/HTTP/Controllers/MCP/*`, `Exceptions/MCPExceptionHandler.php` | Routes unchanged: `POST /mcp/v1/execute`, `GET /mcp/v1/capabilities`. Error envelope gained a new code: `CONFLICT` (409), used for business-rule rejections (insufficient stock, payment declined, invalid coupon, invalid order-status transition) that are neither a validation error nor a missing resource. |

### `app/Modules/Commerce/` — **no longer a skeleton. Product, Category, Cart, Inventory, Order, Customer, Payment, Coupon, Discount are all real, tested, and MCP-reachable — and Stage 6 added the first real external Connector.**

See §7 for the full stage-by-stage breakdown (what was built, in what order,
and why). At a glance, the module now has 9 Domain Entities, ~28 Value
Objects/enums, 3 Domain Services, ~32 Application Actions, 9 Eloquent
Repositories, and 20 numbered migrations, backing 15 MCP capabilities.

`Domain/UCP/*` (the 6 normalized value objects for *external* connector
data — never persisted, never touched by any Stage 1–5 work) is unchanged
since Phase 1. `Infrastructure/Connectors/MockProductConnector.php` is
still there too (registered under the `'mock'` name), joined in Stage 6 by
`WooCommerceProductConnector` — a real `ProductConnectorInterface`
implementation backed by a real Guzzle HTTP client
(`Application/Services/WooCommerceClient`). No live WooCommerce store is
configured out of the box (same "needs live credentials to test honestly"
reasoning HANDOFF has always given) — `MockWooCommerceHttpClient`
(Infrastructure/Http) stands in for one in every test.

### `app/Modules/CRM/` — **new in Phase 3. Support Tickets, Ticket Comments, Customer Notes, and Tags — Phase 3's first Domain Module, built on Phase 1/2's infrastructure without changing either.**

See §7.7 for the full detail. 4 Domain Entities (`Ticket`, `TicketComment`,
`CustomerNote`, `Tag`), 3 Value Objects (`TicketStatus`, `TicketPriority`,
`TagName`), 4 domain events, 4 exceptions, 3 Repository interfaces, 9
Application Actions (only 5 wired to MCP — see §6), 4 Eloquent models, 3
Eloquent repositories, 5 migrations. Demonstrates the Module -> Module
dependency direction CLAUDE.md's "Infrastructure First" philosophy
implies but Phase 2 never had occasion to exercise: CRM depends on
Commerce's `CustomerRepositoryInterface` (a Domain-layer Interface) to
validate a `customer_id` exists, and never imports Commerce's
Infrastructure/Model classes or even Commerce's own exception types (see
CRM's own `CustomerNotFoundException` docblock).

### `app/Modules/Demo/` — unchanged since Phase 1

Same three demo capabilities. Its `DemoServiceProvider` handler closures were
updated twice (once per `CapabilityHandlerRegistry` contract widening) to
keep matching the shared handler signature — they still ignore the second
argument entirely, since Demo has no tenant-scoped data.

### `packages/opencommerce-sdk/` — unchanged since Phase 1

Nothing in Phase 2 touched the SDK. It still only knows about
`discoverCapabilities()` / `execute()` / `getCapability()` against the
generic MCP envelope — it has no Commerce-specific knowledge and doesn't
need any, since Commerce capabilities are just more capabilities from its
point of view.

---

## 2. Full module structure reference

```
app/Core/
├── Domain/{Entities,ValueObjects,Events,Repositories,Exceptions,Exceptions/Contracts}/
├── Application/{Actions,DTOs,Services,Listeners}/
├── Infrastructure/{Models,Repositories}/
├── Interfaces/HTTP/{Controllers/MCP,Requests/MCP}/
├── Exceptions/MCPExceptionHandler.php
└── CoreServiceProvider.php

app/Modules/Commerce/
├── Domain/
│   ├── UCP/                     (6 VOs — external connector normalization, untouched since Phase 1)
│   ├── Connectors/               ConnectorInterface, ProductConnectorInterface,
│   │                             OrderConnectorInterface (untouched since Phase 1)
│   ├── Entities/                 Product, Category, Cart, CartItem, Inventory,
│   │                             Order, OrderItem, Customer, Payment, Coupon, Discount
│   ├── ValueObjects/             Money, SKU, ProductStatus, Quantity, CartStatus,
│   │                             OrderStatus, OrderNumber, Email, Address, CustomerStatus,
│   │                             PaymentStatus, PaymentMethod, TaxRate, CouponCode,
│   │                             DiscountType, PricingBreakdown, WooCommerceProductId,
│   │                             WooCommerceProductData
│   ├── Services/                 PricingService, CouponValidationService,
│   │                             WooCommerceProductMapper  (all pure, framework-free)
│   ├── Events/                   17 domain events across Stages 1-5
│   ├── Repositories/              9 Repository interfaces (one per aggregate + Discount's)
│   └── Exceptions/                18 exception classes; every NotFound/Conflict-shaped one
│                                  implements a Core marker interface (§1) —
│                                  WooCommerceApiException deliberately does not (§7.6)
├── Application/
│   ├── Actions/                  ~32 Actions — see §7 for the per-stage list
│   ├── DTOs/                     ProductData, CategoryData, CartData, CartItemData,
│   │                             OrderData, OrderItemData, CustomerData, AddressData,
│   │                             PricingData, PaymentData, CouponData, WooCommerceSyncResult
│   └── Services/                 ConnectorRegistry, PaymentGatewayInterface,
│                                  MockPaymentGateway, PaymentGatewayResult,
│                                  WooCommerceClientInterface, WooCommerceClient,
│                                  WooCommerceConfig
├── Infrastructure/
│   ├── Connectors/                MockProductConnector (Phase 1),
│   │                              WooCommerceProductConnector (Stage 6, real)
│   ├── Http/                      MockWooCommerceHttpClient (Stage 6, tests only)
│   ├── Models/                    9 Eloquent models, one per aggregate
│   └── Repositories/               9 Eloquent repository implementations
└── CommerceServiceProvider.php    binds every Repository interface + registers
                                   15 capability handlers (see §6 for the full list)

app/Modules/CRM/                  new in Phase 3
├── Domain/
│   ├── Entities/                 Ticket, TicketComment, CustomerNote, Tag
│   ├── ValueObjects/             TicketStatus, TicketPriority, TagName
│   ├── Events/                   TicketWasCreated, TicketWasUpdated,
│   │                             CommentWasAddedToTicket, NoteWasAddedToCustomer
│   ├── Repositories/              TicketRepositoryInterface (owns TicketComment
│   │                              persistence too), CustomerNoteRepositoryInterface,
│   │                              TagRepositoryInterface (owns the customer_tag pivot too)
│   └── Exceptions/                TicketNotFoundException, CustomerNotFoundException
│                                  (CRM's own, not Commerce's — see §7.7),
│                                  InvalidTicketStatusException, TagNotFoundException
│                                  (added unprompted, same reasoning Discount's
│                                  Repository was in Stage 5)
├── Application/
│   ├── Actions/                  9 Actions — only 5 wired to MCP (§6/§7.7)
│   └── DTOs/                     TicketData, TicketCommentData, CustomerNoteData, TagData
├── Infrastructure/
│   ├── Models/                    4 Eloquent models — Tag has no belongsToMany to
│   │                              Commerce's Customer Model (§7.7)
│   └── Repositories/               3 Eloquent repository implementations
└── CRMServiceProvider.php        binds 3 Repository interfaces + registers
                                   5 capability handlers (see §6)

app/Modules/Demo/                  unchanged since Phase 1

packages/opencommerce-sdk/         unchanged since Phase 1

config/commerce.php                new in Stage 6 — WooCommerce store credentials
                                   (WOOCOMMERCE_* env vars, all empty by default)

database/
├── migrations/
│   ├── 0001_01_01_* , 2026_07_30_000001-000007   (Phase 1 — Core tables)
│   ├── 2026_07_30_000008-000009                  (Stage 1 — categories, products)
│   ├── 2026_07_30_000010-000012                  (Stage 2 — carts, cart_items, inventories)
│   ├── 2026_07_30_000013-000014                  (Stage 3 — orders, order_items)
│   ├── 2026_07_30_000015-000016                  (Stage 4 — customers, +orders.customer_id)
│   ├── 2026_07_31_000017-000020                  (Stage 5 — payments, coupons, discounts, +orders pricing cols)
│   │                                  (Stage 6 added no migrations — WooCommerce products
│   │                                   are stored in the existing `products` table, keyed by SKU)
│   └── 2026_07_31_000021-000025                  (Phase 3.1 — tickets, ticket_comments,
│                                                   customer_notes, tags, customer_tag pivot)
└── seeders/{DemoCapabilitiesSeeder,CommerceCapabilitiesSeeder,CRMCapabilitiesSeeder}.php

tests/
├── Fixtures/            woocommerce-products-response.json (Stage 6 — reference payload)
├── Unit/Commerce/       ~32 files — VOs, Entities, Domain Services, all framework-free PHPUnit
├── Feature/Commerce/    ~27 files — Actions against real sqlite :memory: DB, MCP HTTP end-to-end
├── Unit/CRM/            5 files — Ticket (incl. state machine), TicketComment,
│                        CustomerNote, Tag, TagName, all framework-free PHPUnit
├── Feature/CRM/         4 files — full MCP scenario + tenant isolation +
│                        the 4 un-wired Actions exercised directly
├── Unit/Core/, Unit/MCP/, Feature/Core/, Feature/MCP/, Feature/Demo/, Unit/Demo/   unchanged since Phase 1
└── 279 tests total, 634 assertions, ~5s runtime (`php artisan test`)
```

---

## 3. Architectural patterns established — still the ones to follow

Every aggregate added in Phase 2 followed the exact same 7-step pattern
documented after Phase 1 (Entity → Repository Interface → Eloquent Model +
Repository → DTO → Action → Domain Event → bind in ServiceProvider). That
pattern held up for all 9 Commerce aggregates without needing to change —
if you're adding a 10th, just follow it again.

What Phase 2 *added* to the pattern (see §7 for the full reasoning behind
each):

1. **Explicit scalars all the way down, `AuthContext` only at the MCP
   boundary.** Domain Repository interfaces and Application Actions take
   plain `int $tenantId` / `int $agentId` — never `AuthContext` itself.
   Only `CommerceServiceProvider`'s handler closures unpack `AuthContext`
   into those scalars before calling into an Action.
2. **Marker interfaces for cross-cutting exception routing.** A Domain
   Module exception that needs a specific HTTP status (404/409) implements
   `App\Core\Domain\Exceptions\Contracts\{NotFoundExceptionInterface,ConflictExceptionInterface}`
   rather than Core importing the Module's class. This is the load-bearing
   mechanism that let Stages 3, 4, and 5 each add new exception types
   without touching Core again after Stage 2 introduced the interfaces.
3. **Actions composing Actions is normal here**, not an anti-pattern —
   `AddToCartAction` depends on `CheckInventoryAction`; `PlaceOrderAction`
   is itself a dependency of `ProcessPaymentAction`. Prefer this over
   duplicating an already-correct Action's logic.
4. **"Preview" vs. "durable apply" Actions are split deliberately** when a
   calculation has a side effect that must never fire from a mere preview.
   `CalculatePricingAction` (preview, no side effects) vs. `ApplyCouponAction`
   (durable — increments `Coupon.used_count`, writes a `Discount` row) is
   the reference example. If you add another "quote before you commit" flow,
   split it the same way rather than adding an `$apply = false` flag to one
   Action.
5. **Two-phase Inventory lifecycle**, fully wired end to end as of Stage 5:
   - `reserve()` / `release()` — a *soft hold* a Cart places/lifts. Never
     touches `quantity_on_hand`.
   - `commit()` / `restore()` — the *hard* transition a placed/refunded-or-
     cancelled Order causes. `commit()` decrements `quantity_on_hand` and
     lifts the matching soft hold in one call; `restore()` is its exact
     inverse.
   All four methods live on `Domain/Entities/Inventory.php`. Don't
   reintroduce a third state — everything so far has fit into "held in a
   cart" vs. "actually sold."
6. **Widen a signature with optional trailing parameters rather than
   branching or duplicating an Action**, when a new stage needs an old
   Action to do slightly more. `Order::place()` / `PlaceOrderAction::execute()`
   both grew optional `tax`/`discount`/`total`/`customerId` parameters
   across Stages 3–5 while remaining 100% behaviorally identical for every
   caller that doesn't pass them (verified by the fact that no Stage-3 test
   needed to change when Stage 5 added pricing).
7. **"Optional" MCP input fields are simply omitted from `inputSchema`**,
   never declared with some `nullable: true` flag — `MCPRequestValidationService`
   treats every declared field as required and has no concept of
   optional-but-typed yet. If you add a genuinely optional field, leave it
   out of the schema and read it defensively (`$input['x'] ?? null`) in the
   handler/Action.

---

## 4. Non-obvious gotchas (learned the hard way — don't repeat these)

Phase 1's 7 gotchas (see git history / earlier revisions of this file if you
need them — condensed here since none of them bit again in Phase 2 except
where noted) plus what Phase 2 specifically taught:

1. **`ServiceProvider::boot()` runs before `RefreshDatabase` migrates the
   test DB.** Still true, still why capability *descriptions* are seeded
   (`CommerceCapabilitiesSeeder`), never registered in `boot()`. Capability
   *handlers* are fine in `boot()` because `CapabilityHandlerRegistry` is
   pure in-memory.
2. **`CapabilityName`/`PermissionKey` require exactly 3 dot-separated
   segments.** Still true. Every new capability/permission string added in
   Phase 2 was checked against this.
3. Guzzle/SDK gotchas — unaffected by Phase 2, still apply if you touch the SDK.
4. **JSON has no distinct float type.** Still true, and more relevant now
   than in Phase 1: every Money-shaped field (`priceAmount`, `taxAmount`,
   `discountAmount`, `totalAmount`, `amount` on Payment) is an **integer**
   (smallest currency unit) specifically to avoid this — never introduce a
   float-typed money field.
5. `git` PATH issue — environment-specific, check current session.
6. **PHP is 8.2.12.** Still true — this is *why* `PricingService` can't use
   `private const DEFAULT_TAX_RATE = new TaxRate(9.0);` (object-instantiation
   in const expressions needs PHP 8.3+). The 9% default lives as a plain
   `float` constant in two Actions instead (see §8.1).
7. **`CapabilityExecutionService` requires a real registered handler`** —
   still true, and now there's a second thing to remember alongside it:
   **a capability's *handler* (registered in `CommerceServiceProvider::boot()`)
   and its *description* (seeded via `CommerceCapabilitiesSeeder`) are two
   independent registrations.** It's easy to add one and forget the other —
   if a capability 404s with "no execution handler" despite clearly being
   wired in the ServiceProvider, check whether the test actually called
   `$this->seed(CommerceCapabilitiesSeeder::class)`.
8. **`orders.agent_id` is a real, non-nullable foreign key to `agents`.**
   Unlike `carts.owner_id` (plain `unsignedBigInteger`, no FK — Stage 2
   deliberately kept Cart ownership loosely typed), any test that places an
   Order needs a *real* registered Agent row, not a bare integer like `1`.
   Forgetting this produces a `FOREIGN KEY constraint failed` SQLite error
   deep in `EloquentOrderRepository::save()` that has nothing to do with
   your Action's logic — it's always this.
9. **Inventory reservation math only balances if you remember which phase
   you're in.** `AddToCartAction` reserves; `PlaceOrderAction`/
   `ProcessPaymentAction` commit (which *also* lifts the reservation, don't
   double-release); `CancelOrderAction`/`RefundPaymentAction` restore
   (which does *not* re-reserve). Mixing these up silently produces
   available-stock numbers that are wrong by exactly the reserved amount —
   always assert `quantityOnHand()` *and* `quantityReserved()` in tests that
   touch Inventory, not just `available()`, or a bug that cancels out in
   `available()`'s arithmetic can hide.
10. **`Discount` and `OrderItem`/`CartItem` have no `id` field on the Domain
    entity at all** (by design — nothing ever looks up one by its own id,
    only by `orderId`/`cartId`). If you add a feature that needs to
    reference one specific line/discount later, this is the thing that will
    need to change, and it touches the Repository's `toEntity()` mapping,
    not just the entity.
11. **A Connector's dependencies are injected once, at `boot()` time, into
    the instance handed to `ConnectorRegistry` — never re-resolved from the
    container afterward.** Rebinding an interface like
    `WooCommerceClientInterface` in a test's `setUp()` has no effect on a
    connector `boot()` already constructed and registered. To swap in a
    mock (or any different implementation) for a test, re-register a fresh
    Connector instance directly into `ConnectorRegistry` with the
    replacement dependency — the same call `boot()` itself makes, just with
    a different argument (see §7.6 and `WooCommerceProductConnector`'s
    docblock for the full example).

---

## 5. How to run things

```powershell
# First time / after pulling
composer install
cd packages/opencommerce-sdk; composer install; cd ../..

# Database
php artisan migrate
php artisan db:seed   # runs Demo-, Commerce-, and CRMCapabilitiesSeeder

# Tests
php artisan test                                                  # full app suite — 314 tests, ~8s
cd packages/opencommerce-sdk; vendor/bin/phpunit tests; cd ../..   # SDK's own suite (unaffected by Phase 2)

# Manual/live verification
php artisan serve --port=8000
php examples/sample-agent.php <agent-token> http://127.0.0.1:8000/mcp/v1
php examples/woocommerce-sync.php <agent-token> http://127.0.0.1:8000/mcp/v1   # Stage 6 — set
                                                                                # WOOCOMMERCE_* in .env first,
                                                                                # or every call fails against
                                                                                # an empty base URL
```

To generate a throwaway Agent token for manual testing, see the Tinker
snippet in `packages/opencommerce-sdk/README.md`'s "Quick Start" section, or
look at any `registerAgentWithPermissions()` helper in
`tests/Feature/Commerce/*CapabilityTest.php` for the full Tenant → Organization
→ Agent → Role → Permission → Token chain needed to call an MCP capability
end to end.

---

## 6. The 20 MCP capabilities that exist right now

| Capability | Phase/Stage | Permission | Notes |
|---|---|---|---|
| `commerce.product.search` | P2.1 | `commerce.products.read` | Active products only. |
| `commerce.cart.add` | P2.2 | `commerce.cart.manage` | Reserves Inventory. |
| `commerce.cart.get` | P2.2 | `commerce.cart.read` | Never persists an empty Cart. |
| `commerce.order.place` | P2.3 | `commerce.orders.create` | No tax/discount applied (see §8.3). |
| `commerce.order.get` | P2.3 | `commerce.orders.read` | Tenant-wide, not owner-scoped. |
| `commerce.order.list` | P2.3 | `commerce.orders.read` | Optional `status`/`limit`. |
| `commerce.customer.create` | P2.4 | `commerce.customers.create` | |
| `commerce.customer.get` | P2.4 | `commerce.customers.read` | |
| `commerce.customer.list` | P2.4 | `commerce.customers.read` | |
| `commerce.checkout.calculate` | P2.5 | `commerce.checkout.read` | Pure preview, no side effects. |
| `commerce.checkout.process` | P2.5 | `commerce.checkout.create` | The full Cart→Payment→Order flow. |
| `commerce.payment.refund` | P2.5 | `commerce.payments.refund` | Restores Inventory. |
| `commerce.coupon.create` | P2.5 | `commerce.coupons.create` | |
| `commerce.woocommerce.sync` | P2.6 | `commerce.connectors.sync` | Upserts a page of WooCommerce products into the catalog by SKU. |
| `commerce.woocommerce.get` | P2.6 | `commerce.connectors.read` | Live lookup straight from the Connector — not the local catalog. |
| `crm.ticket.create` | P3.1 | `crm.tickets.create` | Validates `customer_id` against Commerce's own `CustomerRepositoryInterface`. |
| `crm.ticket.get` | P3.1 | `crm.tickets.read` | Tenant-scoped by `findById()`; cross-tenant id -> 404, not 403. |
| `crm.ticket.list` | P3.1 | `crm.tickets.read` | Optional `status`/`customer_id`. |
| `crm.comment.create` | P3.1 | `crm.tickets.update` | Renamed from the requested `crm.ticket.comment.add` — 4 segments, see §7.7. |
| `crm.note.create` | P3.1 | `crm.customers.update` | Renamed from the requested `crm.customer.note.add` — same reason. |

**Deliberately NOT wired to MCP** despite the underlying Action existing and
being fully tested (see §8.2 for why, and the same reasoning each time):
`RemoveFromCartAction` (no `commerce.cart.remove`), `UpdateCartItemQuantityAction`,
`ClearCartAction`, `CancelOrderAction` (no `commerce.order.cancel`),
`UpdateOrderStatusAction`, `GetCustomerOrdersAction` (no
`commerce.customer.orders`), `GetPaymentAction` (no `commerce.payment.get`),
and CRM's `UpdateTicketAction`, `GetCustomerNotesAction`, `CreateTagAction`,
`AssignTagToCustomerAction` (§7.7).
Every one of these is a one-capability-definition-plus-one-handler-closure
addition if a future stage actually needs it through MCP — nothing about
them is unfinished, they were just never asked for at the MCP layer.

---

## 7. Phase 2/3 stage-by-stage detail

### 7.1 Stage 1 — Product & Category Management

Entities: `Product`, `Category`. VOs: `Money` (integer cents + ISO
currency), `SKU` (uppercase-normalized, `InvalidSKUException`),
`ProductStatus` (draft/active/archived). `commerce.product.search` only
ever returns `Active` products — a business decision made in
`ListProductsAction`, not the Repository (Repositories must not encode
business rules). SKU uniqueness is per-tenant. Soft deletes on Product.

### 7.2 Stage 2 — Cart & Inventory Management

Entities: `Cart`, `CartItem`, `Inventory`. VOs: `Quantity` (strictly > 0),
`CartStatus`. This stage is where `AuthContext` was born — see §1/§3 — and
where the two Core marker interfaces were introduced specifically because a
first attempt at wiring `InsufficientInventoryException` etc. into
`MCPExceptionHandler` **imported `App\Modules\Commerce` directly into
`App\Core`**, a real violation of "Core must never depend on business
domains" that got caught and fixed before shipping, not after. If you ever
find yourself about to `use App\Modules\...` inside anything under
`app/Core/`, stop — that's the mistake this stage made once already.

`EloquentCartRepository::save()` deletes-and-reinserts all of a Cart's items
on every save (simplest correct approach for a small, frequently-mutated
collection) — contrast with Order's items, which are written once and never
touched again.

### 7.3 Stage 3 — Order Management

Entities: `Order`, `OrderItem` (OrderItem is fully immutable, no mutators,
no `id` field). VOs: `OrderStatus` (7 states), `OrderNumber`
(`ORD-YYYYMMDD-XXXXX`, generated with a random 5-digit suffix + collision
check + retry, not a sequential counter). `PlaceOrderAction` wraps the
entire Cart→Inventory→Order flow in one `DB::transaction`. `Order::confirm()`
is called unconditionally right after `place()` — there is no payment gate
yet at this stage, so "placed" and "confirmed" happen atomically (this
stopped being true once Stage 5 added real payment processing, but the
*old* `commerce.order.place` capability still behaves this way on purpose —
see §8.3).

`Order::changeStatus()` refuses to target `Cancelled`/`Refunded` (only
`cancel()`/`refund()` can reach those, so their Inventory side-effects can
never be bypassed by a generic status update) and refuses to leave a
terminal status (`Cancelled`/`Refunded`/`Delivered`) at all.

### 7.4 Stage 4 — Customer Management

Entity: `Customer`. VOs: `Email` (lowercase-normalized), `Address` (flat
data holder, `fromArray()`/`toArray()`, nullable `state`/`postalCode`),
`CustomerStatus`. `orders.customer_id` was added here as a nullable,
additive migration (Stage 3's `orders` table shipped with no concept of
Customer at all) — `Order`/`PlaceOrderAction` both grew an optional
`customerId` param that defaults to `null` and changes nothing for a caller
that omits it. `GetCustomerOrdersAction` is the one place Customer and
Order — two separate aggregates in the same module — talk to each other,
and it does so only through explicit ids and each aggregate's own
Repository interface, never a direct object reference (Dependency
Inversion, deliberately demonstrated here per that stage's explicit ask).

### 7.5 Stage 5 — Checkout & Payment System

Entities: `Payment`, `Coupon`, `Discount` (Discount has no `id` field, same
reasoning as OrderItem). VOs: `PaymentStatus`, `PaymentMethod`, `TaxRate`
(0–100, a float — a rate isn't money), `CouponCode` (`COUPON-XXXXX`),
`DiscountType`, `PricingBreakdown` (Domain-layer return type for
`PricingService`, distinct from the Application-layer `PricingData` DTO).
Domain Services: `PricingService` (the single formula owner: `Total =
Subtotal + Tax − Discount`, tax always computed on the subtotal, never on
the discounted amount) and `CouponValidationService` (expiry/max-uses/
min-order-amount checks against an already-loaded `Coupon`).

**The checkout flow is payment-before-order, exactly as specified**: Cart →
Pricing → Coupon validation → `PaymentGatewayInterface::charge()` → *only if
successful* → `PlaceOrderAction` (now supplying real tax/discount/total) →
persist `Payment` → `ApplyCouponAction` (increments `Coupon.used_count`,
writes the `Discount` row — durable, only ever called after a real
success). This is why `payments.order_id` is not nullable: a declined
charge never reaches the point where an Order — or a Payment row — exists.

`MockPaymentGateway` is the only `PaymentGatewayInterface` implementation.
It always succeeds unless the caller passes
`payment_details: {"simulate_failure": true}` — the deliberate,
documented way to exercise the decline path in tests without real network
mocking.

`DiscountRepositoryInterface`/`EloquentDiscountRepository` were added even
though not explicitly requested that stage — `Discount` had an Entity,
Eloquent Model, and migration but no Repository in the literal request,
which would have meant bypassing the Repository convention every other
aggregate in this codebase follows.

### 7.6 Stage 6 — Real Connectors (WooCommerce)

VOs: `WooCommerceProductId` (positive-int wrapper, same reasoning as
SKU/Money), `WooCommerceProductData` (raw REST payload, snake_case→camelCase,
never leaves the Connector boundary). Domain Service:
`WooCommerceProductMapper::toUCP()` — the only place a decimal price
string (`"29.99"`) is ever parsed into integer cents; framework-free like
`PricingService`/`CouponValidationService`, so it's unit-testable without
HTTP or a database. Exception: `WooCommerceApiException` — deliberately
implements **neither** Core marker interface (§1/§3.2): an upstream API
failure is neither "not found" nor "a business-rule conflict," so it falls
through `MCPExceptionHandler`'s default branch to `INTERNAL_ERROR` (500),
which is the semantically correct status for a broken external dependency.
Not every new exception needs a marker interface — only ones that are
genuinely 404/409-shaped.

`WooCommerceClientInterface` (Application/Services) is the outbound port;
`WooCommerceClient` is the real Guzzle-backed implementation (WooCommerce's
own Consumer Key/Secret query-string auth, not OAuth); `MockWooCommerceHttpClient`
(Infrastructure/Http) is the only other implementation, used by every test
until a real store's credentials exist. `SyncWooCommerceProductsAction`
upserts by SKU — deliberately *not* built on top of `CreateProductAction`/
`UpdateProductAction`, since those throw on "already exists"/"does not
exist" respectively, the wrong control flow for a bulk operation that must
keep going and report per-item failures (`WooCommerceSyncResult`:
`success_count`/`failed_count`/`errors`) rather than abort on the first bad
row. `GetWooCommerceProductAction` is a live, uncached, unpersisted lookup
straight from the Connector — reuses the existing `ProductNotFoundException`
(already wired to 404 via Core's marker interface) rather than inventing a
WooCommerce-specific one.

**Non-obvious gotcha this stage introduced** — also in §4 item 11:
`WooCommerceProductConnector`'s `WooCommerceClientInterface` dependency is
injected exactly once, when `CommerceServiceProvider::boot()` builds it and
hands it to `ConnectorRegistry`. Rebinding `WooCommerceClientInterface` in
the container *after* `boot()` has already run (e.g. from a test's
`setUp()`) has no effect on the already-registered connector instance — the
same class of container-timing pitfall Laravel developers hit with
singletons generally. Every test that needs `MockWooCommerceHttpClient`
instead re-registers a fresh `WooCommerceProductConnector` directly into
`ConnectorRegistry` (`registerProductConnector('woocommerce', ...)`) — the
exact same call `boot()` itself makes, just with a different client. See
`WooCommerceProductConnector`'s own docblock for the full explanation.

`config/commerce.php` (new file) holds `WOOCOMMERCE_STORE_URL`/
`_CONSUMER_KEY`/`_CONSUMER_SECRET`/`_CURRENCY`/`_TIMEOUT`, all empty/default
out of the box — `WooCommerceConfig::fromConfig()` is the one place that
reads them; nothing else calls `config()`/`env()` directly for WooCommerce
settings.

### 7.7 Phase 3, Stage 1 — CRM Foundation

Entities: `Ticket` (support ticket), `TicketComment` (child, no
`id`-lookup use case beyond its own row, no `tenant_id` — inherited via
`ticket_id`, same shape `OrderItem` has relative to `Order`),
`CustomerNote` (immutable append-only annotation on a Customer), `Tag`
(tenant-scoped label, unique name per tenant). VOs: `TicketStatus`
(open/in_progress/resolved/closed), `TicketPriority`
(low/medium/high/urgent), `TagName` (trimmed, internal whitespace
collapsed, casing preserved — unlike SKU/CouponCode, a Tag name is a
human-facing label, not a machine identifier).

**`Ticket::changeStatus()` is stricter than Commerce's
`Order::changeStatus()`**: not just "no path back to a terminal state"
but "no path back or sideways at all" — a fixed `SEQUENCE` array
(`Open, InProgress, Resolved, Closed`) means only a strictly-forward
index move is ever legal; re-targeting the *current* status is rejected
too, unlike Order (which tolerates a same-status no-op inside its
fulfillment pipeline). `UpdateTicketAction` is the thin
findById→changeStatus→save→dispatch wrapper, mirroring
`UpdateOrderStatusAction`'s shape exactly.

**Cross-module dependency, demonstrated for the first time**: CRM needs
to verify a `customer_id` refers to a real Commerce Customer before a
Ticket/Note/Tag-assignment can reference it. `CreateTicketAction`,
`AddNoteToCustomerAction`, and `AssignTagToCustomerAction` all inject
Commerce's `Domain\Repositories\CustomerRepositoryInterface` directly —
an Interface from another Domain Module's Domain layer, never Commerce's
Infrastructure/Model classes. The reason this doesn't violate module
independence: CRM depends on a *published contract*, not on Commerce's
implementation details, the identical Dependency-Inversion direction
Core's marker-interface mechanism already established for Core -> Module,
just one level over (Module -> Module). The one place this could have
gone wrong and didn't: CRM throws **its own**
`Domain\Exceptions\CustomerNotFoundException` when the check fails, never
Commerce's — see that class's docblock for why importing Commerce's
concrete exception type from CRM would have been the wrong kind of
coupling even though the Interface dependency is fine.

**`TagNotFoundException` wasn't in the original request** — added for
the same reason Commerce's Stage 5 added
`DiscountRepositoryInterface`/`EloquentDiscountRepository` unprompted
(HANDOFF §7.5): `AssignTagToCustomerAction` needed a real 404 for an
unknown tag id, not a raw foreign-key failure surfacing from
`customer_tag`'s insert.

**Capability names changed from the request** — `crm.ticket.comment.add`
and `crm.customer.note.add` were both 4 segments; `CapabilityName`
requires exactly 3 (HANDOFF gotcha #2, hit again here the same way
WooCommerce's Stage 6 capabilities hit it). Renamed to `crm.comment.create`
and `crm.note.create` — reusing the `create` verb Commerce's own
`commerce.coupon.create`/`commerce.customer.create` already established,
rather than inventing "comment"/"note" as pseudo-verbs.

**4 of CRM's 9 Actions are deliberately not wired to MCP this stage** —
`UpdateTicketAction`, `GetCustomerNotesAction`, `CreateTagAction`,
`AssignTagToCustomerAction` — only the 5 capabilities actually requested
(`crm.ticket.create/get/list`, `crm.comment.create`, `crm.note.create`)
got a `CRMCapabilities` entry + `CRMServiceProvider` handler closure. Same
"built, tested, not yet exposed to Agents" gap Commerce has always
carried (HANDOFF §6/§8.2) — each is a small addition whenever a future
stage actually needs it through MCP. All four are still exercised
directly in `tests/Feature/CRM/*ActionTest.php`.

**`customer_tag` is a plain pivot, not an Eloquent `belongsToMany`
relation** — `Tag`'s Eloquent Model has no `customers()` relation to
Commerce's Customer Model at all; `EloquentTagRepository::assignToCustomer()`
writes the pivot row with a plain query-builder insert (explicit
exists-check first, so double-assignment is a silent no-op, not a
duplicate-key error) instead. Keeps CRM decoupled from Commerce's Model
classes even at the Infrastructure layer, not just the Domain layer.

`ticket_comments.agent_id` and `customer_notes.agent_id` are both real,
non-nullable foreign keys to `agents` — the exact same
`orders.agent_id` shape HANDOFF gotcha #8 already warns about, and one
CRM test caught itself making that exact mistake (a bare `1` instead of
a real registered Agent id) during this stage — see
`GetCustomerNotesActionTest`'s docblock.

---

## 8. Known technical debt (ranked, carried over + Phase 2 additions)

1. **No per-tenant tax-rate configuration exists.** `CalculatePricingAction`
   and `ProcessPaymentAction` both hardcode a `9.0`% default
   (`DEFAULT_TAX_RATE_PERCENT`, duplicated as a plain float constant in
   both files — can't be a shared `TaxRate` object constant until PHP 8.3).
   A real implementation needs a `TenantTaxSettings`-shaped entity and a
   repository lookup replacing that constant in exactly those two places.
2. **Seven fully-built, fully-tested Actions have no MCP capability wired
   to them** — see the table in §6 for the full list and why. Each is a
   ~10-line addition (one `CommerceCapabilities::definitions()` entry + one
   `CommerceServiceProvider` handler closure) whenever a future stage
   actually needs it exposed to Agents.
3. **`commerce.order.place` (Stage 3) never applies tax/discount**, even
   though the entity/Action machinery to do so now exists (Stage 5). Only
   `commerce.checkout.process` computes real pricing. This is intentional —
   changing the old capability's behavior wasn't asked for and would be a
   silent behavior change to something already shipped — but it does mean
   there are now two ways to place an Order with meaningfully different
   pricing behavior. Worth deciding explicitly, not by accident, if a
   future stage wants to unify them.
4. **No CI actually running yet.** Unchanged from Phase 1 — verify a GitHub
   remote/Actions is active before relying on it.
5. **N+1 query on the permission-check hot path.** Unchanged from Phase 1
   (`EloquentMemberRoleRepository::findRolesForMember()`).
6. **No global rate limiting on `routes/mcp.php`.** Unchanged from Phase 1.
7. **`User` identity path is incomplete.** Unchanged from Phase 1 — every
   single Commerce capability added in Phase 2 hardcodes `MemberType::Agent`
   for the same reason MCPGatewayController always did. If a human User
   path is ever built, every `MemberType::Agent` hardcoded across Cart/
   Order/Checkout Actions and their ServiceProvider closures is a place
   that needs revisiting.
8. **Coupon's `discount_value` column means two different things depending
   on `discount_type`** (a whole percent 0–100, or a cents integer) —
   documented clearly in `Coupon`'s docblock and `calculateDiscount()`, but
   still a single untyped `int` column, not two type-safe columns. Works
   correctly; a future refactor might prefer separate `discount_percentage`/
   `discount_amount` columns for clarity.
9. **`InvalidCouponException` is reused for "coupon code doesn't exist at
   all"**, not just "coupon exists but can't be used right now" — meaning a
   typo'd coupon code returns 409 CONFLICT rather than a 404. No dedicated
   `CouponNotFoundException` was requested; revisit if that distinction
   ever matters to a caller.
10. **A real Payment Gateway integration needs a transaction-boundary
    change.** `ProcessPaymentAction` currently wraps the *entire* flow,
    including the gateway call, in one `DB::transaction` — fine today only
    because `MockPaymentGateway` is synchronous and local. A real gateway
    should charge *outside* the transaction and only wrap the subsequent DB
    writes, so a slow network call never holds a DB lock. Documented in
    `ProcessPaymentAction`'s own docblock.
11. **Coverage percentage is unmeasured.** Unchanged from Phase 1 — no
    Xdebug/PCOV installed.
12. **WooCommerce sync is single-currency and un-scheduled.** `WooCommerceConfig::$currency`
    is one value for the whole store (WooCommerce doesn't return a currency
    per product in its REST payload), and `commerce.woocommerce.sync` only
    ever runs when an Agent explicitly calls it — no cron/queue job polls a
    connected store on a schedule. Both are fine for a single-store demo,
    not for a real multi-currency or "stay in sync automatically" use case.
13. **Only Product sync exists — no Order/Customer/Inventory push to
    WooCommerce.** `OrderConnectorInterface` (Domain/Connectors, Phase 1)
    still has zero implementations; Stage 6 only built the Product *read*
    direction (WooCommerce → OpenCommerce), not writing Orders back out.
14. **WooCommerce credentials are per-deployment (`.env`), not per-tenant.**
    Every tenant syncing through `commerce.woocommerce.sync` hits the same
    one configured store. A real multi-tenant SaaS needs tenant-scoped
    connector credentials — the "credential storage" responsibility
    `ConnectorRegistry`'s own docblock already flags as belonging to a
    future, fuller Connection Manager, not this registry.
15. **CRM's Ticket has no assignment concept** — `agent_id` on a Ticket is
    only ever "whichever Agent created it," permanently; there is no
    "assign/reassign this Ticket to a different Agent" operation. A real
    support-desk use case will want one.
16. **No way to remove a Tag once assigned, and no `crm.tag.*` capability
    at all exists through MCP** — `CreateTagAction`/`AssignTagToCustomerAction`
    are Action-only this stage (§7.7); there is also no
    `RemoveTagFromCustomerAction` — assignment is currently append-only.
17. **`TicketComment`/`CustomerNote` have no `id`-based lookup path** —
    same "no separate repository for a child record with no independent
    identity" reasoning `OrderItem`/`Discount` already established
    (HANDOFF gotcha #10), inherited by CRM's own child entities. If a
    future feature needs to reference/edit one specific comment or note,
    this is the thing that will need to change.

---

## 9. What's next

Phase 2 is fully complete (all 6 Stages). Phase 3 has begun — CRM
Foundation (Stage 1) is done. Candidates worth raising with whoever's
driving scope next, roughly in order of how much they'd reuse what
already exists:

- **Phase 3's next module** — Finance or AI Workflows per the project
  vision, or a second CRM stage (Ticket assignment, Tag removal, a
  `crm.tag.*` MCP surface — §8.15/§8.16) if CRM isn't considered done yet.
  CRM Foundation is the first proof that the Module -> Module dependency
  direction (§7.7) works, not just Core -> Module — a template for
  whichever module needs to reference Commerce or CRM data next.
- **A second real Connector** (Shopify) — `ProductConnectorInterface` and
  the WooCommerce implementation (§7.6) are now a template to follow;
  `ConnectorRegistry` already supports registering more than one by name.
- **Wire the 11 un-wired capabilities from §6** (7 from Commerce Stages
  1–5, 4 from CRM) if any Agent workflow actually needs cart-removal,
  order-cancellation, payment lookup, ticket-updating, or tag management
  through MCP — cheapest possible next increment each.
- **Real per-tenant tax configuration** (§8.1) and **per-tenant connector
  credentials** (§8.14) — the two most obviously "fake"/single-tenant
  pieces of what's been built so far.
- **Order/Customer/Inventory sync out to WooCommerce** (§8.13) —
  `OrderConnectorInterface` still has no implementation.
- **Shipping** — no shipping cost, address-to-carrier, or fulfillment
  concept exists anywhere yet; `Address` (Stage 4) only lives on Customer
  today, not on an Order.
- **A dedicated `capabilities:sync` artisan command**, graduating away from
  the seeder pattern — flagged as an open decision since Phase 1, still
  open, now with 20 capabilities across three seeders instead of 3.

Whatever comes next, follow §3's patterns and check §8 before assuming a
piece of the puzzle doesn't already exist.
