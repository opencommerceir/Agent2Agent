# OpenCommerce Platform — Session Handoff

**Status: Phase 1 (Core + MCP Gateway), Phase 2 (Commerce, all 6
Stages), and Phase 3 (Domain Expansion, all 5 Stages — CRM, Finance,
Workflows, Loyalty, Reporting) are complete. Phase 4 (Shipping &
Logistics) is under way: Stage 1 (Shipping Foundation) and Stage 2
(Shipping Provider Connector, §7.14) are both complete.
Finance supplies Commerce's own checkout pricing with real tax rates
through an Interface Commerce itself owns (§7.8). Workflows (§7.9) and
Loyalty (§7.10) each introduce a real cross-module Domain Event
Listener. Reporting (§7.11) is the platform's first read-only module
and the first deliberate, documented exception to the Module -> Module
"depend on an Interface, never a Model" rule (a CQRS-style Read Model
querying Commerce's/Loyalty's Eloquent Models directly for aggregate
performance). Shipping (§7.12) is Phase 4's first module and the
**first time a later module's migration alters an earlier module's own
table**: Commerce's `Order` entity gained an additive, backward-compatible
`assignShipping()` (three new nullable fields — `shippingMethodId`/
`shipmentId`/`shippingCost`) so a Shipment can write its assignment back
onto the Order it fulfills, the same Dependency-Inversion direction
every prior cross-module integration used, just flowing one field
further than before — see `Order::assignShipping()`'s own docblock for
the full reasoning and the alternative that was considered and rejected.

**A Tech Debt Sprint (§7.13) ran immediately after Shipping Stage 1,
before Phase 4 continues, closing 7 items from §8/§9 in one pass: the
`CheckInventoryAction` re-check arithmetic bug (§8.22, plus a related
reservation race condition found while fixing it), the permission-check
N+1, per-agent MCP rate limiting, CI coverage reporting, a real Laravel
scheduler (§8.23/§8.27 — the first ever `Schedule::command()` in this
codebase), and a generated `docs/api-reference.md`. The scheduler
immediately wired Workflows' previously-scaffolded `CartAbandonedListener`
for real (Commerce gained a new `CartWasAbandoned` event and
`Cart::abandon()` — dormant since Phase 3 — finally has a caller).
See §7.13 for the full detail, including two places where the sprint's
own brief didn't match the actual code/environment and what was built
instead.**

