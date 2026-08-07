# Roadmap

> For the detailed, stage-by-stage build log (what was built, in what
> order, and why — including every architectural fork and correction
> along the way), see `HANDOFF.md`. This file is the birds-eye view.

## Phase 1 — Platform Foundation ✅ Complete

- Core (Identity, Authentication, Organizations, Tenancy, Permissions)
- Agent Registry
- Capability Registry
- MCP Gateway (`/mcp/v1/execute`, `/mcp/v1/capabilities`)
- UCP (Universal Commerce Protocol — normalized commerce value objects)
- SDK (`packages/opencommerce-sdk` — the first of what are now 5 official
  SDKs; see **Post-Phase-6 Additions** below for the other 4)
- Connector Pattern (Mock Product Connector as the reference implementation)

## Phase 2 — Commerce (6 Stages) ✅ Complete

- Stage 1: Product & Category Management
- Stage 2: Cart & Inventory Management
- Stage 3: Order Management
- Stage 4: Customer Management
- Stage 5: Checkout & Payment System (Coupons, Discounts)
- Stage 6: Real Connectors (WooCommerce)

## Phase 3 — Domain Expansion (5 Stages) ✅ Complete

- Stage 1: CRM (Support Tickets, Customer Notes, Tags)
- Stage 2: Finance (per-tenant tax rates, Invoices)
- Stage 3: Workflows (event-driven automation)
- Stage 4: Loyalty (Points, Rewards, Redemptions)
- Stage 5: Reporting (read-only analytics)

## Phase 4 — Shipping & Logistics (8 Stages) ✅ Complete

- Stage 1: Shipping Foundation (ShippingMethods, Shipments, TrackingEvents)
- Stage 2: Shipping Provider Connector
- Stage 3: Notifications Module
- Stage 4: Multi-language Support / i18n Infrastructure
- Stage 5: Admin Dashboard + Human Authentication
- Stage 6: Advanced Analytics & KPIs
- Stage 7: API Versioning System (`/mcp/v1`, `/mcp/v2`)
- Stage 8: Performance Optimization (caching, N+1 fixes, DB indexing)

