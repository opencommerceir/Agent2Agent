← [Technical Debt and Roadmap](19-technical-debt-and-roadmap.md) | Next: [SDKs and Multi-Language Integration](21-sdks-and-multi-language-integration.md) →

# 20. Integration and Usage Paths — How Others Can Use This Project

Every previous file looked at this platform from the inside — its architecture, modules, and AI layer. This file answers a completely different question: **how can someone outside this team — a business, a developer, an agency — actually use this project, and what's in it for them?**

Short answer: there are **two fundamentally different paths**, and neither depends on the other.

## The two main paths

### Path A — Download, install, and self-host (open source)

This project is open source under the **MIT license** (per `composer.json`) — meaning anyone can take the code, install it on their own server, modify it, and even use it commercially.

The practical steps are exactly what file 18 already covered: clone the repository, install dependencies, migrate the database, and start the server — on their own infrastructure, not ours.

What matters more than the installation itself: because of the "Infrastructure First, Domains Second" philosophy (file 2), the Core of this platform was built completely independent of e-commerce. Anyone choosing this path isn't just downloading a storefront — they're getting a **complete Agent-Ready infrastructure**, with a ready-made identity/multi-tenancy/permissions/MCP-gateway core they can build anything on top of.

**What can they concretely do with this path?**

1. **Run it directly for their own business** — use the existing 10 modules (Commerce, CRM, Finance, Shipping, ...) with their own real data, and their store/business becomes Agent Ready today, with full control over data and branding.
2. **Build a completely new domain on the same Core** — this is exactly what the Core was designed for (file 2: "Core must support Commerce, healthcare, HR, real estate alike"). A team can fork this repository and, instead of commerce, build a completely different domain (medical scheduling, property management, HR, whatever) — following the same seven-step module pattern (file 3), without ever touching the Core or the MCP Gateway.
3. **Host it for multiple clients (white-label SaaS)** — since multi-tenancy has existed in the Core since day one (file 4), an agency or dev shop can run one instance and sell or lease it to several different businesses, each as its own tenant.
4. **Replace or add their own connectors** — the Connector Pattern (files 6 and 8) already exists for WooCommerce and one shipping provider; anyone can add a new connector (Shopify, a local payment gateway) following the same pattern, without touching the rest of the system.
5. **Full data sovereignty** — for businesses that, for regulatory or internal-policy reasons, cannot let their data leave their own infrastructure, this is the only viable path — no data ever goes to us.

What they need: PHP/Laravel skills (or someone to hire), a server, and to follow file 18. **This path has zero dependency on OpenCommerce's own hosted infrastructure.**

### Path B — Connect to OpenCommerce's own hosted infrastructure (OpenCommerce.ir)

Instead of installing and maintaining anything, in this path someone simply becomes a **client** connecting to an already-running instance of the platform — hosted on OpenCommerce's own infrastructure, at **OpenCommerce.ir**.

The flow is exactly what files 4 and 5 already covered:

1. An identity is provisioned for them on the platform (a Tenant/Organization + an Agent).
2. They receive a Bearer token (`AgentToken`) and specific permissions (a Role/Permission set covering exactly the capabilities they need).
3. From that point on, without installing anything on their own systems, they can connect directly with HTTP+JSON requests to the MCP Gateway and use any of the 124 existing capabilities across 10 modules.

This path suits anyone who **doesn't want** to deal with installation, updates, server security, backups, or infrastructure maintenance — they just want to connect, starting today, to a real, Agent-Ready business backend.

**Do they need an SDK?** No — since the MCP Gateway is nothing more than a standard HTTP+JSON API (file 5), any language (PHP, Python, Go, Node, whatever) can connect directly. Today only an official PHP SDK exists (`packages/opencommerce-sdk`); other languages are on the roadmap, but their absence doesn't block the connection — the protocol, not the SDK, is what makes this possible.

## Comparing the two paths

| | Path A: self-hosting | Path B: connecting to hosted infrastructure |
|---|---|---|
| Installation/server required | Yes | No |
| Full control over code and data | Yes, 100% | No — data stays on OpenCommerce's infrastructure |
| Can add a completely new domain (not commerce) | Yes, only possible here | No — they're only a consumer of the existing API |
| Time to start | Needs installation and setup (file 18) | Nearly instant, just needs a token |
| Maintenance/update responsibility | On them | On the OpenCommerce team |
| Best fit for | A business/agency that wants full control or a new domain | A team that just wants to connect quickly to an Agent-Ready backend |

## Who exactly uses which, and for what? (concrete examples)

| User | Real need | Best-fit path |
|---|---|---|
| A store that wants to become Agent Ready without managing a server | A ready backend, fast | Path B |
| A large store needing full branding and data control | Complete infrastructure ownership | Path A |
| A dev agency wanting to resell this to multiple clients | Multi-tenancy + full control | Path A (using the existing multi-tenant Core) |
| A developer building an AI agent in Python/Go who needs a real business backend | Fast connection, no installation | Path B — either direct MCP calls or the goal-driven Agent Orchestrator layer |
| An open-source contributor wanting to build a whole new domain (healthcare, real estate, HR) on this Core | Full code access and the ability to add modules | Only Path A is possible |
| An organization with data-sovereignty requirements (government, banking, healthcare) | Data must never leave their own infrastructure | Only Path A |
| Someone who just wants to learn or contribute to the project | Clone and run locally, no real deployment needed | Path A (exactly what file 18 already covers) |

## An important reminder: the protocol is identical either way

One thing worth never forgetting: whether someone installs this platform themselves (Path A) or connects to a hosted instance (Path B), **how they interact with it is exactly the same** — because both go through the same standard MCP Gateway (file 5). This is precisely the benefit of standardizing on a protocol instead of a proprietary API: code written to talk to a self-hosted instance works against the hosted infrastructure too, and vice versa, with nothing more than a changed base URL and token.

Both levels of interaction are always available, regardless of which path was chosen:

- **Level 1 — direct capability calls**: for deterministic, planned automation (e.g. "search this product," "place this order") — through the MCP Gateway (file 5).
- **Level 2 — goal-driven, through an AI agent**: for when the client's own application is itself an AI agent and just wants to hand over a plain-text goal, letting the platform's own Planner/Executor/Reasoning engine (files 12–15) decide what to run and how.

## One-line summary

This project is simultaneously an **installable open-source product** and a **connectable piece of infrastructure** — and that dual nature is a direct consequence of the same foundational architectural decision from file 2: a Core built completely independent of any domain is just as well-suited to being forked and extended into something new as it is to being run and consumed as a ready-made service.

---

One file remains: the next file gets fully practical — if your project is in Python, Go, or Node.js/TypeScript, exactly how and with which SDK you connect to this platform.

← [Technical Debt and Roadmap](19-technical-debt-and-roadmap.md) | Next: [SDKs and Multi-Language Integration](21-sdks-and-multi-language-integration.md) →