**Stage 2 (Shipping Provider Connector) demonstrates the Connector
Pattern (Phase 1, reused for real in Commerce's Stage 6 WooCommerce
integration) inside Shipping: `ShippingProviderInterface`/
`ShippingProviderRegistry`/`MockShippingProviderAdapter` mirror
`ProductConnectorInterface`/`ConnectorRegistry`/`WooCommerceProductConnector`
file-for-file. Only `mock` has an implementation (no live carrier
credentials exist, same reasoning every Connector in this codebase
gives) — `usps`/`fedex`/`dhl` are modeled, unimplemented future intents.
Fixing a real bug found while building this stage — `AddTrackingEventAction`
had no way to record a historical `occurredAt`, so every synced tracking
event silently got "now" instead of the provider's own timestamp,
breaking `SyncTrackingAction`'s dedup entirely — widened that Action
with an optional trailing parameter (HANDOFF §3 pattern #6), the same
kind of pre-existing-gap-discovered-while-building-the-next-thing this
codebase has hit repeatedly (§7.9/§7.10/§7.12). See §7.14 for the full
detail, including four places the request's own file layout would have
duplicated an abstraction or hit a real domain conflict, and what was
built instead.**

483 tests passing, zero known regressions. Next up: another Phase 4
Shipping/Logistics stage (Shipping Zones, partial fulfillment, folding
`shipping_cost` into checkout pricing — §8.37/§8.35/§8.36), a real
carrier implementation of `ShippingProviderInterface` (USPS/FedEx/DHL —
`MockShippingProviderAdapter` is now the template, the same role
`WooCommerceProductConnector` played for a second real Commerce
Connector), wiring `HighValueOrderListener` (still scaffolded, still the
cheapest available increment — §9), or any remaining deferred item in
§8/§9. Note: `WorkflowsCapabilityTest`'s own docblock still describes
working around the now-fixed §8.22 ceiling (6-on-hand/order-3, kept
under half of stock) — harmless (the test still passes either way) but
worth a quick cleanup pass since the constraint that motivated it is
gone.**

This file is a working-state snapshot for picking up development in a new
session. It assumes you've already read `CLAUDE.md` and `docs/*.md` (the
project's standing rules) — this document is "what actually got built and
why," not a repeat of the architecture doctrine.

If you are a fresh Claude Code session reading this: read this whole file
before touching code. Section 7 (stage-by-stage detail) and Section 8
(technical debt) are the parts most likely to save you from repeating a
mistake or re-deciding something that was already deliberately decided.

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
| Tenant | `Domain/Entities/Tenant.php`, `Application/Actions/CreateTenantAction.php` | `TenantRepositoryInterface` gained `all()` in the Tech Debt Sprint (§7.13) — the first thing that ever needed to list every Tenant, for the new cross-tenant scheduled commands. |
| Organization | `Domain/Entities/Organization.php`, `OrganizationMember.php` | Unchanged since Phase 1. |
| Agent Registry | `Domain/Entities/Agent.php`, `AgentToken.php`, related Actions | Unchanged since Phase 1. |
| Permission System | `Domain/Entities/{Permission,Role,MemberRole}.php`, `CheckPermissionAction` | Unchanged since Phase 1. |
| Capability Registry | `Domain/Entities/Capability.php`, related Actions | Unchanged since Phase 1. Still strict 3-segment `domain.resource.action` names. |
| **Capability Execution** | `Application/Services/CapabilityHandlerRegistry.php`, `CapabilityExecutionService.php` | **Handler contract changed in Phase 2**: `callable(array $input, AuthContext $context): array` — was `callable(array $input): array` in Phase 1, then briefly `callable(array $input, int $tenantId): array` early in Phase 2 before Cart ownership needed the Agent's own id too. See §7.2/§7.3 for the full history — do not re-litigate this, it was already widened twice and settled. |
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

**One widening in Phase 4, Stage 1 (Shipping — see §7.12):** `Order`
gained `shippingMethodId`/`shipmentId`/`shippingCost` (all nullable,
default null) and one new mutator, `assignShipping()` — an additive,
backward-compatible change, the exact same shape `customerId`
(Stage 4)/`tax`/`discount`/`total` (Stage 5) were each added, just
authored by a later module (Shipping) instead of by Commerce's own next
stage. `OrderData`/`EloquentOrderRepository`/the `orders` table all grew
the matching 3-4 fields/columns to carry it. This is the *only* piece of
Commerce any Phase 3/4 module has ever needed to modify directly (every
other cross-module integration so far only ever added a new Interface,
a new Event, or a brand new table).

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

### `app/Modules/Finance/` — **new in Phase 3. Per-tenant tax rates and Invoices — Phase 3, Stage 2, and the first module to change Commerce itself (in a way that still keeps both modules decoupled — see §7.8).**

See §7.8 for the full detail. 3 Domain Entities (`TaxRate`, `Invoice`,
`InvoiceItem`), 4 Value Objects (`InvoiceNumber`, `InvoiceStatus`,
`TaxRegion`, and Finance's own `Money` — deliberately a second, separate
class from Commerce's, not a shared one), 3 domain events, 4 exceptions,
2 Repository interfaces, 9 Application Actions (8 wired to MCP — see
§6), 3 Eloquent models, 2 Eloquent repositories, 3 migrations. Unlike
CRM (Module -> Module dependency in one direction only), Finance
introduces the platform's first *two-way* integration between Domain
Modules: Finance depends on Commerce's `OrderRepositoryInterface`/
`ProductRepositoryInterface` to build Invoices, **and** Commerce's own
checkout pricing (`CalculatePricingAction`/`ProcessPaymentAction`) now
depends on a real tax rate lookup Finance supplies — without either
module ever importing a concrete class from the other. The mechanism
that makes the second direction safe is `Commerce\Application\Services\TaxRateProviderInterface`
— an Interface Commerce itself defines and depends on, that Finance's
`Infrastructure\Services\CommerceTaxRateProvider` implements and
`FinanceServiceProvider` binds over Commerce's own harmless default
(`NullTaxRateProvider`). Commerce still has zero references to
`App\Modules\Finance\*` anywhere in its own code.

### `app/Modules/Workflows/` — **new in Phase 3. Event-driven automation ("when X happens and Y is true, do Z") — Phase 3, Stage 3, and the first module to attach a real Listener to another module's Domain Event.**

See §7.9 for the full detail. 4 Domain Entities (`Workflow`,
`WorkflowRule`, `WorkflowAction`, and `WorkflowLog` — the last one added
unprompted, see §7.9), 3 Value Objects (`WorkflowStatus`, `EventType`,
`Threshold`), 2 domain events, 2 exceptions, 1 Repository interface
(owns `WorkflowLog` persistence too), 7 Application Actions (5 wired to
MCP — see §6, one added unprompted alongside `WorkflowLog`), 3 Listeners
(only `InventoryLowListener` actually registered — `CartAbandonedListener`/
`HighValueOrderListener` are documented, unwired scaffolding), 4 Eloquent
models, 1 Eloquent repository, 4 migrations. Required one small, additive
change to Commerce itself: a new `InventoryWasCommitted` Domain Event
(dispatched from `PlaceOrderAction`) — no event previously existed for
"stock actually went down," only `InventoryReserved` (the soft-hold
side). `InventoryLowListener` reacts to it using Commerce's
`InventoryRepositoryInterface`/`ProductRepositoryInterface` (Interfaces,
never Commerce's Models), the same Dependency Inversion direction
CRM/Finance already established.

### `app/Modules/Loyalty/` — **new in Phase 3. Points, Rewards, and Redemptions — Phase 3, Stage 4, and the second module to attach a real Listener to another module's Domain Event.**

See §7.10 for the full detail. 4 Domain Entities (`LoyaltyAccount`,
`PointTransaction`, `Reward`, `Redemption`), 4 Value Objects (`Points`,
`TransactionType`, `RewardType`, `ExpirationDate`), 4 domain events, 6
exceptions (4 requested + 2 added unprompted — see §7.10), 3 Repository
interfaces, 9 Application Actions (8 wired to MCP — see §6), 1 Listener
(`OrderPlacedListener`, registered), 4 Eloquent models, 3 Eloquent
repositories, 4 migrations. Depends on Commerce's
`CustomerRepositoryInterface` the same one-directional Module -> Module
pattern CRM/Finance/Workflows already established, and reacts to
Commerce's existing `OrderWasPlaced` event without requiring any change
to Commerce at all — unlike Workflows' Stage, no new Commerce event was
needed this time (`OrderWasPlaced` already carried everything).

### `app/Modules/Reporting/` — **new in Phase 3. Read-only analytics — Phase 3, Stage 5, and the first deliberate, documented exception to the Module -> Module Repository Interface rule.**

See §7.11 for the full detail. 2 Domain Entities (`Report`, `ReportResult`),
3 Value Objects (`ReportType`, `DateRange`, `ReportFilter`), 5 pure
Domain Services (one Generator per report type), 2 exceptions, 1
Repository interface (owns `ReportResult` persistence too), 7
Application Actions (5 wired to MCP — see §6), 6 DTOs, 2 Eloquent
models, 1 Eloquent repository, 5 **Query Builders**
(`Infrastructure/Queries/*`) that query Commerce's/Loyalty's Eloquent
Models *directly* — not through their Repository Interfaces — for
SUM/COUNT/GROUP BY aggregate performance. This is a real, considered
architectural trade-off, not an oversight: see `SalesQueryBuilder`'s own
docblock for the full reasoning, and why it's safe (Reporting never
writes to another module's table). Every *entity detail* lookup
(a product's name, a customer's full name) still goes through the
proper Repository Interface, exactly as CRM/Finance/Workflows/Loyalty
already established — only the aggregate math itself bypasses it.

### `app/Modules/Shipping/` — **new in Phase 4. ShippingMethods, Shipments, and TrackingEvents — Phase 4's first module and the first to write data back onto Commerce's own Order.**

See §7.12 for the full detail. 3 Domain Entities (`ShippingMethod`,
`Shipment`, `TrackingEvent`), 5 Value Objects (`Money` — Shipping's own,
same reasoning Finance's duplicate `Money` has —, `Weight`,
`TrackingNumber`, `TrackingStatus`, `ShippingRate`), 4 exceptions (3
requested + 1 added unprompted, `OrderNotFoundException`, same reasoning
Finance's/Loyalty's own additions had), 2 Repository interfaces (one
owns `TrackingEvent` persistence too), 1 pure Domain Service
(`ShippingRateCalculator`), 3 domain events, 9 Application Actions (all
8 requested capabilities wired — see §6), 4 DTOs, 3 Eloquent models, 2
Eloquent repositories, 4 migrations (3 new tables + 1 that alters
Commerce's own `orders` table). Depends on Commerce's
`OrderRepositoryInterface`/`ProductRepositoryInterface` — the
established one-directional Module -> Module Dependency Inversion — and
additionally calls a new mutator Commerce's own `Order` entity gained
for this stage, `assignShipping()` (see Commerce's own section above
and §7.12 for the full reasoning).

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
│                        + RateLimitExceededException (Tech Debt Sprint, §7.13 —
│                        implements neither marker interface, same reasoning
│                        WooCommerceApiException has)
├── Application/{Actions,DTOs,Services,Listeners}/
│                        + EnforceRateLimitAction (§7.13)
├── Infrastructure/{Models,Repositories}/
├── Interfaces/HTTP/{Controllers/MCP,Requests/MCP}/
├── Exceptions/MCPExceptionHandler.php
└── CoreServiceProvider.php

config/mcp.php              new in Tech Debt Sprint (§7.13) — MCP_RATE_LIMIT_PER_MINUTE

app/Console/Commands/       new in Tech Debt Sprint (§7.13) — this directory
                             didn't exist before: ExpireLoyaltyPointsCommand,
                             MarkAbandonedCartsCommand, both scheduled via
                             routes/console.php (Schedule::command(), also
                             new — no app/Console/Kernel.php in this Laravel
                             version)

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
│   ├── Events/                   17 domain events across Stages 1-5, + CartWasAbandoned
│   │                             (Tech Debt Sprint, §7.13 — carries only identifiers,
│   │                             same shape InventoryWasCommitted established)
│   ├── Repositories/              9 Repository interfaces (one per aggregate + Discount's),
│   │                             + findByProductForUpdate() on InventoryRepositoryInterface
│   │                             and findStaleActive() on CartRepositoryInterface (§7.13)
│   └── Exceptions/                18 exception classes; every NotFound/Conflict-shaped one
│                                  implements a Core marker interface (§1) —
│                                  WooCommerceApiException deliberately does not (§7.6)
├── Application/
│   ├── Actions/                  ~32 Actions — see §7 for the per-stage list, +
│   │                             MarkCartsAbandonedAction (§7.13); CheckInventoryAction
│   │                             gained executeCommit()/authorizeCommit() (§7.13, §8.22 fix)
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

app/Modules/Finance/               new in Phase 3
├── Domain/
│   ├── Entities/                 TaxRate, Invoice, InvoiceItem
│   ├── ValueObjects/             InvoiceNumber, InvoiceStatus, TaxRegion, Money
│   │                             (Finance's own — see §7.8 for why it's not
│   │                              Commerce's Money reused)
│   ├── Events/                   InvoiceWasCreated, InvoiceWasIssued, TaxRateWasUpdated
│   ├── Services/                 TaxCalculationService (pure, framework-free)
│   ├── Repositories/              TaxRateRepositoryInterface, InvoiceRepositoryInterface
│   │                              (owns InvoiceItem persistence too, same shape
│   │                               CRM's TicketRepositoryInterface has)
│   └── Exceptions/                InvoiceNotFoundException, TaxRateNotFoundException,
│                                  InvalidTaxRateException, OrderNotFoundException
│                                  (Finance's own, not Commerce's — added
│                                  unprompted, same reasoning CRM's
│                                  TagNotFoundException was)
├── Application/
│   ├── Actions/                  9 Actions — 8 wired to MCP (§6/§7.8)
│   └── DTOs/                     TaxRateData, InvoiceData, InvoiceItemData
├── Infrastructure/
│   ├── Models/                    3 Eloquent models — Invoice has no Eloquent
│   │                              relation to Commerce's Order/Customer Models
│   ├── Repositories/               2 Eloquent repository implementations
│   └── Services/                  CommerceTaxRateProvider — the *only* class in
│                                  this module that references `App\Modules\Commerce\*`,
│                                  and only its published Interface (§7.8)
└── FinanceServiceProvider.php    binds 2 Repository interfaces + Commerce's own
                                   TaxRateProviderInterface + registers 8
                                   capability handlers (see §6)

app/Modules/Workflows/            new in Phase 3
├── Domain/
│   ├── Entities/                 Workflow, WorkflowRule, WorkflowAction, WorkflowLog
│   │                             (WorkflowLog added unprompted, see §7.9)
│   ├── ValueObjects/             WorkflowStatus, EventType, Threshold
│   ├── Events/                   WorkflowWasTriggered, WorkflowActionExecuted
│   ├── Services/                 WorkflowEvaluator (pure, framework-free)
│   ├── Repositories/              WorkflowRepositoryInterface (owns WorkflowLog
│   │                              persistence too, same shape CRM's
│   │                              TicketRepositoryInterface has)
│   └── Exceptions/                WorkflowNotFoundException, InvalidWorkflowException
├── Application/
│   ├── Actions/                  7 Actions — 5 wired to MCP (§6/§7.9);
│   │                              ListWorkflowLogsAction added unprompted
│   │                              alongside WorkflowLog
│   └── Listeners/                InventoryLowListener (reacts to Commerce's
│                                  InventoryWasCommitted event) and, since the Tech
│                                  Debt Sprint (§7.13), CartAbandonedListener (reacts
│                                  to Commerce's new CartWasAbandoned event — both now
│                                  registered). HighValueOrderListener remains
│                                  documented, unwired scaffolding (§7.9/§9) — it
│                                  wasn't in that sprint's scope.
│   └── DTOs/                     WorkflowData, WorkflowRuleData, WorkflowActionData,
│                                  WorkflowLogData (added alongside WorkflowLog)
├── Infrastructure/
│   ├── Models/                    4 Eloquent models
│   └── Repositories/               1 Eloquent repository implementation
└── WorkflowsServiceProvider.php  binds 1 Repository interface +
                                   Event::listen()s InventoryLowListener and
                                   CartAbandonedListener (§7.13) +
                                   registers 5 capability handlers (see §6)

app/Modules/Loyalty/               new in Phase 3
├── Domain/
│   ├── Entities/                 LoyaltyAccount, PointTransaction, Reward, Redemption
│   ├── ValueObjects/             Points, TransactionType, RewardType, ExpirationDate
│   ├── Events/                   PointsWereEarned, PointsWereRedeemed,
│   │                             PointsWereExpired, RewardWasRedeemed
│   ├── Services/                 PointsCalculationService (pure, framework-free)
│   ├── Repositories/              LoyaltyAccountRepositoryInterface (owns Redemption
│   │                              persistence too), PointTransactionRepositoryInterface,
│   │                              RewardRepositoryInterface, + allForTenant() on
│   │                              LoyaltyAccountRepositoryInterface (Tech Debt Sprint, §7.13)
│   └── Exceptions/                LoyaltyAccountNotFoundException, InsufficientPointsException,
│                                  RewardNotFoundException, InvalidPointsException (all 4
│                                  requested) + CustomerNotFoundException,
│                                  LoyaltyAccountAlreadyExistsException (added
│                                  unprompted, same reasoning Finance's
│                                  OrderNotFoundException was — see §7.10)
├── Application/
│   ├── Actions/                  9 Actions — 8 wired to MCP (§6/§7.10);
│   │                              ExpirePointsAction is the one un-wired Action (now
│   │                              runs daily via BulkExpirePointsAction, new in §7.13,
│   │                              and the loyalty:expire-points scheduled command)
│   └── Listeners/                OrderPlacedListener (registered — reacts to
│                                  Commerce's existing OrderWasPlaced event)
│   └── DTOs/                     LoyaltyAccountData, PointTransactionData, RewardData, RedemptionData
├── Infrastructure/
│   ├── Models/                    4 Eloquent models
│   └── Repositories/               3 Eloquent repository implementations
└── LoyaltyServiceProvider.php    binds 3 Repository interfaces +
                                   Event::listen()s OrderPlacedListener +
                                   registers 8 capability handlers (see §6)

app/Modules/Reporting/             new in Phase 3
├── Domain/
│   ├── Entities/                 Report (saved definition), ReportResult
│   │                             (computed output, owned by
│   │                              ReportRepositoryInterface — same
│   │                              "repo owns its child records" shape
│   │                              Workflows'/Loyalty's own repos have)
│   ├── ValueObjects/             ReportType, DateRange (start/end-of-day
│   │                             normalized, validates ordering),
│   │                             ReportFilter (thin array wrapper)
│   ├── Services/                 SalesReportGenerator, TopProductsReportGenerator,
│   │                             TopCustomersReportGenerator, RevenueReportGenerator,
│   │                             LoyaltyReportGenerator (all pure,
│   │                              framework-free — see §7.11)
│   ├── Repositories/              ReportRepositoryInterface (Reporting's
│   │                              own 2 tables only — NOT what the
│   │                              Generate* Actions use to read
│   │                              Commerce/Loyalty data, see below)
│   └── Exceptions/                ReportNotFoundException, InvalidDateRangeException
├── Application/
│   ├── Actions/                  7 Actions — 5 wired to MCP (§6/§7.11);
│   │                              GetReportAction/ListReportsAction are
│   │                              the two un-wired ones
│   └── DTOs/                     ReportData (saved-Report wrapper) +
│                                  SalesReportData, TopProductsReportData,
│                                  TopCustomersReportData, RevenueReportData,
│                                  LoyaltyReportData (one per report type)
├── Infrastructure/
│   ├── Models/                    Report, ReportResult (2 Eloquent models)
│   ├── Repositories/               EloquentReportRepository (1)
│   └── Queries/                  **SalesQueryBuilder, TopProductsQueryBuilder,
│                                  TopCustomersQueryBuilder, RevenueQueryBuilder,
│                                  LoyaltyQueryBuilder** — query Commerce's/
│                                  Loyalty's Eloquent Models directly (the
│                                  one deliberate exception to the usual
│                                  Module -> Module Interface rule, §7.11).
│                                  Not behind an Interface, not bound in
│                                  the ServiceProvider — plain,
│                                  container-autowired concrete classes,
│                                  since there's exactly one way to
│                                  compute a SQL aggregate against
│                                  another module's current schema.
└── ReportingServiceProvider.php  binds 1 Repository interface + registers
                                   5 capability handlers (see §6) — no
                                   Event::listen() at all (this module
                                   reacts to nothing, it only reads)

app/Modules/Shipping/              new in Phase 4
├── Domain/
│   ├── Entities/                 ShippingMethod, Shipment (state
│   │                             machine — see §7.12 — + providerName/
│   │                             providerTrackingNumber fields and
│   │                             assignProviderTracking(), §7.14),
│   │                             TrackingEvent (immutable, owned by
│   │                              ShipmentRepositoryInterface)
│   ├── ValueObjects/             Money (Shipping's own, see §7.12),
│   │                             Weight, TrackingNumber (TRK-XXXXXXXX),
│   │                             TrackingStatus, ShippingRate (+ optional
│   │                             serviceName/serviceCode, §7.14), +
│   │                             ShippingProviderName (enum, §7.14), +
│   │                             Address (Shipping's own, §7.14)
│   ├── Services/                 ShippingRateCalculator (pure,
│   │                              framework-free — base_rate +
│   │                              weight_kg*rate_per_kg), +
│   │                              ShippingProviderInterface (§7.14 —
│   │                              mirrors Commerce's ConnectorInterface/
│   │                              ProductConnectorInterface)
│   ├── Repositories/              ShippingMethodRepositoryInterface,
│   │                              ShipmentRepositoryInterface (owns
│   │                              TrackingEvent persistence too, +
│   │                              findByTrackingNumber(), §7.14)
│   ├── Events/                   ShipmentWasCreated, ShipmentStatusChanged,
│   │                             TrackingEventWasAdded (none have a
│   │                              registered Listener this stage)
│   └── Exceptions/                ShippingMethodNotFoundException,
│                                  ShipmentNotFoundException,
│                                  InvalidWeightException (all 3
│                                  requested) + OrderNotFoundException
│                                  (added unprompted, same reasoning
│                                  Finance's/Loyalty's own were — §7.12),
│                                  + ShippingProviderException (implements
│                                  neither marker interface, §7.14) and
│                                  ShippingProviderNotFoundException
│                                  (NotFoundExceptionInterface, §7.14)
├── Application/
│   ├── Actions/                  9 Actions — all 8 Stage 1 requested
│   │                              capabilities wired (§6/§7.12), +
│   │                              GetProviderRatesAction/
│   │                              CreateProviderShipmentAction/
│   │                              SyncTrackingAction (§7.14);
│   │                              AddTrackingEventAction gained an
│   │                              optional occurredAt param (§7.14)
│   ├── Services/                 ShippingHttpClientInterface (mirrors
│   │                              WooCommerceClientInterface),
│   │                              ShippingProviderConfig (mirrors
│   │                              WooCommerceConfig),
│   │                              ShippingProviderRegistry (mirrors
│   │                              ConnectorRegistry — all new, §7.14)
│   └── DTOs/                     ShippingMethodData, ShipmentData,
│                                  TrackingEventData, ShippingRateData, +
│                                  ProviderRateData, ProviderShipmentData,
│                                  ProviderTrackingEventData (§7.14)
├── Infrastructure/
│   ├── Models/                    ShippingMethod, Shipment, TrackingEvent
│   ├── Http/                      MockShippingHttpClient (§7.14, tests only)
│   ├── Providers/                 MockShippingProviderAdapter (§7.14 —
│   │                              the one real ShippingProviderInterface
│   │                              implementation this stage)
│   └── Repositories/               EloquentShippingMethodRepository,
│                                  EloquentShipmentRepository (2)
└── ShippingServiceProvider.php   binds 2 Repository interfaces + (§7.14)
                                   ShippingProviderRegistry/
                                   ShippingHttpClientInterface, registers
                                   'mock' into the registry, and registers
                                   11 capability handlers (see §6)

config/shipping.php                new in Stage 2 — SHIPPING_PROVIDER*
                                   env vars (mirrors config/commerce.php)

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
│   ├── 2026_07_31_000021-000025                  (Phase 3.1 — tickets, ticket_comments,
│   │                                               customer_notes, tags, customer_tag pivot)
│   ├── 2026_07_31_000026-000028                  (Phase 3.2 — tax_rates, invoices, invoice_items)
│   ├── 2026_07_31_000029-000032                  (Phase 3.3 — workflows, workflow_rules,
│   │                                               workflow_actions, workflow_logs)
│   ├── 2026_07_31_000033-000036                  (Phase 3.4 — loyalty_accounts, point_transactions,
│   │                                               rewards, redemptions)
│   ├── 2026_07_31_000037-000038                  (Phase 3.5 — reports, report_results)
│   ├── 2026_07_31_000039-000042                  (Phase 4.1 — shipping_methods, shipments,
│   │                                               tracking_events, +orders shipping cols —
│   │                                               the first later-module migration to
│   │                                               alter an earlier module's own table, §7.12)
│   └── 2026_08_01_000043                          (Phase 4.2 — +shipments.provider_name/
│                                                   provider_tracking_number, both nullable,
│                                                   no FK, §7.14)
└── seeders/{DemoCapabilitiesSeeder,CommerceCapabilitiesSeeder,CRMCapabilitiesSeeder,FinanceCapabilitiesSeeder,WorkflowsCapabilitiesSeeder,LoyaltyCapabilitiesSeeder,ReportingCapabilitiesSeeder,ShippingCapabilitiesSeeder}.php

tests/
├── Fixtures/            woocommerce-products-response.json (Stage 6 — reference payload),
│                        shipping-rates-response.json, tracking-updates-response.json
│                        (Phase 4 Stage 2, §7.14 — reference payloads)
├── Unit/Commerce/       ~32 files — VOs, Entities, Domain Services, all framework-free PHPUnit
├── Feature/Commerce/    ~27 files — Actions against real sqlite :memory: DB, MCP HTTP end-to-end
├── Unit/CRM/            5 files — Ticket (incl. state machine), TicketComment,
│                        CustomerNote, Tag, TagName, all framework-free PHPUnit
├── Feature/CRM/         4 files — full MCP scenario + tenant isolation +
│                        the 4 un-wired Actions exercised directly
├── Unit/Finance/        6 files — TaxRate, Invoice, InvoiceItem, InvoiceNumber,
│                        TaxRegion, TaxCalculationService, all framework-free PHPUnit
├── Feature/Finance/     4 files — full MCP scenario + tenant isolation +
│                        the Commerce<->Finance tax integration (both fallback
│                        directions) + CreateInvoiceAction's own fallback chain
├── Unit/Workflows/      4 files — Workflow, WorkflowEvaluator (the important
│                        one — every condition type + AND-combination + inactive
│                        guard), WorkflowLog, Threshold, all framework-free PHPUnit
├── Feature/Workflows/   2 files — full real-Order-triggers-real-Listener
│                        scenario (no event faking) + tenant isolation +
│                        the one un-wired Action exercised directly
├── Unit/Loyalty/        5 files — Points, PointTransaction (incl. the
│                        sign-by-type invariant), LoyaltyAccount (earn/
│                        redeem/expire/adjust), ExpirationDate,
│                        PointsCalculationService, all framework-free PHPUnit
├── Feature/Loyalty/     2 files — full real-Order-triggers-real-Listener
│                        earn scenario (no event faking) + redeem/
│                        insufficient-points/tenant isolation +
│                        ExpirePointsAction (the one un-wired Action)
│                        exercised directly with simulated past-due expiry
├── Unit/Reporting/      6 files — DateRange, ReportFilter, and one test
│                        per Generator (SalesReportGenerator,
│                        TopProductsReportGenerator, RevenueReportGenerator,
│                        LoyaltyReportGenerator), all framework-free PHPUnit
├── Feature/Reporting/   1 file — 5 Customers, 10 Products, 20 real paid
│                        Orders (real Payments, real tax, real Loyalty
│                        points via OrderPlacedListener, no faking), all
│                        5 Generate* capabilities checked against
│                        independently-accumulated expected totals (not
│                        report-side math re-derived in the test) +
│                        tenant isolation on GetReportAction (exercised
│                        directly, not wired to MCP) + invalid date range
├── Unit/Shipping/       4 files — Weight, TrackingNumber, ShippingRateCalculator,
│                        and Shipment's own state machine (every legal
│                        and illegal transition, incl. Exception's
│                        recoverability), all framework-free PHPUnit, +
│                        MockShippingProviderAdapterTest/
│                        ShippingProviderRegistryTest (§7.14, 2 more files)
├── Feature/Shipping/    1 file — a real Order with 2 Products (2500g
│                        combined) -> real ShippingMethod -> rate
│                        preview -> real Shipment (real tracking number,
│                        real Order.assignShipping() write-back,
│                        verified via a direct OrderRepositoryInterface
│                        read) -> status transition -> tracking event ->
│                        tenant isolation -> status-filtered listing +
│                        invalid transition/nonexistent-order/forbidden, +
│                        ShippingProviderCapabilityTest (§7.14 — rates ->
│                        fulfill -> sync -> idempotent resync -> simulated
│                        provider failure -> tenant isolation)
├── Unit/Core/, Unit/MCP/, Feature/Demo/, Unit/Demo/   unchanged since Phase 1
├── Feature/Core/        + MCPRateLimitTest (Tech Debt Sprint, §7.13) +
│                        a new query-count regression test in
│                        CheckPermissionTest (the N+1 fix)
├── Feature/Commerce/    + InventoryConcurrencyTest (§8.22 regression +
│                        the reservation race fix) +
│                        MarkAbandonedCartsCommandTest (Tech Debt Sprint,
│                        §7.13 — the scheduler, cross-tenant)
├── Feature/Loyalty/     + ExpireLoyaltyPointsCommandTest (Tech Debt
│                        Sprint, §7.13 — the scheduler, cross-tenant)
├── Feature/Workflows/   + CartAbandonedListenerTest (Tech Debt Sprint,
│                        §7.13 — real CartWasAbandoned event, no faking,
│                        dispatched by the real scheduled command)
└── 483 tests total, 1147 assertions, ~10s runtime (`php artisan test`)
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

What Phase 3 *added* on top of that (see §7.7–§7.9 for the full reasoning
behind each) — these are the ones that matter most once more than one
Domain Module exists:

8. **Module -> Module dependency, one direction: depend on the other
   module's Domain Repository *Interface*, never its Infrastructure/Model
   or concrete Exception classes.** CRM depends on Commerce's
   `CustomerRepositoryInterface`; Finance depends on Commerce's
   `OrderRepositoryInterface`/`ProductRepositoryInterface`; Workflows'
   `InventoryLowListener` depends on Commerce's
   `InventoryRepositoryInterface`/`ProductRepositoryInterface`. In every
   case, the *returned Domain Entity* (e.g. Commerce's `Order`) is fine to
   read from — that's the published contract's return type, not a
   forbidden concrete dependency. What's forbidden is importing the other
   module's Eloquent Model, or throwing/catching the other module's
   concrete Exception class.
9. **A cross-module "does this exist" check always throws the *calling*
   module's own exception, never the depended-upon module's.** CRM's own
   `CustomerNotFoundException`, Finance's own `OrderNotFoundException` —
   both exist specifically so the calling module never imports the other
   module's concrete exception type, even though both happen to implement
   the same Core marker interface and produce an identical 404. See CRM's
   `CustomerNotFoundException` docblock for the fullest explanation.
10. **A two-way Module -> Module integration goes through an Interface the
    *depended-upon* module defines for itself, not one the dependency
    provides.** Commerce needed a real tax rate from Finance, but Commerce
    defines `TaxRateProviderInterface` (in its own `Application/Services`)
    and binds a harmless no-op default (`NullTaxRateProvider`); Finance's
    ServiceProvider rebinds it to a real implementation
    (`CommerceTaxRateProvider`) only if Finance happens to be installed.
    This is the exact same shape `PaymentGatewayInterface` already
    established — "the module that needs something defines the shape of
    what it needs," never "the module that provides something reaches
    into the consumer." Requires the provider's ServiceProvider to be
    registered *after* the consumer's in `bootstrap/providers.php` — safe
    regardless of `boot()` order, because Laravel runs every provider's
    `register()` before any `boot()` runs (§7.8 spells out the exact
    mechanics).
11. **A Domain Event Listener that reacts across a module boundary
    depends on the emitting module's Repository Interfaces the same as
    any other cross-module Action would** — a Listener is not a special
    case. `InventoryLowListener` re-fetches current state through
    `InventoryRepositoryInterface` rather than trusting whatever the
    event payload happens to carry, since events deliberately carry only
    identifiers (§7.9). If the event you need to react to doesn't exist
    yet, adding it is a small, additive, backward-compatible change to
    the emitting module (`InventoryWasCommitted` was added this way) —
    check first whether a close-enough event already exists before adding
    a new one.
12. **A missing piece the request implies but doesn't literally list (an
    Entity, a Repository method, an Action) gets added unprompted when
    skipping it would mean either bypassing an established convention or
    letting a real failure surface ugly** — always with a clear docblock
    explaining the gap and pointing to the precedent. `DiscountRepositoryInterface`
    (Phase 2), `TagNotFoundException` (CRM), `OrderNotFoundException`
    (Finance), and `WorkflowLog`+`ListWorkflowLogsAction` (Workflows) are
    the four examples so far — grep this file for "added unprompted" to
    find all of them and the exact reasoning each time.
13. **A capability or permission name that would need 2 or 4
    dot-separated segments gets restructured to exactly 3**, keeping the
    same semantic grouping the request specified rather than inventing
    new, more granular ones — `CapabilityName`/`PermissionKey` both
    enforce this (HANDOFF gotcha #2) and it has come up in every single
    module added so far except Loyalty and Reporting (WooCommerce, CRM,
    Finance, Workflows, and Shipping all needed at least one rename).
    Check every new capability/permission name against this *before*
    writing any code that references it — it's always cheaper to catch
    before the ServiceProvider/Seeder/tests are all written against the
    wrong name.

What Phase 4 *added* on top of that (§7.12 has the full reasoning):

14. **A module is allowed to write data back onto an *earlier* module's
    own entity, through a new mutator that module gains for exactly this
    purpose** — the mirror image of pattern #8 (which only covers
    *reading* another module's data through its Repository Interface).
    Shipping's `CreateShipmentAction` needed to record which
    Shipment/ShippingMethod fulfills an Order; rather than either (a)
    importing/mutating Commerce's `Order` in some ad-hoc way, or (b)
    leaving `orders` untouched and only ever answering "what ships this"
    from Shipping's own side, Commerce's `Order` entity gained one new
    mutator (`assignShipping()`) and a handful of new nullable fields —
    the exact same "widen with optional trailing state" shape pattern #6
    already established, just authored by the *dependent* module instead
    of the *owning* module's own next stage. The caller still only ever
    reaches this through the owning module's existing `Repository
    Interface::save()` — never a direct Model write, never a new
    Interface method that leaks the dependent module's concerns into the
    owner's contract. Use this sparingly: it's the right shape only when
    the literal requirement is "the other module's own record must
    change," not merely "I need to read/derive something from it" (which
    pattern #8 already covers) or "I need to react to something it did"
    (which pattern #11's Listener shape already covers).

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
php artisan db:seed   # runs Demo-, Commerce-, CRM-, Finance-, Workflows-, Loyalty-, Reporting-, and ShippingCapabilitiesSeeder

# Tests
php artisan test                                                  # full app suite — 470 tests, ~8s
cd packages/opencommerce-sdk; vendor/bin/phpunit tests; cd ../..   # SDK's own suite (unaffected by Phase 2)

# Manual/live verification
php artisan serve --port=8000
php examples/sample-agent.php <agent-token> http://127.0.0.1:8000/mcp/v1
php examples/woocommerce-sync.php <agent-token> http://127.0.0.1:8000/mcp/v1   # Stage 6 — set
                                                                                # WOOCOMMERCE_* in .env first,
                                                                                # or every call fails against
                                                                                # an empty base URL

# Scheduled jobs (Tech Debt Sprint, §7.13) — run once manually, or via a
# real OS cron entry (`* * * * * php artisan schedule:run`) in any actual
# deployment; routes/console.php's Schedule::command() calls only define
# *what* runs, not that anything triggers them automatically
php artisan loyalty:expire-points          # daily @ 02:00
php artisan commerce:check-abandoned-carts # hourly
php artisan schedule:list                  # confirm both are registered
```

To generate a throwaway Agent token for manual testing, see the Tinker
snippet in `packages/opencommerce-sdk/README.md`'s "Quick Start" section, or
look at any `registerAgentWithPermissions()` helper in
`tests/Feature/Commerce/*CapabilityTest.php` for the full Tenant → Organization
→ Agent → Role → Permission → Token chain needed to call an MCP capability
end to end.

---

## 6. The 57 MCP capabilities that exist right now

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
| `commerce.checkout.calculate` | P2.5 | `commerce.checkout.read` | Pure preview, no side effects. Optional `region` since P3.2 — real TaxRate lookup via Finance (§7.8). |
| `commerce.checkout.process` | P2.5 | `commerce.checkout.create` | The full Cart→Payment→Order flow. Optional `region` since P3.2, same as above. |
| `commerce.payment.refund` | P2.5 | `commerce.payments.refund` | Restores Inventory. |
| `commerce.coupon.create` | P2.5 | `commerce.coupons.create` | |
| `commerce.woocommerce.sync` | P2.6 | `commerce.connectors.sync` | Upserts a page of WooCommerce products into the catalog by SKU. |
| `commerce.woocommerce.get` | P2.6 | `commerce.connectors.read` | Live lookup straight from the Connector — not the local catalog. |
| `crm.ticket.create` | P3.1 | `crm.tickets.create` | Validates `customer_id` against Commerce's own `CustomerRepositoryInterface`. |
| `crm.ticket.get` | P3.1 | `crm.tickets.read` | Tenant-scoped by `findById()`; cross-tenant id -> 404, not 403. |
| `crm.ticket.list` | P3.1 | `crm.tickets.read` | Optional `status`/`customer_id`. |
| `crm.comment.create` | P3.1 | `crm.tickets.update` | Renamed from the requested `crm.ticket.comment.add` — 4 segments, see §7.7. |
| `crm.note.create` | P3.1 | `crm.customers.update` | Renamed from the requested `crm.customer.note.add` — same reason. |
| `finance.tax.create` | P3.2 | `finance.tax.manage` | `region: "DEFAULT"` registers the tenant's own fallback rate. |
| `finance.tax.get` | P3.2 | `finance.tax.read` | Looked up by region, not id. |
| `finance.tax.list` | P3.2 | `finance.tax.read` | Optional `is_active`. |
| `finance.invoice.create` | P3.2 | `finance.invoices.create` | From an existing Order; optional `region` (2-tier fallback, §7.8 — not the same chain Commerce's own checkout uses). |
| `finance.invoice.issue` | P3.2 | `finance.invoices.manage` | Draft -> Issued only. |
| `finance.invoice.get` | P3.2 | `finance.invoices.read` | Tenant-scoped by `findById()`, same shape as `crm.ticket.get`. |
| `finance.invoice.list` | P3.2 | `finance.invoices.read` | Optional `status`/`customer_id`. |
| `finance.tax.calculate` | P3.2 | `finance.tax.read` | Strict — an unconfigured region 404s, no fallback (contrast with `finance.invoice.create`'s region handling). |
| `workflow.definition.create` | P3.3 | `workflow.definitions.manage` | Renamed from the requested `workflow.create` — 2 segments, see §7.9. Requires ≥1 rule and ≥1 action. |
| `workflow.definition.get` | P3.3 | `workflow.definitions.read` | Renamed from `workflow.get` — same reason. |
| `workflow.definition.list` | P3.3 | `workflow.definitions.read` | Renamed from `workflow.list` — same reason. Optional `status`/`event_type`. |
| `workflow.event.trigger` | P3.3 | `workflow.definitions.execute` | Renamed from `workflow.trigger` — same reason. Same code path `InventoryLowListener` calls internally. |
| `workflow.log.list` | P3.3 | `workflow.definitions.read` | Already 3 segments, unchanged. Optional `workflow_id`/`limit`. |
| `loyalty.account.get` | P3.4 | `loyalty.accounts.read` | Strict lookup by `customer_id` — 404 if none exists yet. |
| `loyalty.account.create` | P3.4 | `loyalty.accounts.create` | Validates the Customer exists (Commerce's `CustomerRepositoryInterface`); 409 if one already exists (rule §d.2). |
| `loyalty.points.earn` | P3.4 | `loyalty.points.manage` | Find-or-create — unlike `.get`, a missing LoyaltyAccount is silently opened first (§7.10). |
| `loyalty.points.redeem` | P3.4 | `loyalty.points.redeem` | `points` must match the named Reward's `points_required` exactly (422 if not) before the balance check (409 if insufficient). |
| `loyalty.reward.create` | P3.4 | `loyalty.rewards.manage` | `discount_amount` required only when `reward_type` is `discount_coupon`. |
| `loyalty.reward.get` | P3.4 | `loyalty.rewards.read` | |
| `loyalty.reward.list` | P3.4 | `loyalty.rewards.read` | Optional `is_active`. |
| `loyalty.transaction.list` | P3.4 | `loyalty.transactions.read` | By `customer_id`, not `loyalty_account_id`. Optional `limit`. |
| `report.sales.generate` | P3.5 | `reporting.sales.read` | `salesByDay` keyed by `Y-m-d`. Excludes Cancelled/Refunded orders. |
| `report.products.top` | P3.5 | `reporting.products.read` | Optional `limit` (default 10). Ranked by quantity sold, fully in SQL. |
| `report.customers.top` | P3.5 | `reporting.customers.read` | Optional `limit` (default 10). Ranked by total spent, fully in SQL. |
| `report.revenue.generate` | P3.5 | `reporting.revenue.read` | Only counts Orders with ≥1 `completed` Payment. `netRevenue` excludes tax (§7.11). |
| `report.loyalty.generate` | P3.5 | `reporting.loyalty.read` | `activeAccounts` is a current-balance snapshot, not date-range-scoped (§7.11). |
| `shipping.method.create` | P4.1 | `shipping.methods.create` | |
| `shipping.method.list` | P4.1 | `shipping.methods.read` | Optional `is_active`. |
| `shipping.rate.calculate` | P4.1 | `shipping.rates.read` | Pure preview, no side effects. |
| `shipping.shipment.create` | P4.1 | `shipping.shipments.create` | Weighs the Order's Products (via `attributes['weight_grams']`, §7.12), prices it, generates a tracking number, and writes the assignment onto the Order. |
| `shipping.shipment.get` | P4.1 | `shipping.shipments.read` | |
| `shipping.shipment.list` | P4.1 | `shipping.shipments.read` | Optional `status`/`order_id`. |
| `shipping.shipment.transition` | P4.1 | `shipping.shipments.update` | Renamed from the requested `shipping.shipment.status.update` — 4 segments, see §7.12. |
| `shipping.tracking.add` | P4.1 | `shipping.shipments.update` | Renamed from the requested `shipping.tracking.event.add` — same reason. Does not itself change the Shipment's own status. |
| `shipping.provider.rates` | P4.2 | `shipping.providers.read` | Live rates from an external provider (`mock` by default) — `provider` optional. |
| `shipping.provider.fulfill` | P4.2 | `shipping.providers.create` | Renamed from the requested `shipping.provider.shipment.create` — 4 segments, see §7.14. Records the provider's own tracking number onto the Shipment. |
| `shipping.tracking.sync` | P4.2 | `shipping.providers.sync` | Looks the Shipment up by its own internal `tracking_number` (not the provider's). Idempotent — a re-sync adds 0 events. |

**Deliberately NOT wired to MCP** despite the underlying Action existing and
being fully tested (see §8.2 for why, and the same reasoning each time):
`RemoveFromCartAction` (no `commerce.cart.remove`), `UpdateCartItemQuantityAction`,
`ClearCartAction`, `CancelOrderAction` (no `commerce.order.cancel`),
`UpdateOrderStatusAction`, `GetCustomerOrdersAction` (no
`commerce.customer.orders`), `GetPaymentAction` (no `commerce.payment.get`),
CRM's `UpdateTicketAction`, `GetCustomerNotesAction`, `CreateTagAction`,
`AssignTagToCustomerAction` (§7.7), Finance's `UpdateTaxRateAction` (§7.8),
Workflows' `UpdateWorkflowAction` (§7.9), Loyalty's `ExpirePointsAction`
(§7.10 — the actual blocker is the same "no scheduler exists" gap
CartAbandonedListener has, HANDOFF §8.23), and Reporting's `GetReportAction`/
`ListReportsAction` (§7.11 — no `report.definition.get/list` capability
was requested this stage).
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

### 7.8 Phase 3, Stage 2 — Finance (per-tenant tax + Invoices)

Entities: `TaxRate` (tenant + `TaxRegion` + `ratePercentage` int,
percentage×100 — distinct from Commerce's own `ValueObjects\TaxRate`,
which is a transient 0-100 float calculation input, not a persisted
per-region row; the shared name is coincidental, the two are never
interchangeable), `Invoice` (frozen `InvoiceItem[]`, mirrors Order/
OrderItem's Immutable Order Items shape), `InvoiceItem` (no `id`/
`invoiceId` property on the Domain Entity, same HANDOFF gotcha #10
shape OrderItem/Discount/CRM's TicketComment already have, even though
the `invoice_items` table itself has an `id` primary key like every
table does). VOs: `InvoiceNumber` (`INV-YYYYMMDD-XXXXX`, mirrors
OrderNumber's random-suffix-plus-collision-retry generation exactly),
`InvoiceStatus` (draft/issued/paid/cancelled — only Draft->Issued is
reachable so far, `IssueInvoiceAction`'s only transition), `TaxRegion`
(`XX-YYYY` format, e.g. `US-CA`, plus the reserved `DEFAULT` value —
see below), and Finance's **own** `Money` — a deliberate duplicate of
Commerce's `Money`, not a shared/reused class. Domain Service:
`TaxCalculationService` — pure, no Repository dependency, the same
shape Commerce's `PricingService` has (only knows how to combine
numbers it's given; never decides *which* TaxRate applies).

**Why Finance has its own `Money` instead of importing Commerce's**:
depending on Commerce's Repository *Interfaces* is fine (Dependency
Inversion, per this stage's explicit rule, same as CRM), but importing
Commerce's concrete `Money` VO would be a direct Domain-layer
dependency on another module's class — the identical coupling CRM's own
`CustomerNotFoundException` docblock explains why to avoid. Neither
Core nor any module hosts a shared kernel for something as small and
stable as `Money`, so duplicating roughly 40 lines per module was judged
cheaper than creating one — this is a explicit, considered tradeoff, not
an oversight.

**`TaxRegion::default()` (the literal string `"DEFAULT"`) is a
first-class, documented concept**, not a magic string: it's a tenant's
fallback tax rate when no rate is configured for whatever more specific
region a caller asks about. `finance.tax.create` with `region: "DEFAULT"`
registers it like any other region.

**The platform's first *two-way* Module -> Module integration.** Finance
depends on Commerce's `OrderRepositoryInterface`/`ProductRepositoryInterface`
to build an Invoice from an Order (`CreateInvoiceAction`) — ordinary
CRM-style Dependency Inversion, one direction. The new direction:
Commerce's own checkout pricing now needs a real tax rate from Finance.
Making that safe without Commerce ever importing `App\Modules\Finance\*`
required Commerce to define its own outbound port —
`Commerce\Application\Services\TaxRateProviderInterface` — mirroring
exactly how `PaymentGatewayInterface` already let Commerce depend on
"a thing that can charge a card" without knowing which gateway.
`Commerce\Application\Services\NullTaxRateProvider` is Commerce's own
default (`CommerceServiceProvider::register()` binds it) — always
returns null, so Commerce works completely standalone with its old
hardcoded-9%-fallback behavior if Finance is ever removed.
`Finance\Infrastructure\Services\CommerceTaxRateProvider` implements
that same Interface using Finance's own `TaxRateRepositoryInterface`,
and `FinanceServiceProvider::register()` rebinds the Interface to it —
this is the *only* class anywhere in Finance that references
`App\Modules\Commerce\*` at all, and it references only Commerce's
published Interface, never a Commerce Entity, Model, or Exception.
This only works because `bootstrap/providers.php` registers
`FinanceServiceProvider` after `CommerceServiceProvider` — Laravel runs
every provider's `register()` before any `boot()`, so Finance's rebind
is guaranteed to win regardless of boot order.

**Two different, deliberately non-unified fallback chains exist** — do
not try to merge them:
1. `CommerceTaxRateProvider::getRatePercent()` (used by Commerce's own
   `CalculatePricingAction`/`ProcessPaymentAction`): try the given
   region, then `TaxRegion::default()`, then return null — which those
   two Actions interpret as "use the hardcoded 9%." Never throws.
2. `CreateInvoiceAction`'s own inline fallback: try the given region,
   then `TaxRegion::default()`, then charge **zero** tax — no hardcoded
   percentage, because that 9% constant belongs to Commerce's pricing
   policy, not Finance's invoicing policy.
3. `CalculateTaxAction` (backs `finance.tax.calculate`) has no fallback
   at all — an unconfigured region is `TaxRateNotFoundException` (404),
   full stop. This is the strict, explicit sibling to the other two's
   graceful degradation, for a caller that named a specific region and
   wants a real answer or an explicit failure.

`OrderNotFoundException` (Finance's own, not Commerce's) wasn't in the
original request — added unprompted for the same reason CRM's
`TagNotFoundException` was: `CreateInvoiceAction` needs a real 404 for
an unknown/cross-tenant `order_id`, and it must be Finance's own
exception type per the module-independence rule above.

**`UpdateTaxRateAction` is the one Finance Action not wired to MCP** this
stage — region is intentionally not updatable through it (mirrors
Product's SKU/Category's slug being immutable-after-creation), only
`ratePercentage`/`isActive`. Exercised directly in
`tests/Feature/Finance/UpdateTaxRateActionTest.php`.

### 7.9 Phase 3, Stage 3 — Workflows (event-driven automation)

Entities: `Workflow` (aggregate root — rules/actions frozen at creation,
same Immutable Order Items shape Order/Invoice already establish),
`WorkflowRule` (`conditionType`/`field`/`Threshold` — no `id` on the
Domain Entity, same HANDOFF gotcha #10 shape every other child entity in
this codebase has), `WorkflowAction` (`actionType`/`parameters`, same
shape), `WorkflowLog` (see below). VOs: `WorkflowStatus`
(active/inactive/paused), `EventType` (inventory_low/cart_abandoned/
order_high_value), `Threshold` (a non-negative int wrapper). Domain
Service: `WorkflowEvaluator` — pure, framework-free, the same shape
Commerce's `PricingService`/Finance's `TaxCalculationService` already
establish. A Workflow's rules are **AND-combined** (every rule must
match, not just one) — not explicitly specified, a deliberate, documented
default; an empty rule set never matches (`CreateWorkflowAction` already
refuses to create one, `WorkflowEvaluator::evaluate()` guards it too).

**`WorkflowLog` (Entity + Model + DTO + `ListWorkflowLogsAction`) wasn't
in the original request** — added unprompted for the same reason CRM's
`TagNotFoundException`/Finance's `OrderNotFoundException` were: the
request named `workflow_logs` (a table), `workflow.log.list` (a
capability), and exactly one Repository interface for the whole module —
something has to give that table and capability a structured shape, and
per this stage's own established convention (CRM's
`TicketRepositoryInterface` owns `TicketComment`, Finance's
`InvoiceRepositoryInterface` owns `InvoiceItem`), the natural owner is
`WorkflowRepositoryInterface::saveLog()`/`listLogs()`, not a second,
dedicated Repository interface.

**The platform's first real cross-module Domain Event Listener.** Every
event dispatched since Phase 1 (`ProductWasCreated`, `OrderWasPlaced`,
`InvoiceWasCreated`, ...) had zero registered listeners — `Event::listen()`
simply never appeared anywhere in this codebase until
`WorkflowsServiceProvider::boot()`. `InventoryLowListener::handle()`
reacts to a **new** Commerce event, `InventoryWasCommitted` (dispatched
from `PlaceOrderAction`'s commit loop) — added this stage because no
event previously existed for "stock actually went down," only
`InventoryReserved` (the soft-hold side; see that new event's own
docblock). The Listener depends on Commerce's
`InventoryRepositoryInterface`/`ProductRepositoryInterface` — Interfaces,
never Commerce's Infrastructure/Model classes — the identical Dependency
Inversion direction CRM/Finance already established, just triggered by
an Event instead of a direct Action call.

**Two Listeners exist as deliberate, documented, unwired scaffolding** —
`CartAbandonedListener` and `HighValueOrderListener` — per this stage's
explicit scope ("فعلاً فقط یک Workflow ساده پیاده‌سازی می‌کنیم"). Neither
is registered via `Event::listen()`. `CartAbandonedListener` has a real
technical gap blocking it (cart abandonment is a time-based condition —
"idle for 24h" — which needs a scheduled job polling Carts, not an Event
Listener; no scheduling mechanism exists anywhere in this codebase yet).
`HighValueOrderListener` has **no** technical blocker at all — Commerce's
`OrderWasPlaced` event already exists and already carries everything
needed (see that Listener's own docblock for the exact `handle()` this
would be) — it is unwired purely because this stage's scope named only
Low Stock Alert as functional.

**Capability and permission names were renamed from the request** —
`workflow.create/get/list/trigger` were all 2 segments; `CapabilityName`
requires exactly 3 (HANDOFF gotcha #2, hit again the same way
WooCommerce's/CRM's capabilities hit it). Renamed to
`workflow.definition.create/get/list` and `workflow.event.trigger`
(`workflow.log.list` was already compliant). Permissions
(`workflows.manage/read/execute`, also 2 segments — `PermissionKey` has
the identical requirement) became `workflow.definitions.manage/read/execute`,
keeping the exact same 3 permission groupings the request specified.

**A real, pre-existing Commerce quirk surfaced while writing this
stage's own end-to-end test, not introduced by this stage**:
`CheckInventoryAction`'s re-check inside `PlaceOrderAction` validates a
Cart item's quantity against `Inventory::available()` — which already
has *that same Cart's own reservation* subtracted out. Ordering more
than half of on-hand stock (e.g. 7 of 10) makes that re-check fail even
though the exact right amount was already correctly reserved earlier by
`AddToCartAction`. Every existing Commerce/Finance test happens to order
small-enough quantities to never hit this. Not fixed here — it's a
pre-existing Commerce behavior, out of scope for a Workflows PR to
change — but `tests/Feature/Workflows/WorkflowsCapabilityTest.php`'s own
docblock documents the exact numbers (6 on hand, order 3) chosen to stay
under that ceiling while still crossing the Low Stock Alert's `<5`
threshold once committed. Flagged in §8 as a debt item worth a real fix.

### 7.10 Phase 3, Stage 4 — Loyalty Program (points, Rewards, Redemptions)

Entities: `LoyaltyAccount` (one per Customer per tenant, rule §d.2),
`PointTransaction` (immutable ledger row, same shape Commerce's
`OrderItem`/Workflows' `WorkflowLog` already establish), `Reward`,
`Redemption` (see below). VOs: `Points` (a non-negative *amount* —
deliberately never the same type as `PointTransaction`'s own signed
`points` delta column; that Entity's own docblock explains why they're
not unified), `TransactionType` (earn/redeem/expire/adjust/bonus),
`RewardType` (discount_coupon/free_product/free_shipping — only
`DiscountCoupon` does anything with `discount_amount` this stage, the
other two are modeled-but-unfulfilled the same way Workflows'
`EventType::CartAbandoned`/`OrderHighValue` are), `ExpirationDate` (a
1-year-default, configurable validity window, rule §d.5). Domain
Service: `PointsCalculationService` — pure, framework-free, the same
shape Commerce's `PricingService`/Finance's `TaxCalculationService`/
Workflows' `WorkflowEvaluator` already establish (rule §d.6: $1 = 100
cents = 1 point, integer division, always rounds down).

**`LoyaltyAccount.current_balance` is maintained directly, not
recomputed from the other two totals on read** — `earn()`/`redeem()`
move it in lock-step with `total_points_earned`/`total_points_redeemed`;
`expire()` subtracts from `current_balance` alone. This still satisfies
the requested formula
(`current_balance = total_points_earned - total_points_redeemed - expired_points`)
exactly, because those are the only three operations (plus `adjust()`)
that ever touch it — there is deliberately no `total_points_expired`
column in the schema (see `PointTransactionRepositoryInterface::findExpirable()`'s
docblock for how an already-expired batch is recognized without one).

**Two exceptions weren't in the original request's list of 4** — added
unprompted for the same reason CRM's `TagNotFoundException`/Finance's
`OrderNotFoundException`/Workflows' `WorkflowLog` were (HANDOFF §3 item
12):
- `CustomerNotFoundException` (Loyalty's own, not Commerce's) —
  `CreateLoyaltyAccountAction` validates a `customer_id` against
  Commerce's `CustomerRepositoryInterface` (Dependency Inversion — an
  Interface, never Commerce's Infrastructure/Model), and must throw its
  own exception type for the same reason CRM's own
  `CustomerNotFoundException` docblock explains.
- `LoyaltyAccountAlreadyExistsException` — rule §d.2 ("one LoyaltyAccount
  per Customer per tenant") needs a real 409 CONFLICT when
  `loyalty.account.create` is called twice for the same Customer, rather
  than letting `loyalty_accounts`' own `unique(tenant_id, customer_id)`
  constraint surface as a raw database error.

**`loyalty.points.earn` finds-or-creates the LoyaltyAccount; `loyalty.account.get`
does a strict lookup (404 if missing)** — a deliberate difference between
the two verbs, not an inconsistency. Earning points for a first-time
purchaser who has no LoyaltyAccount yet is an entirely normal case (the
capability's own input is just `customer_id`, no requirement that
`loyalty.account.create` was called first) — `EarnPointsAction` composes
`CreateLoyaltyAccountAction` internally (Actions composing Actions,
HANDOFF §3 item 3) rather than requiring the caller to provision one
explicitly first.

**`loyalty.points.redeem`'s `points` input is validated against the
named Reward's own `points_required` before the balance is even
checked** — a mismatch is `InvalidPointsException` (422), not silently
accepted or conflated with `InsufficientPointsException` (409, thrown
only once the price is confirmed correct but the balance genuinely isn't
there). This makes the capability's redundant-looking `points`/`reward_id`
pair meaningful: the caller states the price it expects to pay, and a
stale expectation fails loudly instead of silently charging whatever the
Reward costs today.

**`Redemption` wasn't in the original request's Repository list** — the
request names a `redemptions` table and this Entity but only 3
Repository interfaces for the whole module, the same gap Workflows'
`WorkflowLog` had. Per that stage's own precedent (a Repository
interface owns its child records), the natural owner is
`LoyaltyAccountRepositoryInterface::saveRedemption()`/`listRedemptions()`,
not a 4th interface.

**The platform's second real cross-module Domain Event Listener.**
`OrderPlacedListener::handle(OrderWasPlaced $event)` earns points for the
placed Order's Customer, if it has one. Unlike Workflows'
`InventoryLowListener` (which re-fetches Inventory through a Repository
because `InventoryWasCommitted` deliberately carries only identifiers),
`OrderWasPlaced` already carries the full, authoritative `Order` entity
— its `total()`/`customerId()` are exactly what's needed, so no Commerce
Repository dependency was required at all for this Listener (the same
observation Workflows' own unwired `HighValueOrderListener` docblock
already made about this same event). Orders with no `customer_id`
(optional since Stage 4/Commerce) or worth less than $1 (rounds to 0
points) are silently skipped — neither is an error.

**`ExpirePointsAction` is a deliberate simplification, not a full
per-lot FIFO ledger.** It processes one LoyaltyAccount at a time (no
`loyalty.points.expire` capability was requested — same "built, tested,
not yet exposed to Agents" gap Finance's `UpdateTaxRateAction`/Workflows'
`UpdateWorkflowAction` carry), finding `earn`/`bonus` PointTransactions
whose `expires_at` is due and not already expired (recognized via an
`expire` transaction's `reference_id` pointing back at the source row —
no mutable "processed" flag is ever added to an immutable ledger row),
oldest first, expiring each fully but capped by whatever balance
genuinely remains. It does **not** track which specific Redemption
consumed which specific earn-batch — a Customer who redeems mostly from
a recent batch will still see an *older*, unrelated batch expire first,
which is the tenant-favoring (conservative) outcome, not the
customer-favoring one a true per-lot ledger would give. Flagged as a
simplification worth a real fix (§8), not silently broken behavior — no
point this method ever expires was un-redeemed or not yet due.
**Not wired to MCP** — and can't usefully run on a schedule yet anyway,
since **no scheduling mechanism exists anywhere in this codebase**
(HANDOFF §8.23, unchanged since Workflows' Stage) — the identical blocker
`CartAbandonedListener` has.

**Capability names needed no renaming this stage** — all 8 requested
names (`loyalty.account.get/create`, `loyalty.points.earn/redeem`,
`loyalty.reward.create/get/list`, `loyalty.transaction.list`) and all 7
requested permissions were already exactly 3 dot-separated segments,
unlike WooCommerce/CRM/Workflows (HANDOFF gotcha #2) — the first stage
where this didn't come up.

### 7.11 Phase 3, Stage 5 — Reporting (read-only analytics)

Entities: `Report` (the saved *definition* of a report run — type, date
range, filters, which Agent ran it; immutable, no update method),
`ReportResult` (the computed numbers from one run, kept separate so a
Report can be re-run without overwriting its history — same "parent
definition, child result" split Workflows' `Workflow`/`WorkflowLog`
already establish). VOs: `ReportType` (sales/top_products/top_customers/
revenue/loyalty), `DateRange` (validates `end >= start` at construction,
normalizes to start-of-day/end-of-day so an inclusive whole-day range
never silently drops that last day's data), `ReportFilter` (a thin,
typed array wrapper for per-report-type extras like `limit`). Domain
Services: one Generator per report type
(`SalesReportGenerator`/`TopProductsReportGenerator`/
`TopCustomersReportGenerator`/`RevenueReportGenerator`/
`LoyaltyReportGenerator`) — pure, framework-free, the same shape
Commerce's `PricingService`/Loyalty's `PointsCalculationService` already
establish: each one only combines numbers/rows a Query Builder already
aggregated in SQL, never re-sums raw rows in a PHP loop itself.

**The platform's first genuinely read-only module.** Every prior Phase 3
module wrote its own data (CRM/Finance/Workflows/Loyalty all persist
their own aggregates) in addition to reading others'. Reporting only
ever writes its own `reports`/`report_results` rows (the saved record of
having run a report) — it never mutates Commerce, Loyalty, or anything
else.

**The platform's first deliberate, documented exception to "Module ->
Module depends on a Repository Interface, never a Model"** (HANDOFF §3
item 8). The five `Infrastructure/Queries/*` Query Builders
(`SalesQueryBuilder`, `TopProductsQueryBuilder`, `TopCustomersQueryBuilder`,
`RevenueQueryBuilder`, `LoyaltyQueryBuilder`) query Commerce's
(`Order`/`OrderItem`/`Payment`) and Loyalty's (`LoyaltyAccount`/
`PointTransaction`) Eloquent Models *directly* — see `SalesQueryBuilder`'s
own docblock for the complete reasoning, condensed here: computing a
SUM/COUNT/GROUP BY aggregate through `OrderRepositoryInterface::listByTenant()`
would mean fetching every matching Order as a full Domain Entity and
summing in a PHP loop — exactly the anti-pattern rule §e ("از Eloquent
aggregates استفاده کن, نه loop در PHP") forbids — and extending that
Interface with five report-shaped aggregate methods would leak
Reporting's query needs into a write-side Domain contract Commerce
itself doesn't need. This is safe specifically because every Query
Builder is SELECT-only (standard CQRS "Read Model" territory — a
read-only projection may cut across aggregate boundaries a write
operation never could) and the coupling is explicit and contained to
exactly 5 classes: if Commerce's/Loyalty's schema changes, these are the
only files that need to change. Crucially, this exception is scoped
*only* to bulk aggregation — every single-entity detail lookup in this
module (a Product's name for Top Products, a Customer's full name for
Top Customers/Loyalty) still goes through the proper
`ProductRepositoryInterface`/`CustomerRepositoryInterface`, exactly like
every prior module. Query Builders are plain, container-autowired
concrete classes with no Interface and no ServiceProvider binding
(`ReportingServiceProvider`'s own docblock) — there's exactly one way to
run a SQL aggregate against another module's current schema, so an
Interface+Implementation split would be pure ceremony.

**Bounded per-row Repository lookups, not a new batch method.** Both
`GenerateTopProductsReportAction` and `GenerateTopCustomersReportAction`
(and `GenerateLoyaltyReportAction` for its `topEarners`) call
`findById()` once per already-ranked-and-limited row (≤ `limit`, default
10) to resolve a display name — the same "small, bounded per-item
lookup, not a batch method added to another module's Interface"
precedent Finance's `CreateInvoiceAction` already established for
resolving an OrderItem's Product name.

**`net_revenue` deliberately excludes tax.** `RevenueReportGenerator`'s
formula is `net_revenue = gross_revenue - discounts_applied` — tax is
money collected on behalf of a tax authority, not revenue the business
keeps or loses, so it's reported alongside gross/net but never netted
against either. `RevenueQueryBuilder` also only counts an Order toward
revenue if it has at least one `completed` Payment (rule §e.4) — an
Order placed but never actually paid contributed no real revenue — using
`whereExists` rather than a JOIN specifically to avoid double-counting
an Order's amounts if it somehow has more than one `completed` Payment
row.

**`loyalty` report's `active_accounts` is a current-balance snapshot,
not date-range-scoped** — "how many accounts currently hold a positive
balance" rather than "how many had any transaction in this window". A
deliberate choice (`LoyaltyQueryBuilder`'s own docblock): an account's
overall activity naturally spans beyond any one report's window,
documented explicitly so a future report wanting the date-scoped
definition instead doesn't silently assume this one already means that.

**`GetReportAction`/`ListReportsAction` aren't wired to MCP this
stage** — no `report.definition.get`/`.list` capability was among the 5
requested (only the 5 Generate* ones were). Both exercised directly in
`tests/Feature/Reporting/ReportingCapabilityTest.php`, the same "built,
tested, not yet exposed to Agents" gap every prior module has carried at
least one of (§6).

**Capability and permission names needed no renaming this stage** — all
5 requested capability names and all 5 requested permissions were
already exactly 3 dot-separated segments, the second stage in a row
(after Loyalty) where this didn't come up.

### 7.12 Phase 4, Stage 1 — Shipping (ShippingMethods, Shipments, TrackingEvents)

Entities: `ShippingMethod` (a tenant-defined carrier/tier, no
update/deactivate method — same "structure frozen, not requested"
shape Loyalty's `Reward` has), `Shipment` (state machine — see below),
`TrackingEvent` (immutable audit log entry, no `tenant_id` of its own,
inherited through `shipment_id` — same shape `order_items`/
`ticket_comments`/`workflow_rules` already have). VOs: `Money`
(Shipping's own, deliberately a second, separate class from Commerce's —
identical reasoning to Finance's own `Money` docblock: depending on
Commerce's Repository *Interfaces* is fine, importing its concrete
`Money` VO would be a direct Domain-layer dependency on another
module's class), `Weight` (non-negative grams, HANDOFF gotcha #4
applied to a physical unit instead of money), `TrackingNumber`
(`TRK-XXXXXXXX`, random — not date-based like `OrderNumber`/
`InvoiceNumber`, since a tracking number has no "which day" component
worth encoding), `TrackingStatus`, `ShippingRate` (a computed cost +
the method's own estimated delivery window — never persisted on its
own, `CalculateShippingRateAction` is a preview, no side effects, the
same "preview vs. durable apply" split `CalculatePricingAction`/
`ApplyCouponAction` establish, HANDOFF §3 item 4). Domain Service:
`ShippingRateCalculator` — pure, framework-free, the same shape
Commerce's `PricingService`/Loyalty's `PointsCalculationService`
already establish (rule §d.2: `base_rate + (weight_kg × rate_per_kg)`,
rounded to the nearest cent).

**Shipment's state machine** (rule §d.3: "pending → in_transit →
delivered (یا returned/exception)"): `Delivered`/`Returned` are the only
true terminal states; `Exception` is deliberately **recoverable**
(`Exception -> InTransit`/`Returned` are both allowed) — a real-world
carrier problem can be resolved and the shipment resumes transit, unlike
a delivery that already happened or a package that was already sent
back. `changeStatus()` also stamps `shipped_at`/`delivered_at` the first
time the Shipment ever reaches `InTransit`/`Delivered`, mirroring
Order's own `confirm()`/`cancel()` pattern of a transition carrying its
own side effect. `AddTrackingEventAction` deliberately does **not** call
`changeStatus()` — a `TrackingEvent` is a carrier-reported log entry
("arrived at sorting facility"), while `Shipment.status` is the
authoritative current state, changed only through
`UpdateShipmentStatusAction`'s own transition validation; keeping the
two independent means logging many intermediate carrier updates never
has to also satisfy the Shipment's own state-machine rules.

**Weight comes from `Product.attributes['weight_grams']`, not a
first-class Product field.** Commerce's `Product` entity has no
dedicated Weight concept — `CreateShipmentAction` reads the free-form
`attributes` bag Phase 1 already established for exactly this kind of
ad-hoc, module-specific data, defaulting to 0 grams for a Product with
none set (not an error — plenty of legitimate Products, e.g. a digital
good or one created before Shipping existed, may never need one).
Adding a first-class `weight` column to `products` was considered and
rejected: it would mean modifying Commerce's own migration/Domain Entity
for a concern only Shipping currently has a use for, where the existing
extension point already covers it.

**The first later-module migration to alter an earlier module's own
table.** Every prior cross-module integration (Finance's
`TaxRateProviderInterface`, Workflows'/Loyalty's Listeners) only ever
added a new Interface, a new Event, or a brand-new table — none of them
touched an existing table belonging to a different module. Shipping's
`CreateShipmentAction` needs to record which Shipment/ShippingMethod
fulfills an Order, and — after considering the alternative (leaving
`orders` completely untouched and only ever answering "what ships this
Order" via `shipping.shipment.list`'s own `order_id` filter, entirely
Shipping-side) — the literal, explicit request asks for
`shipping_method_id`/`shipment_id`/`shipping_cost` to live on the Order
itself, the same way `customer_id` (Stage 4) does. The chosen
implementation keeps this as narrow as possible: `Order` gained one new
mutator, `assignShipping()`, and three new nullable, backward-compatible
fields (HANDOFF §3 pattern #6 — the exact same "widen with optional
trailing state" shape `customerId`/`tax`/`discount`/`total` already
used); Commerce's Domain/Application layers otherwise never reference
Shipping at all. `2026_07_31_000042_add_shipping_to_orders_table.php`
adds the 4 columns with **no FK constraint** on `shipping_method_id`/
`shipment_id` — the identical "cross-module reference through an
Interface, not a database-level FK" reasoning `shipments.order_id` has
in the other direction (that migration's own docblock). This is a real,
accepted coupling — a future rename of `orders.shipping_cost_amount`
would need Shipping's own `EloquentOrderRepository` usage sites touched
too — not a hidden one, and it's isolated to exactly the 4 files this
paragraph names.

**One exception wasn't in the original request's list of 3** — added
unprompted for the same reason Finance's `OrderNotFoundException`/
Loyalty's `CustomerNotFoundException` were: `CreateShipmentAction`
validates an `order_id` against Commerce's `OrderRepositoryInterface`
(Dependency Inversion), and must throw its own exception type rather
than Commerce's concrete one.

**Two capability names needed renaming** — `shipping.shipment.status.update`
and `shipping.tracking.event.add` were both 4 dot-separated segments;
`CapabilityName` requires exactly 3 (HANDOFF gotcha #2, hit again the
same way WooCommerce's/CRM's/Workflows' capabilities hit it — the first
time in 3 stages this has come up again after Loyalty/Reporting didn't
need it). Renamed to `shipping.shipment.transition` and
`shipping.tracking.add`, restructuring the same 3 semantic groupings the
request specified rather than inventing new, more granular ones.

### 7.13 Tech Debt Sprint (between Phase 4 Stage 1 and Stage 2)

Requested as 7 items, ranked critical/important/quality. Two of the
seven came with a diagnosis or a deliverable that didn't match the real
code/environment — both were caught before implementing and fixed
instead of implemented as originally described:

1. **§8.22's `CheckInventoryAction` re-check bug — the request's own
   proposed fix was already the bug, not a fix.** The brief described
   the *current* behavior as checking only `quantityOnHand`, and
   proposed fixing it by checking `available()`
   (`quantityOnHand - quantityReserved`) instead — but
   `CheckInventoryAction::execute()` already checked `available()`, and
   that's exactly what §8.22 documents as wrong: `PlaceOrderAction`'s
   re-check runs *after* `AddToCartAction` already subtracted the same
   quantity into `quantityReserved`, so checking it against
   `available()` again double-subtracts it, failing any Order for >=
   half of on-hand stock. The actual fix: `CheckInventoryAction` gained
   a second pair, `executeCommit()`/`authorizeCommit()`, checking
   `quantityOnHand` directly — the question "can the quantity already
   reserved by this Cart still be fulfilled," not "is there additional
   uncommitted capacity" (`execute()`/`authorize()`'s question, still
   correct for `AddToCartAction`'s own use, left unchanged).
   `PlaceOrderAction` now calls `authorizeCommit()` for its re-check.
2. **A related concurrency bug, not in the original 38-item debt list,
   surfaced while designing that fix.** The sprint's own "concurrent
   reservation" test scenario (two Agents racing for the same stock)
   doesn't exercise §8.22's arithmetic bug at all — it's a
   check-then-act race in `AddToCartAction` (no transaction, no row
   lock between reading `available()` and writing the new reservation).
   Fixed alongside §8.22 since it's the same neighborhood and squarely
   fits "production-ready": `InventoryRepositoryInterface` gained
   `findByProductForUpdate()` (a `lockForUpdate()` read), and
   `AddToCartAction`'s reserve step now runs inside its own
   `DB::transaction()` using it, so two concurrent reservations against
   the same Product serialize instead of both reading the same
   `available()` snapshot and over-reserving past `quantityOnHand`.
3. **The N+1 on the permission-check hot path** (old §8's item 5) —
   `EloquentMemberRoleRepository::findRolesForMember()` was `1 + 2N`
   queries for N roles (a `findById()` call per role id, each 2 queries
   of its own). Fixed with a new batch method,
   `RoleRepositoryInterface::findByIds()` (one `whereIn` + one
   eager-loaded `permissions` query, regardless of N) —
   `findRolesForMember()` now calls it once instead of looping.
4. **Per-agent MCP rate limiting** — no middleware layer exists on
   `mcp/*` routes (`AgentAuthenticationService` resolves the Agent
   inside the controller itself, not via a Guard), so this is an
   explicit Action call (`EnforceRateLimitAction`, `App\Core\Application\Actions`)
   right after the Agent is resolved in `MCPGatewayController`, using
   Laravel's `RateLimiter` facade directly (no `RateLimiter::for()`/
   middleware registration needed — there's no middleware pipeline to
   hook into). Keyed `"mcp-agent:{$agentId}"`, 100/minute default
   (`config/mcp.php`, `MCP_RATE_LIMIT_PER_MINUTE`). A new exception,
   `RateLimitExceededException`, deliberately implements neither Core
   marker interface (same reasoning `WooCommerceApiException` gives for
   its own case) — `MCPExceptionHandler` got one new dedicated match arm,
   `TOO_MANY_REQUESTS`/429.
5. **CI coverage reporting** — `.github/workflows/tests.yml` already
   existed (HANDOFF's old "no CI actually running" note meant
   unverified-on-the-remote, not "file doesn't exist" — the request
   assumed the latter). Edited in place: `coverage: none` → `coverage: pcov`
   (the CI runner installs the real PCOV extension via `setup-php`; no
   composer package makes this possible locally — **`pcov/pcov` is not
   an installable Composer package**, PCOV is a PHP extension, and this
   dev environment has neither PCOV nor Xdebug installed, so the actual
   coverage percentage has never been measured here, only in CI).
   `phpunit.xml` gained a `<coverage>` block. The CI gate
   (`--min=60`) is a conservative placeholder, not a measured baseline —
   raise it once a real CI run reports the actual number (the workflow
   uploads the report as an artifact for exactly this).
6. **A real Laravel scheduler** (old §8.23/§8.27) — this Laravel version
   (^12.0) has no `app/Console/Kernel.php`; `Schedule::command()` calls
   were added directly to `routes/console.php` instead (already wired
   via `bootstrap/app.php`'s `withRouting(commands: ...)`). Two new
   commands, both requiring a new shared primitive neither Core nor any
   module had before — `TenantRepositoryInterface::all()` (cross-tenant
   iteration; nothing before this batch job ever needed to list every
   Tenant):
   - `loyalty:expire-points` (daily, 02:00) — `BulkExpirePointsAction`
     (new) lists every LoyaltyAccount for a tenant
     (`LoyaltyAccountRepositoryInterface::allForTenant()`, new) and calls
     the existing, already-tested `ExpirePointsAction` once per account
     (that Action's own docblock already called this "the natural unit a
     future scheduled job would iterate").
   - `commerce:check-abandoned-carts` (hourly) — `MarkCartsAbandonedAction`
     (new) finds every Cart idle past 24h
     (`CartRepositoryInterface::findStaleActive()`, new, using the
     `updated_at` Eloquent already tracked) and calls `Cart::abandon()`
     — an entity mutator that has existed since Phase 3 with **zero
     callers until this sprint** — then dispatches a new event,
     `CartWasAbandoned` (Commerce's own, carrying only identifiers, same
     shape `InventoryWasCommitted` established).
   - This immediately let Workflows' `CartAbandonedListener` — scaffolding
     since Phase 3.3, blocked purely on "no scheduler exists" — get a
     real `handle(CartWasAbandoned $event)` body (calling
     `TriggerWorkflowAction`, the same translation `InventoryLowListener`
     already does for `inventory_low`) and a real
     `Event::listen()` registration in `WorkflowsServiceProvider::boot()`.
     `HighValueOrderListener` remains deliberately un-wired — it wasn't
     in this sprint's scope (see §9).
7. **`docs/api-reference.md`** (new) — generated by reading all 8
   modules' own `Interfaces/MCP/*Capabilities.php` manifests directly
   (the same data each `*CapabilitiesSeeder` registers from), not
   copied from this file's own §6 table, so its schemas stay accurate to
   the actual registered `inputSchema`/`outputSchema`/`requiredPermissions`
   independent of anything written here.

11 new tests across the 4 items that produce test-observable behavior
(`InventoryConcurrencyTest`, a new `CheckPermissionTest` query-count
regression, `MCPRateLimitTest`, and 3 scheduler/listener end-to-end
tests) — CI/coverage/docs were verified by running the actual workflow
file and command output instead. 470 tests total, zero regressions.

### 7.14 Phase 4, Stage 2 — Shipping Provider Connector

Entities: none new — this stage adds a second, independent path
alongside Stage 1's local `ShippingRateCalculator`, not a new aggregate.
VOs: `ShippingProviderName` (enum `mock|usps|fedex|dhl` — only `Mock` has
an implementation; the rest are modeled-but-unfulfilled, same shape
`RewardType::FreeProduct`/`EventType::CartAbandoned` already have),
Shipping's own `Address` (a deliberate duplicate of Commerce's, identical
reasoning Shipping's own `Money` docblock already gives — this is also a
first, narrow step on §8.37's "no Address concept anywhere in Shipping"
gap, scoped only to what `getRates()` needs). `ShippingRate` (existing
VO) gained two new optional trailing fields, `serviceName`/`serviceCode`
(HANDOFF §3 pattern #6) — `null` for the local-calculator path, populated
for a provider quote. `Shipment` gained two new nullable fields,
`providerName`/`providerTrackingNumber`, and one new mutator,
`assignProviderTracking()` — see that method's own docblock for why the
provider's tracking number is stored as a plain string, never forced
through the existing `TrackingNumber` VO's strict `TRK-XXXXXXXX` format.

**The Connector Pattern (Phase 1, reused for real in Commerce's Stage 6
WooCommerce integration), demonstrated a second time, inside Shipping.**
`ShippingProviderInterface` (`getName()`/`isConnected()`/`getRates()`/
`createShipment()`/`getTrackingUpdates()`) mirrors `ConnectorInterface`/
`ProductConnectorInterface` exactly; `ShippingProviderRegistry`
(Application layer — see correction below) mirrors `ConnectorRegistry`;
`ShippingHttpClientInterface` + `MockShippingHttpClient` mirror
`WooCommerceClientInterface` + `MockWooCommerceHttpClient`; the one real
adapter, `MockShippingProviderAdapter`, mirrors `WooCommerceProductConnector`.
No real carrier client exists this stage (no live USPS/FedEx/DHL
credentials, the same "needs live credentials to test honestly"
reasoning every Connector in this codebase gives) — unlike WooCommerce's
own Stage 6, which built both a real and a mock client, this stage is
Mock-only by explicit request.

**Four places where the request's literal file layout would have
duplicated an abstraction or hit a real domain conflict** — each caught
during planning, not after shipping, the same discipline the Tech Debt
Sprint (§7.13) applied to its own two mismatches:
1. **One adapter class, not two.** The request asked for both an
   `Application/Services/MockShippingProvider` and an
   `Infrastructure/Providers/MockShippingProviderAdapter`. The established
   Connector precedent has exactly one adapter class per provider
   (`WooCommerceProductConnector` is the only class implementing
   `ProductConnectorInterface`) — building a second, wrapping
   Application-layer class would have been pure ceremony. Built **one**:
   `MockShippingProviderAdapter`, depending on `ShippingHttpClientInterface`.
2. **`ShippingProviderRegistry` lives in `Application/Services`, not
   `Domain/Services`** — `ConnectorRegistry`, the exact precedent this
   stage follows, lives there (a plain in-memory lookup, no domain rule
   to protect).
3. **`getTrackingUpdates()` returns a new DTO, `ProviderTrackingEventData`,
   never `TrackingEvent` itself.** `TrackingEvent::record()` requires the
   *local* `shipmentId` — something a provider structurally cannot know
   (it only ever sees a `TrackingNumber`). Identical reasoning
   `WooCommerceProductConnector` already demonstrates by returning
   `UCPProduct` rather than Commerce's own persisted `Product` entity.
   `SyncTrackingAction` (which does know the local Shipment) is the one
   place these become real `TrackingEvent` rows.
4. **The provider's tracking number is a plain string in two new
   columns, never overwriting `Shipment`'s own `TrackingNumber`.**
   Coincidentally, the request's own mock fixture already returns
   `TRK-XXXXXXXX`-shaped values, so `createShipment(): TrackingNumber` is
   honestly satisfiable *for Mock* today — but `usps`/`fedex`/`dhl` are
   real future intents whose tracking numbers will never match that
   regex. `Shipment::assignProviderTracking()`'s own docblock has the
   full reasoning.

**A real bug, not in the request, found while building `SyncTrackingAction`**:
`AddTrackingEventAction` had no way to record a historical `occurredAt`
at all — every call defaulted to `new DateTimeImmutable()` ("now"). Since
`shipping.tracking.add` (an Agent reporting an update as it happens) is
the only caller before this stage, "now" was always correct and the gap
was invisible. `SyncTrackingAction` needed to reuse this same Action
(Actions composing Actions, HANDOFF §3 pattern #3) but supply the
*provider's own* timestamp — without a real `occurredAt` parameter, every
synced event silently got "now," which broke the `(status, occurredAt)`
dedup key entirely (a re-sync would re-add both events every time, since
"now" is never the same value twice). Widened `AddTrackingEventAction`
with an optional trailing `occurredAt` parameter (HANDOFF §3 pattern #6)
— `shipping.tracking.add` is completely unaffected (still omits it,
still defaults to now).

`SyncTrackingAction`'s status-update step (after adding new events, apply
the newest one's status via the existing `UpdateShipmentStatusAction`)
deliberately catches and silently swallows `InvalidArgumentException` —
covers both "already this status" (not in `Shipment::changeStatus()`'s
own `ALLOWED_TRANSITIONS` map for its current state) and a genuinely
illegal transition. A re-sync must be idempotent; a provider replaying or
reordering its own event history is normal, not an error.

**Capability naming hit gotcha #2 again**: the requested
`shipping.provider.shipment.create` was 4 dot-separated segments;
renamed to `shipping.provider.fulfill`, the same restructuring treatment
`shipping.shipment.status.update` got in Stage 1.

New tests: `tests/Unit/Shipping/MockShippingProviderAdapterTest.php` (7 —
name/health-check, 3 rates matching the fixture, a valid `TrackingNumber`,
2 tracking events matching the fixture, simulated-failure throwing
`ShippingProviderException`), `tests/Unit/Shipping/ShippingProviderRegistryTest.php`
(3 — register/get/not-found), `tests/Feature/Shipping/ShippingProviderCapabilityTest.php`
(3 — the full rates → fulfill → sync → idempotent-resync → simulated-failure
→ tenant-isolation scenario, plus unregistered-provider and
missing-permission cases). 483 tests total, zero regressions.

---

## 8. Known technical debt (ranked, carried over + Phase 2 additions)

1. ~~**No per-tenant tax-rate configuration exists.**~~ **Resolved in
   Phase 3.2 (Finance module, §7.8).** `CalculatePricingAction`/
   `ProcessPaymentAction` still carry the `DEFAULT_TAX_RATE_PERCENT = 9.0`
   constant, but only as the last-resort fallback when neither a
   region-specific nor a tenant-default `TaxRate` is configured — real
   per-tenant configuration now exists via Finance's `TaxRate` entity and
   `TaxRateProviderInterface`. The constant still can't be a shared
   `TaxRate` object const until PHP 8.3 (HANDOFF gotcha #6) — that specific
   sub-issue is unchanged, just no longer the whole story.
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
4. **No CI actually running yet** (unverified-on-the-remote, not
   "doesn't exist" — `.github/workflows/tests.yml` has existed since
   Phase 1). The Tech Debt Sprint (§7.13) added coverage reporting to it
   (`coverage: pcov`, `--min=60` placeholder), but whether GitHub
   Actions is actually enabled for this repo's remote is still
   unverified — check before relying on it.
5. ~~**N+1 query on the permission-check hot path.**~~ **Resolved in the
   Tech Debt Sprint (§7.13).** `RoleRepositoryInterface::findByIds()` (new)
   batches what used to be a `findById()` call per role id.
6. ~~**No global rate limiting on `routes/mcp.php`.**~~ **Resolved in the
   Tech Debt Sprint (§7.13)**, per-agent rather than global —
   `EnforceRateLimitAction`, 100/min per Agent by default.
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
11. **Coverage percentage is still unmeasured locally** — the Tech Debt
    Sprint (§7.13) added the `<coverage>` block to `phpunit.xml` and wired
    `coverage: pcov` into CI, but this dev environment has neither PCOV
    nor Xdebug installed (PCOV is a PHP extension, not a Composer
    package — `composer require pcov/pcov` isn't a real thing), so the
    actual percentage has only ever been measurable in CI, never here.
    Check the CI run's uploaded `coverage-report` artifact for the real
    number, then raise `--min=60` (currently a placeholder) to match it.
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
18. **Invoice has no PDF/HTML export and no email-delivery concept.** It's
    a billing *record* (status, amounts, line items) with an MCP surface —
    nothing renders or sends it anywhere yet.
19. **No `MarkInvoicePaidAction`/`CancelInvoiceAction` exist.** `InvoiceStatus`
    models Paid/Cancelled as real states (§7.8), but `Invoice::issue()` is
    the only transition method that exists — Draft->Issued is as far as
    any Order in this codebase can currently travel. Payment reconciliation
    (an Invoice becoming Paid because a Commerce `Payment` succeeded) has
    no wiring at all yet — a third candidate for the same kind of Interface
    Commerce's `TaxRateProviderInterface` demonstrates, if that direction
    is ever wanted.
20. **`finance.tax.calculate`'s strict behavior and `CommerceTaxRateProvider`'s
    graceful one are easy to conflate** — see §7.8's "two different,
    deliberately non-unified fallback chains" note before changing either;
    they read similarly but intentionally never share code.
21. **Coverage is unmeasured** for Finance same as everywhere else (§8.11).
22. ~~**`CheckInventoryAction`'s re-check math breaks for orders larger
    than half of on-hand stock.**~~ **Resolved in the Tech Debt Sprint
    (§7.13).** `CheckInventoryAction` gained `executeCommit()`/
    `authorizeCommit()`, checking `quantityOnHand` directly instead of
    `available()` — the correct question for a re-check on a quantity
    already reserved by the same Cart. `PlaceOrderAction` now calls
    `authorizeCommit()`. A related, previously-undocumented reservation
    race condition (concurrent `AddToCartAction` calls over-reserving
    past `quantityOnHand`) was found and fixed alongside it, via
    `InventoryRepositoryInterface::findByProductForUpdate()` (row
    locking) + wrapping the reservation in `DB::transaction()`.
23. ~~**No scheduled/cron mechanism exists anywhere in this codebase**~~ —
    **Resolved in the Tech Debt Sprint (§7.13).** `routes/console.php`
    now has two real `Schedule::command()` entries
    (`loyalty:expire-points` daily, `commerce:check-abandoned-carts`
    hourly) — the actual blocker for `CartAbandonedListener` (§7.9),
    which is now wired for real.
24. **`WorkflowAction`'s `notify_agent` type doesn't deliver anywhere.**
    No Notification/Inbox system exists in Core — "notifying" currently
    means rendering the message template and recording it in the
    `WorkflowLog` (`ExecuteWorkflowActionAction`'s own docblock). A real
    delivery channel (email, Slack, an MCP push mechanism agents poll)
    would extend that Action's match arm, not replace the templating.
25. **A Workflow's rules/actions are immutable after creation, with no
    "add a rule" / "add an action" operation at all** — only
    name/description/status are editable (`UpdateWorkflowAction`). A
    workflow builder UI would need a more deliberate redefinition
    operation than currently exists, the same gap Ticket/Tag's own
    "structure is frozen, generic fields aren't" shape has elsewhere.
26. **`ExpirePointsAction` is a simplified FIFO, not a true per-lot
    ledger** (§7.10) — it doesn't track which specific Redemption
    consumed which specific earn-batch, so expiration always processes
    the oldest still-flagged batch first regardless of which batch a
    Customer's redemptions actually drew down. Tenant-favoring, not
    wrong, but a precise implementation is real future work.
27. ~~**No scheduling mechanism exists anywhere in this codebase**~~ (same
    item as §8.23) — **Resolved in the Tech Debt Sprint (§7.13)**, and it
    did unlock both at once as predicted: `ExpirePointsAction` now runs
    daily via `BulkExpirePointsAction`/`loyalty:expire-points`, and
    `CartAbandonedListener` is wired for real.
28. **No `crm.tag.*`-style admin surface for Rewards** — no
    `UpdateRewardAction`/deactivate operation exists; a Reward, once
    created, can only be read or listed, never edited or retired.
29. **Redemption has no `pending`/`cancelled` path** — `status` models
    those states (rule §d schema) but every Redemption this stage
    creates goes straight to `completed`; nothing produces the other two
    values yet, the same "modeled but not all reachable" gap
    `RewardType::FreeProduct`/`FreeShipping` and Workflows'
    `EventType::CartAbandoned`/`OrderHighValue` already have.
30. **Reporting's Query Builders are coupled to Commerce's/Loyalty's
    current column names** (§7.11) — a deliberate, documented,
    accepted exception to the usual Repository Interface boundary, not
    an oversight, but it does mean a future rename of e.g.
    `orders.total_amount` or `point_transactions.transaction_type` needs
    to touch Reporting too, something no other module's own schema
    changes have ever required of a *different* module before.
31. **No caching is actually implemented for ReportResult**, even though
    `expires_at` exists on the table and rule §d.4 calls out caching as
    a possibility — every `Generate*ReportAction` call always
    recomputes from scratch and writes a brand new `ReportResult` row;
    nothing reads a still-fresh prior result instead. `expires_at` is
    schema-ready, not yet wired to any actual short-circuit logic.
32. **No `report.definition.get`/`.list` MCP surface** — `GetReportAction`/
    `ListReportsAction` exist and are tested but aren't reachable by an
    Agent yet (§7.11), so a saved Report's history can only be inspected
    by re-running the same report, not by listing/retrieving past runs.
33. **Reporting has no Shipping/Inventory-turnover/Customer-lifetime-value
    report yet** — only the 5 requested types exist; `Product`'s
    Category (Stage 1) and `Inventory`'s current stock levels (Stage 2)
    are both untouched by any report so far, a natural next report type
    if inventory/catalog analytics are ever wanted.
34. **Weight lives in `Product.attributes['weight_grams']`, an untyped,
    unvalidated free-form bag entry** (§7.12) — nothing stops a Product
    from having `weight_grams` set to a string, a negative number, or
    left out entirely (silently treated as 0). `CreateShipmentAction`
    casts defensively but there is no `InvalidWeightException` raised
    for a bad Product attribute today, only for a negative `Weight` VO
    constructed directly. A first-class Product field (or at least a
    validated attribute schema) is the real fix if this becomes load-bearing.
35. **No per-Order-item shipment splitting** — one Shipment always
    covers 100% of one Order; there is no concept of partial
    fulfillment (some items ship now, the rest later in a second
    Shipment). `shipments.order_id` is a plain int specifically because
    of this 1:1-per-fulfillment assumption — a real multi-shipment Order
    would need to revisit that.
36. **No shipping-cost inclusion in Commerce's own checkout pricing.**
    `PricingService`'s `Total = Subtotal + Tax − Discount` formula still
    has no shipping term — `Order.shipping_cost` is populated only
    *after* the Order is placed and shipped, never as part of
    `commerce.checkout.calculate`/`.process`'s own upfront total. A
    Customer paying at checkout time still doesn't see shipping cost
    baked into what they're charged.
37. **No Shipping Zones/per-region rates** — every `ShippingMethod`
    charges the same `base_rate`/`rate_per_kg` regardless of
    destination; there is no address/zone concept anywhere in Shipping
    (Commerce's own `Address` VO, added in Stage 4, still only lives on
    Customer, not on an Order or Shipment — HANDOFF's own "Shipping"
    bullet in §9 flagged this before this stage existed).
38. **`shipping.method.create`'s `currency` input isn't in its own
    inputSchema** — it's read defensively (`$input['currency'] ?? 'USD'`)
    the same "optional field simply omitted from inputSchema" convention
    HANDOFF §3 pattern #7 established, but it means a caller can't
    discover it exists without reading this file or the source.

---

## 9. What's next

Phase 2 (Commerce, all 6 Stages) and Phase 3 (CRM, Finance, Workflows,
Loyalty, Reporting — all 5 Stages) are fully complete. Phase 4
(Shipping & Logistics) has two Stages done — Shipping Foundation and the
Shipping Provider Connector (§7.14). The Tech Debt Sprint (§7.13) ran
between the two, closing the scheduler gap and the `CheckInventoryAction`
re-check bug that used to top this list. Candidates worth raising with
whoever's driving scope next, roughly in order of how much they'd reuse
what already exists:

- **A real carrier implementation of `ShippingProviderInterface`** (USPS/
  FedEx/DHL) — `MockShippingProviderAdapter` (§7.14) is now the template,
  the same role `WooCommerceProductConnector` played for Commerce's own
  second real Connector; `ShippingProviderRegistry` already supports
  registering more than one by name, and `ShippingProviderName` already
  has the enum cases waiting.
- **Measure real test coverage from a CI run** — the Tech Debt Sprint
  (§7.13) wired `coverage: pcov` into `.github/workflows/tests.yml` but
  could only set a conservative placeholder gate (`--min=60`), since no
  coverage driver exists in this dev environment to measure the real
  number locally. Cheapest possible next increment: push, read the
  uploaded `coverage-report` artifact, raise the gate to match reality.
- **Wire `HighValueOrderListener`** (§7.9) — still genuinely the cheapest
  possible next increment in the entire codebase: the event it needs
  (`OrderWasPlaced`) already exists, the Listener class already exists,
  only `Event::listen()` in `WorkflowsServiceProvider::boot()` and a
  Workflow row are missing. (`CartAbandonedListener`, the other half of
  this same "scaffolded Listener" pattern, got wired for real in §7.13 —
  this is the one that's left.)
- **Phase 4's next Shipping stage** — Shipping Zones/per-region rates
  (§8.37 — Shipping's own new `Address` VO from §7.14 is a first step, not
  the full feature), partial/multi-shipment fulfillment (§8.35), folding
  `shipping_cost` into Commerce's own checkout total (§8.36), or a
  first-class Product weight field replacing the `attributes` bag
  (§8.34). Shipping is the reference for a module writing data back onto
  an earlier module's own entity (§7.12) — the same
  `Order::assignShipping()` shape would extend to any future "write a
  result back onto Commerce" need.
- **Phase 3 polish** — Finance (payment reconciliation to auto-mark
  Invoices Paid, PDF/email export — §8.18/§8.19), a second CRM stage
  (Ticket assignment, Tag removal, a `crm.tag.*` MCP surface —
  §8.15/§8.16), real notification delivery for `notify_agent` (§8.24), or
  actual caching for `ReportResult` (§8.31 — `expires_at` already exists
  on the schema, nothing checks it yet).
- **A second real product Connector** (Shopify) — `ProductConnectorInterface`
  and the WooCommerce implementation (§7.6) are now a template to follow;
  `ConnectorRegistry` already supports registering more than one by name.
- **Wire the 16 un-wired capabilities from §6** (7 from Commerce Stages
  1–5, 4 from CRM, 1 from Finance, 1 from Workflows, 1 from Loyalty, 2
  from Reporting — Shipping wired all 8 of its own) if any Agent workflow
  actually needs cart-removal, order-cancellation, payment lookup,
  ticket-updating, tag management, tax-rate updates, workflow-updating,
  points-expiration, or saved-report retrieval through MCP — cheapest
  possible next increment each.
- **Per-tenant connector credentials** (§8.14) — the most obviously
  "fake"/single-tenant piece remaining (per-tenant tax, §8.1's original
  concern, is resolved as of Phase 3.2).
- **Order/Customer/Inventory sync out to WooCommerce** (§8.13) —
  `OrderConnectorInterface` still has no implementation.
- **A dedicated `capabilities:sync` artisan command**, graduating away from
  the seeder pattern — flagged as an open decision since Phase 1, still
  open, now with 54 capabilities across eight seeders instead of 3.

Whatever comes next, follow §3's patterns and check §8 before assuming a
piece of the puzzle doesn't already exist.