A Tech Debt Sprint ran between Phase 4 Stage 1 and Stage 2, resolving 7
carried-over items (inventory concurrency, permission N+1, MCP rate
limiting, the platform's first real scheduler, CI coverage reporting).

## Phase 5 — Advanced Commerce (5 Stages) ✅ Complete

- Stage 1: Product Variants (attributes, combinations)
- Stage 2: Multi-warehouse Inventory (transfers, nearest-warehouse lookup)
- Stage 3: Bulk Operations (CSV import/export, background Jobs)
- Stage 4: Advanced Discount Rules (priority + stackability engine)
- Stage 5: Subscription & Recurring Orders (billing cycles, trials,
  pause/resume/cancel/upgrade, automated retry + past-due handling)

## Phase 6 — AI Agent Orchestration (6 Stages) ✅ Complete

- Stage 1: Agent Orchestrator (Goal -> Plan -> Execute over *existing* MCP
  capabilities, no business logic of its own; `DeterministicPlanner` MVP)
- Stage 2: Agent Profiles + CEO Agent (config-driven personas,
  `config/agents/{type}.php` — adding a new Agent is one new file)
- Stage 3: LLM-based Planner (real OpenAI/Claude planning, `PLANNER_TYPE=llm`,
  automatic fallback to the deterministic planner on any failure)
- Stage 4: Execution Memory & Learning (pattern extraction from real
  execution history; a repeat goal skips planning and reuses what worked)
- Stage 5: Multi-Agent Collaboration (persona-to-persona delegation under
  the caller's own real permissions — personas are planning
  classifications, not separate identities, a real correction from the
  original request confirmed before writing any code)
- Stage 6: Self-Reflection & Reasoning (pre-execution `think()` and
  post-execution `reflect()`, LLM-backed with the identical automatic
  deterministic fallback shape Stage 3 established; explanatory only —
  nothing it produces is read back into planning or execution)

See `docs/agent-orchestrator.md` and its sibling how-to guides
(`docs/agent-profiles.md`, `docs/llm-planner.md`, `docs/execution-memory.md`,
`docs/multi-agent-collaboration.md`, `docs/self-reflection.md`) for the
full architecture, and `HANDOFF.md` §7.26-§7.31 for the complete build
narrative, including every deliberate correction made from each stage's
own original request.

**Current state**: 1156 automated tests passing, 127 MCP capabilities
across 10 Domain Modules (Commerce, CRM, Finance, Workflows, Loyalty,
Reporting, Shipping, Notifications, Analytics) plus the Core platform,
the now-complete Agent Orchestrator, a session-authenticated Admin
Dashboard, real Zibal/Stripe payment gateways, and 5 official SDKs. See
`README.md`'s Project Status section and `HANDOFF.md` for the
authoritative, up-to-date snapshot.

## Post-Phase-6 Additions (real, shipped work — not numbered Stages)

Phase 6 finished at Stage 6 (§7.31), but real work kept shipping on top of
it rather than waiting for a numbered Phase 7 to be scoped — the same
"a real, useful piece of work that doesn't fit the numbered Stage
sequence" shape the Tech Debt Sprint already used between Phase 4 Stage 1
and Stage 2. In build order:

- **OpenRouter Integration** (`HANDOFF.md` §7.32) — a 3rd `LLMClientInterface`
  provider alongside OpenAI/Claude, giving `LLMPlanner`/`LLMReasoningEngine`
  access to 100+ models through one API, several genuinely free.
- **Showcase Demo** (§7.33) — a `/showcase` web chat UI (see **🎬 Showcase
  Demo** in `README.md`) with a live data panel, a real-AI toggle, a
  conversation history sidebar, and an optional passcode gate.
- **Multi-Language SDK Expansion** (§7.34) — Python, Node.js/TypeScript,
  and Go SDKs (`packages/opencommerce-sdk-{python,js,go}`), each
  dependency-free and mirroring the PHP SDK's own public contract field
  for field.
- **Live OpenRouter Verification** (§7.35) — the LLM integration exercised
  against a real, live provider for the first time (not only mocked
  HTTP), finding and fixing a real `base_uri`-resolution bug in the
  process.
- **SDK Registry Publish-Readiness** (§7.36) — closed real PyPI naming
  collisions, set the Go SDK's real module path, and fixed a real npm
  scoped-package publish blocker.
- **Real Payment Gateways — Zibal + Stripe** (§7.37) — real,
  redirect-based checkout via `RedirectPaymentGatewayInterface`/
  `PaymentGatewayRegistry` (see `docs/payment-gateways.md`), extensible to
  any future gateway in 3 steps.
- **Laravel SDK** — `packages/opencommerce-sdk-laravel` (`opencommerce/sdk-laravel`),
  a thin ServiceProvider + `OpenCommerce` facade around the PHP SDK,
  completing the SDK Platform's original 5-language line-up.

## Phase 7 — Not Yet Scoped

No Phase 7 has been decided. Whoever drives scope next is choosing where
the platform goes from here, not just picking the next item off a list —
Phase 6 was only 6 Stages in, and the post-Phase-6 additions above, while
real and shipped, weren't a scoped Phase either. Candidates carried over
from Phase 6's own retrospective (`HANDOFF.md` §9) include: feeding
`ReasoningTrace` alternatives back into planning (today purely
explanatory), a real, genuinely async delegation flow (a queued Job
another process later picks up), semantic/embedding-based
`ExecutionPattern` matching in place of today's plain keyword substring
check, cycle detection for delegation beyond "can't delegate to
yourself," additional Agent personas beyond CEO, a `/dashboard/agents`
page covering every Phase 6 surface (Goals, Executions, Profiles, Memory,
Collaboration, and Reasoning — none has one yet), a real carrier
(USPS/FedEx/DHL) implementation of `ShippingProviderInterface`, a real
SMS gateway behind `SmsSender`, a `refund()` addition to
`RedirectPaymentGatewayInterface` (Stripe/Zibal), a customer-facing
checkout page to actually land on `commerce.payment.initiate`'s own
`redirect_url` (no storefront exists yet — this platform is MCP/API +
Admin Dashboard only), plus the still-open Phase 5 items (a Dashboard UI
for every Phase 5 resource, folding Cart-level automatic discounts into
the real checkout total, a real file-upload endpoint for Bulk Operations
imports, a "subscription expiring soon" notification) and the
longer-standing infrastructure items below.

## Cross-cutting / Infrastructure Track (ongoing, not phase-bound)

- [ ] Upgrade to Laravel 13 (blocked on the runtime environment moving to
      PHP 8.3+ first — see `docs/decisions.md`, Decision 003)
- [ ] Measure real test coverage from a CI run (PCOV is wired into CI but
      unmeasured in this dev environment — `docs/decisions.md`/`HANDOFF.md`
      §8 have the detail)
- [ ] Stand up a real Redis server for `CACHE_STORE=redis` (currently
      `database` in this working copy; `predis/predis` is already a real
      dependency)
- [ ] A dedicated `capabilities:sync` artisan command, graduating away
      from the per-module capability seeder pattern
- [ ] A real v3 API version, or retiring v1 once its sunset date passes
      (the versioning infrastructure already supports either)
