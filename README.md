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

OpenCommerce provides official SDKs that enable developers to make their applications Agent Ready with minimal effort.

Planned SDKs include:

- PHP SDK
- Laravel SDK
- TypeScript SDK
- Node.js SDK
- Python SDK
- Go SDK

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
  (+ `LLM_PROVIDER=openai|claude` and the matching API key) and
  `LLMPlanner` asks a real GPT-4 or Claude model to plan each Goal against
  every capability the platform currently has, instead of matching
  config-declared keyword rules. Any failure — network, a malformed
  response — is caught and falls back to the deterministic planner
  automatically, so a broken/unreachable LLM never turns into a hard
  failure for the caller. Ships defaulted to `PLANNER_TYPE=deterministic`
  (no code change needed to keep using the config-driven planner).
- `DeterministicPlanner` (still the default) reads each profile's own
  config-declared rules and resolves a small set of template tokens
  (`{date:N}`/`{coupon_code}`/`{discount_percent}`).

See `docs/agent-orchestrator.md`/`docs/agent-profiles.md`/`docs/llm-planner.md`
for the full architecture, how to add a new Agent persona, and how to
enable the LLM planner, and `HANDOFF.md` §7.26/§7.27/§7.28 for the build
log, including every deliberate correction made from the original
requests (real capability names in
place of illustrative ones, why `AuthContext` is threaded through this
module's own Domain Service interfaces as a documented exception, and
more).

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
- [x] SDK Platform (PHP SDK — `packages/opencommerce-sdk`)
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
- [ ] Additional SDKs (TypeScript, Node.js, Python, Go — PHP SDK is the
      only one built so far)
- [ ] Real carrier (USPS/FedEx/DHL) and SMS gateway integrations (mock/stub today)
- [ ] Upgrade to Laravel 13 (blocked on the runtime environment moving to PHP 8.3+ first — see `docs/decisions.md`, Decision 003)

---

## Project Status

> ✅ **Phase 5 Complete — Advanced Commerce** · 🚧 **Phase 6, Stage 3 — LLM-based Planner**

OpenCommerce Platform has moved past the foundation stage: Core, MCP
Gateway, and UCP are stable, and 11 Domain Modules are built, tested, and
MCP-reachable — Commerce (incl. multi-warehouse inventory, variants, bulk
operations, and subscriptions), CRM, Finance, Workflows, Loyalty,
Reporting, Shipping, Notifications, Analytics, and the new Agent
Orchestrator (config-driven Agent Profiles, a real CEO Agent persona, and
now a real LLM-based planner with automatic deterministic fallback), plus
a bilingual Admin Dashboard for human operators. 966 automated tests pass
across 118 MCP capabilities, with zero known regressions.

The current focus is building out the remaining AI Agent personas beyond
CEO, and extending the LLM planner (recursive planning, self-reflection,
multi-agent collaboration) — see `docs/agent-orchestrator.md`,
`docs/agent-profiles.md`, `docs/llm-planner.md`, and `docs/roadmap.md`.

---

## Contributing

OpenCommerce Platform is an open-source project.

We welcome developers, software architects, AI engineers, protocol designers, and contributors who share the vision of building the future of **Agentic Commerce** and **Agent-Ready Business Infrastructure**.

Whether you're improving documentation, implementing SDKs, building connectors, proposing architecture, or contributing code, your participation is always welcome.

---

## License

OpenCommerce Platform is released under the **MIT License**.
