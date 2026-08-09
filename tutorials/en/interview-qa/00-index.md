Next: [Project Storytelling & Big Picture](01-project-storytelling.md) →

# 00. Technical Interview Handbook — Overall Map

## What is this handbook?

This is a real simulation of the questions a tough-but-fair technical interviewer (exactly the style used at top tech companies) would ask about **this real project, OpenCommerce Platform** — not generic textbook questions. Every model answer references a specific file, class, or real decision in this exact repository, because that's precisely what an experienced interviewer picks up on in seconds: a memorized answer versus someone who actually built the thing and understands why it was built that way.

**Important contract:** reading this handbook is not a substitute for understanding the code. The goal is to take what you already know (from having read the [pre-tutorial](../pre-tutorial/00-table-of-contents.md) and the [main tutorial series](../00-table-of-contents.md), or from having read the code itself) and turn it into a confident, interview-ready answer.

## The full map of 22 files

### Part A — Story & Big Picture
| # | File | Focus |
|---|---|---|
| 00 | This file | Overall map and how to use this handbook |
| 01 | [Project Storytelling](01-project-storytelling.md) | Introducing the project, your role, business context, why this stack |
| 02 | [Overall Architecture](02-overall-architecture.md) | Modular monolith vs. microservices, layering, full request-lifecycle trace |

### Part B — Core Engineering
| # | File | Focus |
|---|---|---|
| 03 | [Laravel & Design Patterns](03-laravel-and-design-patterns.md) | Service container, providers, Repository/Factory/Strategy/Observer as actually used |
| 04 | [Database & Performance](04-database-and-performance.md) | Schema design, indexing, N+1 prevention, caching/Redis |
| 05 | [API Design](05-api-design.md) | REST conventions, versioning, DTOs, validation, error handling |
| 06 | [Testing & Quality](06-testing-and-quality.md) | Unit/feature tests, testing DDD aggregates, the CI pipeline |

### Part C — Architecture Deep Dives
| # | File | Focus |
|---|---|---|
| 07 | [DDD Tactical](07-ddd-tactical.md) | Entities, Value Objects, Aggregates, Domain Events in the real code |
| 08 | [DDD Strategic](08-ddd-strategic.md) | Bounded Contexts, context mapping, why the module boundaries sit where they do |
| 09 | [Event-Driven & Messaging](09-event-driven-messaging.md) | Sync vs. async, listeners, eventual consistency, the Outbox pattern, where Event Sourcing does and doesn't apply |
| 10 | [CQRS & Read Models](10-cqrs-read-models.md) | Command/query separation, the Reporting pipeline |

### Part D — Platform Concerns
| # | File | Focus |
|---|---|---|
| 11 | [Multi-Tenancy](11-multi-tenancy.md) | Isolation models, tenant scoping in code, preventing cross-tenant leaks |
| 12 | [Security](12-security.md) | Auth flows, authorization, OWASP in practice, rate limiting, secrets management |
| 13 | [Payments & Fintech](13-payments-fintech.md) | Gateway integration, idempotency, retries, reconciliation, never using floats for money |

### Part E — Business Modules
| # | File | Focus |
|---|---|---|
| 14 | [Commerce Core](14-commerce-core.md) | Catalog, cart, checkout, order, shipping, notification flows |
| 15 | [CRM & Loyalty](15-crm-loyalty.md) | Customer lifecycle, points/rewards domain modeling |
| 16 | [Finance, Workflows & Reporting](16-finance-workflows-reporting.md) | The ledger, Workflow state machines, KPIs and aggregation |

### Part F — AI
| # | File | Focus |
|---|---|---|
| 17 | [AI Agents & the Orchestrator](17-ai-agents-orchestrator.md) | LLM integration, all 6 Orchestrator stages, memory, tool calling |
| 18 | [The MCP Protocol](18-mcp-protocol.md) | What MCP is, why it was chosen over direct calls, a full architecture trace |

### Part G — Interview Readiness
| # | File | Focus |
|---|---|---|
| 19 | [Tradeoffs & Failures](19-tradeoffs-failures.md) | "Why didn't you use X?", failure scenarios, debugging war stories |
| 20 | [Scaling & Redesign](20-scaling-redesign.md) | 100x traffic, bottlenecks, redesign debates |
| 21 | [Full Mock Interviews](21-full-mock-interviews.md) | Three complete simulated interview transcripts (Mid/Senior/Architect) with a critique of each answer |
| 22 | [Behavioral & Role](22-behavioral-and-role.md) | Your exact role, mistakes you made, conflicts, how you learned — answered with real project stories |

## How to use this handbook

Depending on the level you're interviewing for:

| Level | Reading priority |
|---|---|
| **Mid-Level** | Parts A, B, E — then file 21 (the Mid section of the mock interviews) |
| **Senior** | All of Parts A through E, plus 07, 08, 11, 13, 19 — then file 21 (the Senior section) |
| **Architect / Staff** | The whole handbook, with special focus on 08, 09, 10, 19, 20 — then file 21 (the Architect section) |

## The contract of every Q&A entry

Every question follows exactly this structure:

- 🎯 **What the interviewer is REALLY testing** — the hidden intent behind the question, what it's actually probing for
- ✅ **Model answer** — a complete, confident answer with a direct reference to real code
- 🔁 **Likely follow-ups** — each with a short hint at the answer
- 🚩 **Red flags** — answers that would reveal the candidate doesn't actually understand the material

## Going deeper

If you want to understand a question in depth, not just answer it:

- [`HANDOFF.md`](../../../HANDOFF.md) at the repository root — the complete, raw log of every architectural decision, every correction, and every rejected alternative, with the reasoning behind each
- [The main tutorial series](../00-table-of-contents.md) — the full explanation of every module and every phase
- [The pre-tutorial](../pre-tutorial/00-table-of-contents.md) — if you're not comfortable with a foundational technical term

---

Let's start with the first file 👉 [Project Storytelling & Big Picture](01-project-storytelling.md)

Next: [Project Storytelling & Big Picture](01-project-storytelling.md) →
