# OpenCommerce Platform

> **Open-source infrastructure for building Agent-Ready business systems.**

OpenCommerce Platform is an open-source infrastructure that enables businesses to become **AI Agent Ready**.

As AI Agents become a new interface between people and digital services, businesses need a standardized way for agents to discover, understand, and securely interact with their products, services, and capabilities.

Today's software—including commerce platforms, marketplaces, ERP systems, CRM solutions, and custom business applications—was designed primarily for human users. OpenCommerce bridges the gap between these existing systems and the emerging world of AI Agents through open protocols, standardized interfaces, and developer-friendly tooling.

Our mission is to build the open infrastructure that powers the next generation of intelligent business software.

---

## Vision

The internet connected people to information.

Cloud platforms connected businesses to services.

The next evolution is connecting **AI Agents** to business capabilities.

Just as every business became **mobile-ready** and **search-engine friendly**, the next generation of digital businesses must become **Agent Ready**.

OpenCommerce Platform aims to become the open infrastructure that enables AI Agents to securely discover, understand, and execute business capabilities across modern business ecosystems.

---

## Why OpenCommerce?

Modern business software is fragmented.

Every platform exposes different APIs, authentication methods, data structures, and business rules. While APIs already exist, they were never designed for autonomous AI Agents capable of reasoning and performing complex workflows.

AI Agents need to:

- Discover available capabilities
- Understand business semantics
- Execute actions securely
- Navigate permissions
- Work consistently across different platforms

Today, every AI integration is typically built from scratch.

This creates duplicated work, inconsistent implementations, and poor scalability.

OpenCommerce solves this by introducing a unified infrastructure layer between AI Agents and business systems.

---

## Our Solution

OpenCommerce provides a common language between AI Agents and business software.

Instead of creating custom integrations for every platform, businesses expose their capabilities once through OpenCommerce.

AI Agents can then securely discover, understand, and execute those capabilities using standardized protocols.

The result is a scalable ecosystem where businesses become **Agent Ready** without replacing their existing systems.

---

## Core Architecture

OpenCommerce Platform is built around a modular architecture where every layer has a single responsibility.

### OpenCommerce Core

The foundation of the platform.

Responsible for:

- Identity & Authentication
- Organizations
- Multi-tenancy
- Permissions
- API Keys
- Configuration
- Connections
- Event Bus
- Audit Logs

---

### Agent Registry

Maintains information about registered AI Agents, their identities, permissions, supported protocols, and available connections.

---

### Capability Registry

The Capability Registry acts as the discovery layer of the platform.

Every connected business system exposes its capabilities in a standardized format.

Examples include:

- Search Products
- Check Inventory
- Create Orders
- Retrieve Customer Information
- Generate Reports
- Create Invoices

AI Agents discover these capabilities dynamically instead of relying on hardcoded integrations.

---

### MCP Gateway

The **Model Context Protocol (MCP)** Gateway provides the communication layer between AI Agents and OpenCommerce.

Responsibilities include:

- Authentication
- Authorization
- Capability Discovery
- Tool Execution
- Structured Responses

Business logic is never implemented inside the MCP Gateway.

---

### Universal Commerce Protocol (UCP)

The Universal Commerce Protocol (UCP) provides a normalized commerce model.

Different commerce systems—including Shopify, WooCommerce, Magento, Laravel applications, and custom platforms—are transformed into a common structure that AI Agents can understand consistently.

---

### SDK Platform

OpenCommerce provides official SDKs that enable developers to make their applications Agent Ready with minimal effort — whether connecting to a self-hosted deployment or to OpenCommerce's own hosted infrastructure at OpenCommerce.ir. Every SDK talks the same underlying MCP Gateway protocol, so no SDK at all is strictly required — any language that can send HTTP + JSON can integrate directly.

**Available today:**

