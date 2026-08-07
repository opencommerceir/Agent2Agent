# The Complete OpenCommerce Platform Tutorial (English)

This folder is a complete, step-by-step tutorial of the **OpenCommerce Platform** project — from why it exists, through its architecture and modules, to its AI agent orchestration layer, and finally a runnable live demo.

The goal: someone with zero prior context on this project should be able to read the files below, in order, and come away with a full, practical understanding of the whole system — both the architecture and the *why* behind it.

> Note: this tutorial is a simplified, narrated distillation of the project's own `HANDOFF.md` and `docs/` files. For very fine-grained technical detail, `HANDOFF.md` is always the final authority.

---

## Reading order

| # | File | Topic |
|---|------|-------|
| 00 | This file | Table of contents |
| 01 | [Introduction and Vision](01-introduction-and-vision.md) | What the project is, why it exists, what it is not |
| 02 | [Architecture and Philosophy](02-architecture-and-philosophy.md) | Infrastructure First, layering, design principles |
| 03 | [Tech Stack and Project Structure](03-tech-stack-and-project-structure.md) | Laravel, folder structure, the per-module pattern |
| 04 | [The Core](04-the-core.md) | Identity, multi-tenancy, permissions, registries |
| 05 | [The MCP Gateway](05-mcp-gateway.md) | The agent-facing protocol, the Capability model |
| 06 | [The Commerce Module](06-commerce-module.md) | Product, cart, order, payment |
| 07 | [CRM, Finance, Workflows, Loyalty, Reporting](07-crm-finance-workflows-loyalty-reporting.md) | Phase 3 of the platform |
| 08 | [Shipping and Notifications](08-shipping-and-notifications.md) | Fulfillment and messaging |
| 09 | [i18n and the Admin Dashboard](09-i18n-and-admin-dashboard.md) | Translation and human authentication |
| 10 | [Analytics and API Versioning](10-analytics-and-api-versioning.md) | KPIs, reports, v1/v2, performance |
| 11 | [Advanced Commerce](11-advanced-commerce.md) | Variants, multi-warehouse, discounts, subscriptions |
| 12 | [AI Agents: the Agent Orchestrator](12-agent-orchestrator.md) | Introducing Phase 6 and the goal-execution engine |
| 13 | [Agent Profiles and the LLM Planner](13-agent-profiles-and-llm-planner.md) | CEO/Sales/Support/Finance personas and LLM-backed planning |
| 14 | [Execution Memory and Multi-Agent Collaboration](14-execution-memory-and-multi-agent-collaboration.md) | Learning from past runs and delegating between agents |
| 15 | [Self-Reflection, Reasoning and OpenRouter](15-self-reflection-and-openrouter.md) | Reasoning and free LLM access |
| 16 | [The Showcase Demo](16-showcase-demo.md) | Live chat, data panel, history, passcode gate |
| 17 | [Architecture Patterns and Gotchas](17-architecture-patterns-and-gotchas.md) | Rules to always follow |
| 18 | [Install, Run and Test](18-install-run-and-test.md) | Getting it running on your own machine, running the demo |
| 19 | [Technical Debt and Roadmap](19-technical-debt-and-roadmap.md) | What's still unfinished and what comes next |
| 20 | [Integration and Usage Paths](20-integration-and-usage-paths.md) | Self-hosting vs. connecting to OpenCommerce.ir's hosted infrastructure |
| 21 | [SDKs and Multi-Language Integration](21-sdks-and-multi-language-integration.md) | Installing and using the five official SDKs: PHP, Laravel, Python, Node.js/TypeScript, and Go |
| 22 | [Monetization and Business Use Cases](22-monetization-and-business-use-cases.md) | Ten real revenue models — from white-label hosting to the Iranian local market |

---

## The project in one paragraph

**OpenCommerce Platform** is an open-source infrastructure layer that helps businesses become **Agent Ready** for AI agents. Instead of building a custom integration between an AI agent and every business system (a storefront, a CRM, an accounting tool, ...) from scratch, OpenCommerce offers one standard middle layer that:

- exposes business capabilities as **Capabilities** (discoverable abilities),
- lets agents **discover**, understand, and **securely execute** those capabilities,
- routes everything through one single door: the **MCP Gateway** (built on the Model Context Protocol).

One critical point to hold onto through the whole tutorial: **commerce is only the first domain, not the whole point.** The platform's **Core** must never know anything about a product, an order, a customer, or a payment — those all live in **Domain Modules**. Core only ever provides infrastructure: identity, multi-tenancy, permissions, and events.

The next 22 files unpack this idea one step at a time.

Start with file 01 👉 [Introduction and Vision](01-introduction-and-vision.md)
