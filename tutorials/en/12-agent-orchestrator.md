← [Advanced Commerce](11-advanced-commerce.md) | Next: [Agent Profiles and the LLM Planner](13-agent-profiles-and-llm-planner.md) →

# 12. Phase 6 — AI Agents: Introducing the Agent Orchestrator

Here we reach the most interesting part of the project: turning a **plain-text goal** (like "increase sales by 15% this week") into a sequence of real MCP capability calls that actually execute. Phase 6 has six stages; this file opens the first one.

## This module is a new kind of module

Every prior module (Commerce, CRM, ...) owned real business data and real business logic. `AgentOrchestrator` **owns neither** — it is purely an **orchestration layer**: it turns plain text into a sequence of calls to *existing* capabilities from other modules. "No business logic of its own" was the first and most explicit rule stated in the original request for this module, and every design decision below exists specifically to honor it.

## The overall flow: Goal → Plan → Execute

```
A plain-text Goal
    ↓
PlannerInterface       ← turns this goal into a sequence of capability calls (an ExecutionPlan)
    ↓
PlanExecutorInterface   ← actually executes each step
    ↓
ToolInvokerInterface     ← walks the exact same MCP security path for every capability
    ↓
ExecutionResult           ← the final result, persisted and returned
```

### Why is this path safe?

`CapabilityToolInvoker` calls every capability through **the exact same sequence** — `GetCapabilityAction → CheckPermissionAction → CapabilityExecutionService` — that `AbstractMCPGatewayController` (file 5) itself uses. This means a capability invoked through a Goal is exactly as authorized, validated, and executed as one called directly via `/mcp/v1/execute`. This module never builds a second execution path.

## Three real corrections to the original request

Like every other stage in this project, the request was checked against the live codebase before any code was written:

1. **The sample capability names in the request didn't exist at all** — `reporting.sales.summary`, `analytics.top_products`, `inventory.check` weren't registered anywhere. They were replaced with real ones: `report.sales.generate`, `analytics.kpi.calculate` (called twice, once each for `top_products` and `low_stock_products`), `commerce.coupon.create`, `notification.message.send`.
2. **Every step's input had to carry a real value, not an empty array** — `DeterministicPlanner` computes a real 7-or-30-day date range, extracts a discount percentage from the goal text (defaulting to 10%), and generates a valid random coupon code.
3. A new Notification type (`PromotionAnnouncement`) was added since none of the existing ones fit "a marketing message."

## The first Planner: `DeterministicPlanner`

The first version of the Planner is a simple set of keyword rules — not real AI. If the goal text contains "sales," a specific sequence of capabilities runs; if it contains "support," a different one does. This is a deliberate MVP, designed to be swapped out behind the same Interface for an LLM-backed Planner later (which we see in the next file).

## A documented exception to the AuthContext rule

File 4 said `AuthContext` never crosses the MCP boundary. Here is a fully deliberate exception:

- `PlannerInterface::createPlan(Goal $goal)` — takes **no** identity at all, because planning is tenant-independent (deciding "which capabilities satisfy this goal" has nothing to do with any specific tenant's data).
- `ToolInvokerInterface` and `PlanExecutorInterface` **do** take `AuthContext` directly — because their whole job is to re-enter the same MCP boundary. This isn't a violation of the rule; it's the rule's mirror image: the rule exists to keep `AuthContext` from leaking into ordinary module Actions, not to stop the exact entry point the rule protects from using it.

## A real, old Core bug found here

While wiring error handling for the new `/api/agents/*` routes, it turned out `MCPExceptionHandler` incorrectly flattened an unmatched route into `INTERNAL_ERROR`/500 instead of a real 404. This bug had **already existed** in the code but was never reachable, since every `mcp/*` route was an exact string with nothing to fail to match. This module's first parametrized route (`{agentType}`) exposed the latent bug, and it was fixed on the spot.

## Execution memory — genuinely persisted, not just in-memory

The original request described execution memory as "in-memory for MVP." But that conflicted with the real need for endpoints like `GET /api/agents/executions/{id}` (which must also work in a completely separate, later HTTP request) — an in-memory array would vanish the moment the request ended. This inconsistency was noticed and resolved in favor of what actually needs to work: `ExecutionMemoryRepositoryInterface` has been backed by two real tables from day one (`agent_executions`, `agent_execution_steps`).

## Two ways to reach the same logic

From this module onward, every operation is reachable both through MCP and through a dedicated HTTP surface:

```
agent.goal.execute          ⇄     POST /api/agents/{agent_type}
agent.execution.get          ⇄     GET  /api/agents/executions/{id}
agent.execution.list          ⇄     GET  /api/agents/executions
```

Both routes call the exact same three Actions — logic is never duplicated (pattern #19, fully covered in file 17).

## Summary table of this stage's main entities

| Entity | Role |
|---|---|
| `Goal` | The goal text plus the requesting persona |
| `ExecutionPlan` / `ExecutionStep` | The generated plan and its steps |
| `ExecutionResult` | The final execution outcome |

By the end of this stage: 920 tests, 116 MCP capabilities. In the next file, we see how this simple Planner became configuration-driven (personas) and was genuinely connected to a real LLM.

---
← [Advanced Commerce](11-advanced-commerce.md) | Next: [Agent Profiles and the LLM Planner](13-agent-profiles-and-llm-planner.md) →