- **PHP SDK** — `packages/opencommerce-sdk` (framework-agnostic, Guzzle-backed)
- **Python SDK** — `packages/opencommerce-sdk-python` (dependency-free, standard library only)
- **Node.js / TypeScript SDK** — `packages/opencommerce-sdk-js` (`@opencommerce/sdk`, dependency-free, built on the standard `fetch` API)
- **Go SDK** — `packages/opencommerce-sdk-go` (dependency-free, standard library only)

All four mirror the same `MCPClient`/`discover`/`execute`/`getCapability` surface, support both the `v1` and `v2` wire envelopes transparently, and map every HTTP-level failure to a typed error/exception. See each package's own `README.md` for a 5-minute quick start, and `examples/sample-agent.{php,py,ts,go}` for a complete, runnable script in every language.

**Still planned:**

- Laravel SDK (a thin, Laravel-specific wrapper — Facade + ServiceProvider — around the framework-agnostic PHP SDK above)

---

### Connectors

Connectors integrate existing business systems without requiring major architectural changes.

Examples include:

- Shopify
- WooCommerce
- Magento
- Laravel Commerce
- ERP Systems
- CRM Systems
- POS Systems
- Custom APIs

---

## First Domain: Commerce

Commerce was the first domain implemented on top of OpenCommerce Platform, and remains its largest — Products (incl. variants), Categories, Carts, Multi-warehouse Inventory, Orders, Customers, Checkout/Payments, Coupons, Discount Rules, Bulk Operations, and Subscriptions & Recurring Orders. It lets AI Agents:

- Discover and search products (incl. variants)
- Manage carts and place orders
- Check and transfer inventory across warehouses
- Access customer information
- Run complete checkout workflows (tax, discounts, coupons)
- Accept real payments via Zibal (Iranian IPG) or Stripe, alongside the existing Mock gateway — see `docs/payment-gateways.md`
- Create and manage recurring Subscriptions (trials, pause/resume/cancel/upgrade, automated billing)
- Bulk import/export catalog and customer data

This established the foundation for **Agentic Commerce** — and proved out the pattern every domain below now follows.

---

## Beyond Commerce

OpenCommerce was designed as a general-purpose Agent infrastructure from the start, and it no longer only proves that in theory — nine more Domain Modules are built and live on the same Core, none of which required a single change to Core itself:

- **CRM** — Support Tickets, Customer Notes, Tags
- **Finance** — per-tenant tax rates, Invoices
- **Workflows** — event-driven automation ("when X happens and Y is true, do Z")
- **Loyalty** — Points, Rewards, Redemptions
- **Reporting** — read-only sales/revenue/customer/loyalty analytics
- **Shipping** — Shipping Methods, Shipments, Tracking, real carrier connector pattern
- **Notifications** — Email/SMS/Webhook/In-App, cross-module event-driven delivery
- **Analytics** — KPIs, dashboards, CSV/PDF export
- Plus a session-authenticated **Admin Dashboard** (bilingual, EN/FA with RTL support) for human operators, and a versioned MCP surface (`/mcp/v1`, `/mcp/v2`)

Future domains under consideration include:

- ERP
- Human Resources
- Healthcare
- Manufacturing
- Marketing Automation

The Core Platform remains domain-independent, allowing new modules to be added without changing the underlying architecture — every module above is proof of that, not just a design goal. See `docs/roadmap.md` for the phase-by-phase build order and `HANDOFF.md` for the full engineering log.

---

## Agent Orchestrator

