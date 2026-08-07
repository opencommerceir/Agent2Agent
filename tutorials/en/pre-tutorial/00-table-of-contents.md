Next: [Web & API Fundamentals](01-web-and-api-fundamentals.md) →

# Pre-Tutorial: Technical Concepts Before You Start

## What is this section?

The main tutorial series (files `00` through `22` in `tutorials/en/`) assumes the reader already knows basic concepts like APIs, databases, layered architecture, software testing, and — since this project is built heavily around AI agent orchestration — concepts like LLMs, prompts, and agents. If you're new to any of these areas, reading that series directly may run into terms it never stops to define (because that series sees itself as explaining *this project*, not explaining *foundational software engineering concepts*).

This folder — the **pre-tutorial** — fills exactly that gap. Every technical term, every architectural pattern, every professional concept used anywhere in this project (the code, `HANDOFF.md`, and the main tutorial series) is explained here from zero, in plain language, with examples, and with one constant focus: **exactly where in this real project is this concept actually used?**

## Who is this pre-tutorial for?

- Someone who just learned backend programming and wants to understand a real, large-scale project.
- Someone who has worked with a different language/framework but isn't familiar with Laravel, Clean Architecture, or Domain-Driven Design (DDD).
- Someone who has only used generative AI (like ChatGPT) as a consumer, but doesn't know what "Agent," "MCP," or "Tool Calling" actually mean from an engineer's point of view.
- Someone who wants a complete mental map of every term they're about to see, before starting file 01 of the main series.

If you're already comfortable with everything above, feel free to skip this section and go straight to [file 01 of the main series](../01-introduction-and-vision.md) — this pre-tutorial isn't a mandatory prerequisite, it's a safety net.

## Structure: 12 chapters, from foundational to specialized

Each chapter is readable on its own, but the order below is recommended, since each chapter builds on the concepts of the one before it:

| # | Chapter | What you'll learn |
|---|---------|--------------------|
| 01 | [Web & API Fundamentals](01-web-and-api-fundamentals.md) | HTTP, REST, JSON, client/server, backend/frontend |
| 02 | [Databases & Performance](02-databases-and-performance.md) | Tables, relationships, transactions, indexes, N+1, locking |
| 03 | [Software Architecture & Design Principles](03-software-architecture-and-design-principles.md) | Layering, SOLID, dependency injection, coupling/cohesion |
| 04 | [Domain-Driven Design (DDD)](04-domain-driven-design.md) | Entity, Value Object, Repository, Domain Event, Aggregate |
| 05 | [Common Design Patterns](05-common-design-patterns.md) | Repository, Factory, Strategy/Connector, Registry, DTO |
| 06 | [Backend Infrastructure & Laravel](06-backend-infrastructure-and-laravel.md) | MVC, Service Provider, Middleware, Migration, Queue, Cache |
| 07 | [Security, Auth & Multi-Tenancy](07-security-auth-and-multi-tenancy.md) | Authentication vs. Authorization, RBAC, tokens, multi-tenancy |
| 08 | [Software Testing](08-software-testing.md) | Unit/Feature/Integration tests, mocks, TDD, coverage |
| 09 | [AI, LLMs & AI Agents](09-ai-llms-and-ai-agents.md) | LLMs, prompts, tokens, tool calling, AI agents, reasoning |
| 10 | [The MCP Protocol & Agent Ecosystem](10-the-mcp-protocol-and-agent-ecosystem.md) | Model Context Protocol, capabilities, planner/executor |
| 11 | [Online Payments & Fintech](11-online-payments-and-fintech.md) | Payment gateways, webhooks, idempotency, HMAC signatures |
| 12 | [Professional Engineering & Business Concepts](12-professional-engineering-and-business-concepts.md) | SaaS, SDKs, versioning, technical debt, CI/CD, open-source licensing |

## The writing convention of this pre-tutorial

Every concept across these 12 chapters follows this exact, fixed structure:

1. **Simple definition** — as if explaining it to a non-technical friend.
2. **Why it matters** — what problem it solves; what the world looks like without it.
3. **📍 In this project** — exactly where in the OpenCommerce Platform's code/architecture you actually see this concept, with a reference to the real file or the related file in the main tutorial series.

This means that by the time you reach the main series (files 01–22), every term you encounter there will already carry a real, lived meaning and a real example — not an abstract textbook definition.

## Quick map: which chapter prepares you for which main-series file?

| Pre-tutorial chapter | Directly prepares you for... |
|---|---|
| 01, 02 | Main series files 4 and 5 (The Core and the MCP Gateway) |
| 03, 04, 05 | Main series files 2 and 3 (Architecture and Project Structure) — and effectively files 6 through 11 |
| 06 | Main series files 3 and 18 (Project Structure, Install & Run) |
| 07 | Main series file 4 (The Core) |
| 08 | Main series file 18 (Install, Run and Test) and every chapter's own testing sections |
| 09, 10 | Main series files 12–16 (all of Phase 6 — AI agents) |
| 11 | Main series files 6 and 21 (The Commerce module and real payment gateways) |
| 12 | Main series files 19, 20, 21, and 22 (technical debt, integration paths, SDKs, monetization models) |

---

Let's start with the first chapter 👉 [Web & API Fundamentals](01-web-and-api-fundamentals.md)

Next: [Web & API Fundamentals](01-web-and-api-fundamentals.md) →
