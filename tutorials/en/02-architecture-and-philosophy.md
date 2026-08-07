← [Introduction and Vision](01-introduction-and-vision.md) | Next: [Tech Stack and Project Structure](03-tech-stack-and-project-structure.md) →

# 2. Overall Architecture and Design Philosophy

The previous file explained *why* this project exists. This one turns that goal into a real architecture.

## The layer stack, top to bottom

A request from an AI agent travels this path:

```
AI Agents
   ↓
MCP Gateway            ← entry point, authentication, authorization
   ↓
OpenCommerce Core       ← identity / organization / tenant / permissions
   ↓
Capability Registry     ← the discoverable catalog of capabilities
   ↓
Agent Registry           ← information about the agents themselves
   ↓
Connection Manager        ← connections to external systems
   ↓
Domain Modules             ← Commerce, CRM, Finance, Shipping, ...
   ↓
External Business Systems   ← WooCommerce, payment gateways, ...
```

Each box has exactly one responsibility, and **no box does another box's job.**

## The platform's core building blocks

### 1. OpenCommerce Core
The foundation of the whole platform. Responsible for:
- Identity & Authentication
- Organizations
- Multi-tenancy
- Permissions
- API keys
- Configuration
- Connections
- Event bus
- Audit logs

### 2. Agent Registry
Information about registered AI agents — identity, permissions, supported protocols, and available connections.

### 3. Capability Registry
The platform's discovery layer. Every connected business system exposes its capabilities in a standard format — "Search Products," "Check Inventory," "Create Order," "Get Customer Info," "Generate Report," "Create Invoice." Agents discover these **dynamically**, never through hardcoded integrations.

### 4. MCP Gateway
The communication layer between agents and OpenCommerce, built on the **Model Context Protocol (MCP)**. Responsibilities:
- Authentication
- Authorization
- Capability discovery
- Tool execution
- Structured responses

**Critical rule: business logic is never implemented inside the MCP Gateway.** It is purely a communication layer.

### 5. Universal Commerce Protocol (UCP)
A normalized commerce model. Different commerce systems (Shopify, WooCommerce, Magento, custom Laravel apps) are transformed into one shared structure so agents always see a consistent shape, regardless of where the data came from.

### 6. SDK Platform
Official SDKs to help developers make their apps Agent Ready with minimal effort (a PHP SDK exists today — `packages/opencommerce-sdk`).

### 7. Connectors
Adapters to external business systems — like the real WooCommerce connection we'll see in later files.

## Architectural goals

| Goal | Description |
|---|---|
| **Agent First** | Agents are first-class citizens; they must discover, understand, execute, and receive structured responses. |
| **Domain-independent Core** | The same Core must support Commerce, CRM, ERP, HR, healthcare, logistics, and manufacturing alike. |
| **Extensibility** | New domains, connectors, and capabilities must be addable without changing existing infrastructure. |
| **Enterprise Ready** | Multi-tenancy, authentication, authorization, security, auditing, scalability, external integration. |

## Core restrictions

One of the most important rules in the entire project: **Core must never contain business domain logic.**

Core must not know about:
- Products
- Orders
- Customers
- Inventory
- Payments
- Shipping
- Discounts
- Marketing rules

Right vs. wrong, concretely:

```
❌ Wrong:   Core/ProductService.php
✅ Correct:  Modules/Commerce/ProductService.php
```

If you ever catch yourself writing code inside `app/Core/` that reaches for `App\Modules\...`, stop right there — that is precisely the mistake that was made and caught once, early in this project's history.

## The architecture inside every module

Every domain module (Commerce, Shipping, whichever) follows a fixed, Clean-Architecture-shaped layout:

```
Module/
├── Domain/            ← pure business logic, no framework dependency
│   ├── Entities/        core objects (Product, Order, ...)
│   ├── ValueObjects/     value types (Money, SKU, ...)
│   ├── Events/           domain events
│   ├── Repositories/     storage Interfaces (never implementations)
│   ├── Services/         pure domain services, no dependencies
│   └── Exceptions/        domain errors
├── Application/        ← use-case orchestration
│   ├── Actions/           one Action = one concrete business operation
│   ├── DTOs/               data transfer objects between layers
│   └── Services/           application services (e.g. an external HTTP client)
├── Infrastructure/     ← real implementations (Eloquent Models, Repositories)
├── Interfaces/          ← the HTTP/MCP entry layer (controllers)
└── Support/, Tests/
```

**Golden rule:** business logic never lives inside a Controller or an Eloquent Model — only inside `Domain` and `Application`.

## The five principles, in practice

The five principles from the previous file take on concrete meaning here:

1. **Architecture over speed** → e.g. when Phase 5 asked for a second, parallel stock column for product variants, the team extended the existing two-phase inventory system instead of taking the quick path (file 11).
2. **Maintainability over shortcuts** → the repeated "Actions composing Actions" pattern instead of duplicating logic.
3. **Explicit over magic** → MCP rate limiting is an explicit Action call, not hidden middleware.
4. **Interfaces over tight coupling** → every module connects to every other module only through Interfaces, never a concrete class.
5. **Documentation before complexity** → every phase and every big decision is recorded in `HANDOFF.md` (and now in this tutorial).

## Multi-tenancy strategy

The architecture must support a real SaaS:

- **Phase 1 (current):** shared database, isolated by a `tenant_id` column — every tenant-scoped table carries it.
- **Phase 2 (future):** database-per-tenant.

No decision is allowed to block the future migration to Phase 2 — this is a permanent design constraint.

## The development workflow

Never jump straight into implementation. The order is always:

1. **Understand:** analyze the problem, requirements, existing architecture, dependencies.
2. **Design:** explain architecture, responsibilities, data flow, database impact, and trade-offs.
3. **Approval:** wait for confirmation before major implementation.
4. **Implement:** write clean code per the standards.
5. **Review:** verify architectural compliance, security, tests, and documentation.

In later files (especially 6 through 16), you'll repeatedly see the team, before building a phase, first auditing the request against the existing code — and when it found a mismatch or architectural risk, **stopping and asking** instead of guessing. That is steps 1 and 2 in action.

## Summary of this file

- The platform is layered top to bottom: agent → MCP Gateway → Core → Capability Registry → Domain Modules → external systems.
- Core stays independent of any domain, always.
- Every module follows the same layered structure (Domain/Application/Infrastructure/Interfaces).
- Five engineering principles (architecture, maintainability, explicitness, interfaces, documentation) sit behind every decision.

Next, we turn this architecture into a real folder map and technology stack.

---
← [Introduction and Vision](01-introduction-and-vision.md) | Next: [Tech Stack and Project Structure](03-tech-stack-and-project-structure.md) →