Phase 6's first module, and the platform's first step from "Agents can call
one capability at a time" toward "Agents can pursue a whole business Goal."
The Agent Orchestrator is an **orchestration layer with no business logic
of its own** — it turns a plain-text Goal ("Increase sales by 15% this
week") into an ordered sequence of *existing* OpenCommerce MCP
capabilities, executes them on the calling Agent's behalf through the same
`CapabilityExecutionService`/`CapabilityHandlerRegistry` machinery
`/mcp/v1/execute` itself uses, and never aborts a plan just because one
step failed.

- `POST /api/agents/{ceo|sales|support|finance}` with `{"goal": "..."}` —
  plus the identical `agent.goal.execute`/`agent.execution.get`/
  `agent.execution.list` MCP capabilities for a caller that is itself an
  Agent.
- **Agent Profiles (Phase 6, Stage 2)** — every persona's own planning
  rules now live entirely in config (`config/agents/{type}.php`), not PHP:
  adding a new Agent is exactly one new config file, no code change.
  `GET /api/agents/profiles`/`GET /api/agents/profiles/{agent_type}`
  (+ the identical `agent.profile.list`/`agent.profile.get` MCP
  capabilities) inspect a persona's own rules, default inputs, and
  expected permissions.
- **The CEO Agent** is the first fully-realized persona
  (`config/agents/ceo.php`) — sales/revenue/inventory goals, a real
  discount-percentage parsed straight from the goal text, a real
  generated coupon code. Sales/Support/Finance profiles ship alongside it.
- **A real LLM-based planner (Phase 6, Stage 3)** — set `PLANNER_TYPE=llm`
  (+ `LLM_PROVIDER=openai|claude|openrouter` and the matching API key) and
  `LLMPlanner` asks a real GPT-4, Claude, or (via OpenRouter — Showcase
  prep, §7.32 — a single API in front of 100+ models, several genuinely
  free) model to plan each Goal against every capability the platform
  currently has, instead of matching config-declared keyword rules. Any
  failure — network, a malformed response — is caught and falls back to
  the deterministic planner automatically, so a broken/unreachable LLM
  never turns into a hard failure for the caller. Ships defaulted to
  `PLANNER_TYPE=deterministic` (no code change needed to keep using the
  config-driven planner). The identical `LLM_PROVIDER` choice also drives
  `REASONING_TYPE=llm` (Self-Reflection & Reasoning, below) — see
  `docs/openrouter-integration.md` for how to try either one for free.
- `DeterministicPlanner` (still the default) reads each profile's own
  config-declared rules and resolves a small set of template tokens
  (`{date:N}`/`{coupon_code}`/`{discount_percent}`).
- **Execution Memory & Learning (Phase 6, Stage 4)** — every finished Goal
  execution is learned from: a repeat occurrence of a similar goal skips
  planning entirely and reuses whichever capability sequence already
  worked, reinforcing (or, on a later failure, degrading) that same
  learned pattern's own success rate. `GET /api/agents/memory/insights`/
  `POST /api/agents/memory/suggest` (+ the identical
  `agent.memory.insights`/`agent.memory.suggest` MCP capabilities) expose
  it — reusing the *existing* execution history (Stage 1) rather than a
  second, parallel record of the same thing.
- **Multi-Agent Collaboration (Phase 6, Stage 5)** — one persona can hand a
  sub-task to another's own planning rules and get back a real, executed
  result: `agent.collaboration.delegate`/`agent.collaboration.messages`
  MCP capabilities, backed by a durable communication log (`AgentMessage`)
  and a real work-tracking state machine (`DelegationRequest` —
  `pending -> in_progress -> completed/failed/timeout`). Delegation always
  runs under the *same* real, already-authenticated Agent's own
  permissions — this codebase has no separate "Sales Agent" identity with
  its own permission set (`AgentType` is a per-call planning persona, not
  an identity), a real correction from the original request confirmed
  before writing any code — see `docs/multi-agent-collaboration.md`.
- **Self-Reflection & Reasoning (Phase 6, Stage 6 — the last Stage of
  Phase 6)** — every Goal execution now thinks before acting and reflects
  after acting: `think()` produces a real, confidence-scored `ReasoningTrace`
  (what the goal needs, what's worked before, alternatives considered)
  before a plan is even created, and `reflect()` produces a second one
  from the real outcome once execution finishes. Both are LLM-backed
  (`REASONING_TYPE=llm`) with an automatic, honest deterministic fallback
  on any failure — never a hard failure for the caller — and both are
  persisted and retrievable afterward:
  `GET /api/agents/reasoning/trace`/`GET /api/agents/reasoning/explain`
  (+ the identical `agent.reasoning.trace`/`agent.reasoning.explain` MCP
  capabilities). Reasoning is deliberately explanatory, never
  plan-changing — nothing it produces is read back into planning or
  execution, the same restraint already applied to delegation above — see
  `docs/self-reflection.md`.

See `docs/agent-orchestrator.md`/`docs/agent-profiles.md`/`docs/llm-planner.md`/`docs/execution-memory.md`/`docs/multi-agent-collaboration.md`/`docs/self-reflection.md`
for the full architecture, how to add a new Agent persona, how to enable
the LLM planner, how learning works, how delegation works, and how
reasoning works, and `HANDOFF.md` §7.26/§7.27/§7.28/§7.29/§7.30/§7.31 for
the build log, including every deliberate correction made from the
original requests (real capability names in place of illustrative ones,
why `AuthContext` is threaded through this module's own Domain Service
interfaces as a documented exception, and more).

---

## 🎬 Showcase Demo

A `/showcase` chat UI (Showcase prep, §7.33 — built after Phase 6
finished, across 3 back-to-back passes, not a Phase 6 Stage) for watching
the Agent Orchestrator think, plan, execute, delegate, and reflect
**live**, against a realistic, pre-seeded store — not an empty fixture,
not a mocked response, and repeatable/shareable rather than a one-time
demo. It's a thin Interfaces-layer surface, the same shape the Admin
Dashboard already uses: every response it renders is `ExecuteGoalAction`'s
own, unmodified `ExecutionResultData::toArray()`; the live data panel
reuses the exact same `ListProductsAction`/`ListOrdersAction`/
`GetDashboardStatsAction` the Dashboard's own Controllers call; the
history sidebar reuses `ListExecutionsAction`/`GetExecutionResultAction`/
`GetReasoningTraceAction`/`ExplainReasoningAction` unmodified. No new
business logic exists anywhere behind it.

### Setup

```bash
php artisan db:seed --class=DemoShowcaseSeeder   # first time only
```

Two more env vars are worth setting, both entirely optional:

```bash
# .env — optional, both blank by default
SHOWCASE_PASSCODE=            # set this to gate /showcase behind a shared passcode
                               # before handing the demo link to anyone outside your
                               # own machine; blank (the default) means /showcase is
                               # open to anyone with the URL — the right choice for
                               # local development, not for a public staging URL.
OPENROUTER_API_KEY=           # set this so the in-app "🧠 Use real AI" toggle (below)
                               # actually reaches a real LLM; without it, the toggle
                               # still never errors — it just falls back to the same
                               # deterministic planner/reasoning silently, so you may
                               # not notice a difference. OpenRouter's own free tier
                               # (OPENROUTER_MODEL defaults to a $0 model) is the
                               # cheapest way to try this for real.
```

### Getting in

Visit **`/showcase`**. If `SHOWCASE_PASSCODE` is unset, you land directly
on the chat. If it's set, you're redirected to `/showcase/enter` — enter
the passcode once and your browser session stays in until it expires or
you clear cookies; this gate is a plain session flag with no relationship
at all to the Dashboard's own `/login` (a real `User` with a real
password) — sharing the showcase passcode with someone grants them
nothing on `/dashboard`, and vice versa.

To wipe and rebuild the demo store between sessions (recommended before
every live demo — a prior run's own chat history/coupons/Executions get
cleared):

```bash
php artisan demo:reset
```

**Resetting never breaks anything above it.** `ShowcaseController`/
`ShowcasePanelController` all resolve the demo Tenant fresh, by slug,
on every single request (`TenantRepositoryInterface::findBySlug('demo-showcase')`)
— nothing caches a Tenant/Agent id across requests except the one
session-scoped bearer token `ShowcaseController::index()` mints, which a
reset Tenant's own newly-seeded Agent transparently accepts again the
next time `/showcase` loads (a stale token from before the reset simply
fails `AuthenticateAgentAction` once and `chat()` returns a clean 401
telling the visitor to reload — never a 500). The passcode gate is
entirely independent of Tenant data altogether. Resetting mid-demo is
safe; the worst case is one reload.

`DemoShowcaseSeeder` builds one well-known Tenant (`demo-showcase`): 40
Products across 5 Categories, 2 Warehouses, 6 variant-bearing Products, 40
Customers (some with a real Loyalty balance), 10 CRM Tickets, active
Coupons/DiscountRules, 180 real Cart→Payment→Order checkouts backdated
across the last 85 days (so the sales-trend numbers actually vary day to
day), and 3 Executions pre-run through the real `ExecuteGoalAction` so
Execution Memory and the history sidebar both already have something to
show from the moment you first load the page.

### What it demonstrates

- **Transparent reasoning** — every response shows a real, confidence-scored
  `think()` (🤔 pre-execution reasoning: what the goal needs, alternatives
  considered) *before* the plan runs, a step-by-step execution checklist,
  and a real `reflect()` (✅ post-execution reasoning) afterward — the exact
  `ReasoningTrace`s Self-Reflection & Reasoning (Phase 6, Stage 6)
  persists, rendered live instead of queried after the fact.
- **A "🧠 Use real AI" toggle** next to the input box — flips
  `agent-orchestrator.{planner,reasoning}.type` to `llm` and `.llm.provider`
  to `openrouter` for that one request only (the same config-driven
  binding `PlannerConfigTest`/`ReasoningConfigTest` already prove in
  tests, reached here from a real Controller), then explicitly restores
  the original values afterward regardless of outcome — proven with a
  dedicated test, not just documented. With no `OPENROUTER_API_KEY`, the
  request still succeeds via the same automatic deterministic fallback
  every LLM path in this codebase already has.
- **Live delegation between personas** — the CEO persona's own
  `config/agents/ceo.php` includes a `delegate` planning rule (the
  cheapest available increment named in this module's own build log,
  HANDOFF §8.85): a "Delegate this promotional campaign to another agent"
  goal resolves to a single `agent.collaboration.delegate` step, rendered
  as an animated hand-off between the CEO and Sales personas' own
  avatars, with the *real*, nested `ExecutionResultData` the Sales
  persona's own planning rules produced (`commerce.coupon.create` +
  `notification.message.send`) opened inline as its own mini execution
  card — not a canned "delegation succeeded" message.
