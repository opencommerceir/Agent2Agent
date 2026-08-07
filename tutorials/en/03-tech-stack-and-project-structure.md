← [Architecture and Philosophy](02-architecture-and-philosophy.md) | Next: [The Core](04-the-core.md) →

# 3. Tech Stack and Project Structure

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Database | MySQL (dev/test: in-memory SQLite) |
| Cache & queue | Redis (dev default: `database`), Queue Workers |
| Admin Dashboard frontend | Tailwind CSS v4 + Alpine.js + Chart.js, bundled with Vite |
| PDF | `barryvdh/laravel-dompdf` |
| External HTTP client | Guzzle |
| Architectural style | Modular Monolith, Domain-Driven Design, Clean Architecture, SOLID, Event-Driven, API-First |

A small but important note on PHP: the version is 8.2.12, meaning some PHP 8.3 features (like constructing an object inside a class constant) aren't available — this constraint explains a handful of small technical decisions in the code (see file 17).

## The overall folder map

```
app/
├── Core/                         ← identity, tenancy, permissions, MCP Gateway
├── Modules/
│   ├── Commerce/                  ← product, cart, order, customer, payment, coupon
│   ├── CRM/                       ← support tickets, customer notes, tags
│   ├── Finance/                   ← tax rates, invoices
│   ├── Workflows/                 ← event-driven automation
│   ├── Loyalty/                   ← points, rewards, redemptions
│   ├── Reporting/                 ← read-only reporting
│   ├── Shipping/                  ← shipping methods, shipments, tracking
│   ├── Notifications/             ← email, webhook, SMS, in-app
│   ├── Analytics/                 ← KPIs, analytics snapshots
│   ├── AgentOrchestrator/         ← goal→plan→execute→reflect engine for AI agents
│   └── Demo/                      ← the original sample module (unchanged since Phase 1)
├── Http/                          ← Admin Dashboard and Showcase demo controllers
└── Console/Commands/               ← scheduled Artisan commands

packages/
└── opencommerce-sdk/              ← the official PHP SDK for talking to MCP

config/
├── agents/{ceo,sales,support,finance}.php   ← each AI agent persona's configuration
├── agent-orchestrator.php                    ← Planner/Reasoning/LLM configuration
├── api.php, commerce.php, shipping.php, showcase.php, mcp.php   ← per-module config

database/
├── migrations/                     ← ordered by phase and stage
└── seeders/                        ← one capability seeder per module + the demo showcase seeder

resources/views/
├── dashboard/                       ← Admin Dashboard Blade views
└── showcase/                        ← Showcase demo Blade views

routes/
├── mcp.php     ← /mcp/v1 and /mcp/v2 routes
├── agents.php  ← /api/agents/* routes
├── web.php     ← login, Admin Dashboard, Showcase demo
└── console.php ← scheduled commands (Schedule::command)

tests/
├── Unit/       ← pure, framework-free tests (where possible)
└── Feature/    ← real HTTP/database-level tests

docs/            ← architecture, decisions, conventions documentation
HANDOFF.md       ← the complete build log (the final technical reference)
tutorials/       ← this very tutorial you're reading 🙂
```

## The internal shape of a domain module

As described in the previous file, every module looks like this (a real example from Commerce):

```
app/Modules/Commerce/
├── Domain/
│   ├── Entities/        Product, Category, Cart, CartItem, Inventory, Order, ...
│   ├── ValueObjects/     Money, SKU, ProductStatus, Quantity, ...
│   ├── Services/         PricingService, CouponValidationService, ...
│   ├── Events/           ProductWasCreated, OrderWasPlaced, ...
│   ├── Repositories/      ProductRepositoryInterface, OrderRepositoryInterface, ...
│   └── Exceptions/        ProductNotFoundException, ...
├── Application/
│   ├── Actions/           CreateProductAction, PlaceOrderAction, ...
│   ├── DTOs/               ProductData, OrderData, ...
│   ├── Jobs/                ProcessBulkImportJob, ...
│   └── Services/            ConnectorRegistry, PaymentGatewayInterface, ...
├── Infrastructure/
│   ├── Models/               Eloquent models
│   ├── Repositories/          Repository implementations
│   └── Connectors/             external connectors, e.g. WooCommerce
├── Interfaces/MCP/           this module's MCP capability definitions
└── CommerceServiceProvider.php  ← wires everything into the Laravel container
```

The seven-step pattern for adding a new capability (repeated across every module):

```
Entity → Repository Interface → Eloquent Model + Repository →
DTO → Action → Domain Event → register in ServiceProvider
```

## The numbers, at a glance (current state)

- **1,156 passing tests** (zero known regressions)
- **127 MCP capabilities**
- **10 domain modules** plus the Core
- **5 official SDKs** (PHP, Laravel, Python, Node.js/TypeScript, Go)
- **6 completed phases** (Phase 1 through Phase 6) plus several side stages (Tech Debt Sprint, OpenRouter, Showcase Demo, the multi-language SDK expansion, live verification against a real OpenRouter model, and real Zibal + Stripe payment gateways)

## How to move through the codebase (a reading roadmap for this tutorial)

Files 4 through 22 of this tutorial follow the exact order the project was actually built — the phase order:

| Phase | What was built | Related file in this tutorial |
|---|---|---|
| Phase 1 | The Core + MCP Gateway | Files 4, 5 |
| Phase 2 | The Commerce module (6 stages) | File 6 |
| Phase 3 | CRM, Finance, Workflows, Loyalty, Reporting | File 7 |
| Phase 4 | Shipping, Notifications, i18n, Admin Dashboard, Analytics, API Versioning, Performance (8 stages) | Files 8, 9, 10 |
| Phase 5 | Product Variants, multi-warehouse, bulk operations, advanced discounts, subscriptions (5 stages) | File 11 |
| Phase 6 | AI Agent Orchestration (6 stages) | Files 12, 13, 14, 15 |
| — | OpenRouter + the live Showcase demo | Files 15, 16 |
| — | Architecture patterns, install/run/test, technical debt & roadmap | Files 17, 18, 19 |
| — | Integration paths for others + the five official SDKs (incl. real Zibal/Stripe payment gateways) | Files 20, 21 |
| — | Profitable, revenue-generating use cases | File 22 |

This order matters, because every phase builds on the mechanisms of the phase before it — understanding Commerce before the Agent Orchestrator is essential, since AI agents ultimately call the exact same Commerce capabilities through MCP.

---
← [Architecture and Philosophy](02-architecture-and-philosophy.md) | Next: [The Core](04-the-core.md) →
