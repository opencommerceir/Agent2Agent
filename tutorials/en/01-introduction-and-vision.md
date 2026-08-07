← [Table of Contents](00-table-of-contents.md) | Next: [Architecture and Philosophy](02-architecture-and-philosophy.md) →

# 1. Introduction and Vision

## Why does this project exist at all?

Imagine an AI agent needs to place an order on behalf of a user, check warehouse stock, or open a support ticket. Today, doing any of these requires:

- writing a custom integration against that specific system's API,
- figuring out its authentication scheme,
- translating its data shape (which differs per platform),
- manually respecting its permissions and business rules.

This means every "agent + business system" pairing repeats this work from scratch. The result: duplicated effort, inconsistent implementations, and poor scalability.

**OpenCommerce Platform** solves this by building a **shared infrastructure layer between AI agents and business systems**:

```
AI Agents
   ↓
OpenCommerce Platform
   ↓
Business Systems (storefront, CRM, accounting, warehouse, ...)
```

Instead of a custom, direct connection, every business exposes its capabilities **once** through OpenCommerce, and any agent following the shared protocol can discover and use them.

## The bigger picture

You can read the history of the web this way:

- The internet connected **people to information**.
- Cloud platforms connected **businesses to services**.
- The next step is connecting **AI agents to business capabilities**.

Just as every business had to become "mobile-friendly" and "SEO-friendly," the next generation of digital businesses has to become **Agent Ready**. OpenCommerce wants to be the open infrastructure for that shift.

## What OpenCommerce is

- **Agent Enablement Infrastructure**
- **Capability Discovery Platform**
- **AI Integration Layer**
- **Open Protocol Platform**
- **Developer Platform**
- **Business Connector Framework**

## What OpenCommerce is *not*

This is just as important as the positive definition, because it shapes every architectural decision downstream:

- Not a simple e-commerce app.
- Not a marketplace.
- Not a CMS.
- Not a traditional ERP.
- Not a bag of CRUD modules.

**Commerce is only the first domain built on top of this infrastructure — it is not the end goal.** The Core platform must always stay independent of any specific domain, so tomorrow the same infrastructure could carry a standalone CRM, an ERP, a healthcare system, or any other domain.

## Core philosophy: Infrastructure First, Domains Second

Always keep this ordering in mind:

1. Infrastructure first.
2. Domains second.

Core must never know about:

- Products
- Orders
- Customers
- Inventory
- Payments
- Shipping
- Discounts / promotions
- Any specific platform like Shopify / WooCommerce / Magento

All of these belong to **Domain Modules**. This separation is the root cause of nearly every architectural decision you'll see in the files that follow.

## The primary goal

Build a platform where AI agents can:

- **Discover** capabilities.
- Understand what actions are available.
- **Execute** real business operations.
- **Respect permissions** (no agent ever does more than it's allowed to).
- Communicate securely.
- **Integrate** with existing software — without having to replace it.

The key point: **the goal is not to replace a business's existing systems, but to prepare them for an agent-driven world.**

## The five engineering principles behind every decision

These five principles keep resurfacing throughout the entire codebase (especially in file 17):

| Principle | Meaning |
|---|---|
| **Architecture over speed** | A fast implementation with poor architecture is technical debt. Correct architecture always wins. |
| **Maintainability over shortcuts** | Prefer a solution that's easier to understand and extend, even if it takes longer to write. |
| **Explicit over magic** | Hidden behavior is forbidden. Code must be explicit and predictable. |
| **Interfaces over tight coupling** | Components connect through Interfaces so they stay replaceable, never through direct dependence on another class. |
| **Documentation before complexity** | Every major decision gets documented — exactly what `HANDOFF.md`, and this tutorial, are doing. |

## Tech stack at a glance

- **Backend:** Laravel 12, PHP 8.2+
- **Infrastructure:** MySQL, Redis, Queue Workers
- **Architectural style:** Modular Monolith (unified but modular), Domain-Driven Design, Clean Architecture, SOLID principles, Event-Driven Architecture, API-First design

The next file turns these principles into a real, layered architecture map.

---
← [Table of Contents](00-table-of-contents.md) | Next: [Architecture and Philosophy](02-architecture-and-philosophy.md) →