- **A live data panel, not a static screenshot** — three tabs (KPIs,
  Products, Orders) beside the chat, backed by the same read Actions the
  Dashboard uses, scoped to the one demo Tenant. Only the active tab
  refetches after each chat turn, so a presenter can watch a coupon or a
  KPI number change in the panel right after the Agent that just created
  it finishes — real cause and effect, on screen, at the same time.
- **A History sidebar (🕘 button, top-right)** — every past Execution for
  this Tenant, newest first; opening one replays its real, persisted
  pre/post reasoning and step checklist read-only, through the identical
  card the live chat itself renders.
- **Suggested Goals** — 2-4 one-click buttons per persona, each wired to a
  real keyword its own `config/agents/{type}.php` profile actually
  recognizes (never a made-up phrase that would silently fall back to a
  different, less interesting plan), so a presenter never has to type or
  guess a working goal live on stage.

### A suggested demo walkthrough

1. Open `/showcase` (enter the passcode first if one is configured),
   leave the **CEO** persona selected (the default).
2. Click **"📈 Increase sales this week"** — watch the pre-reasoning
   thoughts and confidence bar appear, then all 4 steps
   (`report.sales.generate` → `analytics.kpi.calculate` →
   `commerce.coupon.create` → `notification.message.send`) complete in
   sequence, then the reflection. Glance at the **KPIs** tab — it just
   refreshed with the real numbers this run computed.
3. Click **"🤝 Delegate a campaign to Sales"** — this time there's only
   one step, `agent.collaboration.delegate`, rendered as an animated
   arrow from 🧑‍💼 CEO to 📈 Sales. Open the nested card: the Sales
   persona really did plan and run its own 2-step plan
   (`commerce.coupon.create` + `notification.message.send`) under the
   same call.
4. Turn on **"🧠 Use real AI"** and send **"Check our inventory levels"**
   — if `OPENROUTER_API_KEY` is set, the pre-reasoning thoughts and
   decision text now come from a real model instead of the deterministic
   template; if not, nothing breaks, it just reads the same as before
   (worth narrating either way, since the silent fallback *is* the point).
5. Switch to the **Sales** persona and click **"🎯 Launch a promotional
   campaign"** to show the same persona acting on its own, unprompted by
   a delegation. Switch to **Support**/**Finance** to show the other two
   personas' own, narrower rule sets.
6. Open the **🕘 History** drawer and click the oldest item — the exact
   same reasoning/step card renders again, read-only, proving nothing
   about this UI is faked or randomly generated per view.
7. Point at the **Orders** tab at any point — every number on it is a
   real, backdated Order from `DemoShowcaseSeeder`, not a placeholder.

See `HANDOFF.md` §7.33 for the full build log across all 3 passes —
including a real bug this work's own live smoke-testing caught that no
automated test happened to (a Suggested Goal colliding with a learned
`ExecutionPattern` from the seeded Execution history) — and how it was
fixed and regression-tested.

---

## Technology Stack

OpenCommerce Platform is built using modern technologies and architectural principles.

### Backend

- Laravel 12
- PHP 8.2+
- MySQL
- Redis
- Queue Workers

### Architecture

- Modular Monolith
- Domain-Driven Design (DDD)
- Clean Architecture
- Event-Driven Architecture
- API-First Design
- Capability-Driven Design
- Model Context Protocol (MCP)

---

## Design Principles

OpenCommerce follows a set of core architectural principles.

- Core is independent of business domains.
- Every capability should be discoverable.
- MCP handles communication, not business logic.
- UCP standardizes commerce data and workflows.
- Components should remain modular and independently replaceable.
- Developer Experience is a first-class priority.
- Extensibility is preferred over customization.
- Existing business systems should require minimal changes to become Agent Ready.

---

## Roadmap

Phases 1 through 5 are complete. See `docs/roadmap.md` for the full
phase/stage breakdown and `HANDOFF.md` for the detailed build log.

- [x] OpenCommerce Core (Identity, Auth, Organizations, Tenancy, Permissions)
- [x] Agent Registry
- [x] Capability Registry
- [x] MCP Gateway (`/mcp/v1` and `/mcp/v2`)
- [x] Universal Commerce Protocol (UCP)
- [x] SDK Platform (PHP, Python, Node.js/TypeScript, and Go SDKs — `packages/opencommerce-sdk*`; a Laravel-specific wrapper SDK remains planned)
- [x] Commerce Connectors (Mock + real WooCommerce)
- [x] Event System (Domain Events across every module)
- [x] Multi-tenant Infrastructure (shared-database, `tenant_id` isolation)
- [x] Developer Documentation (`docs/`, `HANDOFF.md`, per-version API docs)
- [x] Commerce domain (Products/Variants, Cart, Multi-warehouse Inventory, Orders, Customers, Checkout, Discounts, Bulk Operations, Subscriptions)
- [x] CRM, Finance, Workflows, Loyalty, Reporting domains
- [x] Shipping & Logistics domain (incl. a real carrier connector pattern)
- [x] Notifications domain (Email/SMS/Webhook/In-App)
- [x] Analytics & KPIs domain
- [x] Admin Dashboard (session-authenticated, bilingual EN/FA)
- [x] Agent Orchestrator (Phase 6, Stage 1 — goal -> plan -> execute, deterministic MVP planner)
- [x] Agent Profiles + CEO Agent (Phase 6, Stage 2 — config-driven personas, `config/agents/{type}.php`)
- [x] LLM-based Planner (Phase 6, Stage 3 — real OpenAI/Claude planning with automatic fallback, `PLANNER_TYPE=llm`)
- [x] Execution Memory & Learning (Phase 6, Stage 4 — pattern extraction from real execution history, learned-plan suggestions ahead of both Planners)
- [x] Multi-Agent Collaboration (Phase 6, Stage 5 — persona-to-persona delegation under the caller's own real permissions, `agent.collaboration.delegate`/`.messages`)
- [x] Self-Reflection & Reasoning (Phase 6, Stage 6 — the last Stage of Phase 6 — pre-execution `think()` and post-execution `reflect()`, LLM-backed with automatic deterministic fallback, `agent.reasoning.trace`/`.explain`)
- [x] Real Payment Gateways (Zibal + Stripe, redirect-based checkout via `RedirectPaymentGatewayInterface`/`PaymentGatewayRegistry`, extensible to any future gateway — see `docs/payment-gateways.md`)
- [ ] Real carrier (USPS/FedEx/DHL) and SMS gateway integrations (mock/stub today)
- [ ] Upgrade to Laravel 13 (blocked on the runtime environment moving to PHP 8.3+ first — see `docs/decisions.md`, Decision 003)

---

## Project Status

> ✅ **Phase 5 Complete — Advanced Commerce** · ✅ **Phase 6 Complete — AI Agent Orchestration**

OpenCommerce Platform has moved past the foundation stage: Core, MCP
Gateway, and UCP are stable, and 11 Domain Modules are built, tested, and
MCP-reachable — Commerce (incl. multi-warehouse inventory, variants, bulk
operations, and subscriptions), CRM, Finance, Workflows, Loyalty,
Reporting, Shipping, Notifications, Analytics, and the now-complete Agent
Orchestrator (config-driven Agent Profiles, a real CEO Agent persona, a
real LLM-based planner with automatic deterministic fallback, execution
memory & learning, persona-to-persona delegation under the caller's own
real permissions, and now pre/post-execution reasoning with the same
LLM-backed-with-automatic-fallback shape, plus, in Showcase prep §7.32, a
third LLM provider — OpenRouter, with free-model access), plus a
bilingual Admin Dashboard for human operators and a repeatable, shareable
`/showcase` chat UI (Showcase prep, §7.33 — see **🎬 Showcase Demo**
above) for watching the Agent Orchestrator plan, execute, delegate, and
reflect live against a realistic, pre-seeded store, with a real-AI
toggle, a conversation history sidebar, and an optional passcode gate for
sharing the link safely. 1102 automated tests pass across 124 MCP
capabilities, with zero known regressions.

Phase 6 (AI Agent Orchestration) is now fully complete, all 6 Stages.
Whoever drives scope next is choosing where the platform goes from here —
candidates include feeding reasoning back into planning (today it's
explanatory only), real async delegation, semantic/vector pattern
matching, additional Agent personas beyond CEO, and a Dashboard page
covering everything Phase 6 built — see `docs/agent-orchestrator.md`,
`docs/agent-profiles.md`, `docs/llm-planner.md`, `docs/execution-memory.md`,
`docs/multi-agent-collaboration.md`, `docs/self-reflection.md`, and
`docs/roadmap.md`.

---

## Contributing

OpenCommerce Platform is an open-source project.

We welcome developers, software architects, AI engineers, protocol designers, and contributors who share the vision of building the future of **Agentic Commerce** and **Agent-Ready Business Infrastructure**.

Whether you're improving documentation, implementing SDKs, building connectors, proposing architecture, or contributing code, your participation is always welcome.

---

## License

OpenCommerce Platform is released under the **MIT License**.
