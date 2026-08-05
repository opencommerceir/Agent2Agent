# Agent Orchestrator Module

> Added in Phase 6, Stage 1 (§7.26 in `HANDOFF.md`). This document is the
> module's own reference; `HANDOFF.md` §7.26 carries the full narrative of
> what was built, what was corrected from the original request, and why.

## Overview

The Agent Orchestrator is an **orchestration layer** that lets AI Agents
(a CEO Agent, a Sales Agent, a Support Agent, a Finance Agent, ...) state a
business Goal in plain text and have it turned into a sequence of real
OpenCommerce MCP capability calls, executed on the Agent's behalf.

It holds **no business logic of its own**. Every fact it produces —
today's sales total, a created Coupon, a sent Notification — comes from an
existing Domain Module's own capability, called through the exact same
`CapabilityExecutionService`/`CapabilityHandlerRegistry` machinery
`/mcp/v1/execute` itself uses. The Orchestrator only ever decides *which*
capabilities to call, in what order, with what input, and what to do when
one of them fails.

## Architecture

| Concept | Class | Role |
|---|---|---|
| Goal | `Domain\Entities\Goal` | A business objective as plain text + an `AgentType` classification. |
| Planner | `Domain\Services\PlannerInterface` | Turns a Goal into an `ExecutionPlan` (an ordered list of capability calls). `DeterministicPlanner` is the one MVP implementation — hardcoded keyword rules. |
| ExecutionPlan / ExecutionStep | `Domain\Entities\{ExecutionPlan,ExecutionStep}` | The plan itself, and each individual planned capability call (`capability` + `input` + `priority`), with mutable `status`/`output`/`error` as it runs. |
| Executor | `Domain\Services\PlanExecutorInterface` | Runs every step of a plan, in order, never aborting on a single step's failure. `PlanExecutor` is the one implementation. |
| ToolInvoker | `Domain\Services\ToolInvokerInterface` | Invokes exactly one capability by name. `CapabilityToolInvoker` is the one implementation — backed entirely by Core's own `GetCapabilityAction` / `CheckPermissionAction` / `CapabilityExecutionService`, the same three building blocks `AbstractMCPGatewayController` itself uses. |
| ExecutionResult | `Domain\Entities\ExecutionResult` | The finished outcome of one plan run — every step's final state, a derived `status` (`completed`/`partial`/`failed`/`empty`), and a generic completion summary. |
| Memory | `Domain\Repositories\ExecutionMemoryRepositoryInterface` | Persists every finished `ExecutionResult` (and its steps) so it can be listed/retrieved later. `EloquentExecutionMemoryRepository` is the one implementation — two real tables (`agent_executions`/`agent_execution_steps`), not an in-process array, so history survives across requests. |

## Execution flow

1. A caller sends a Goal — either `POST /api/agents/{agent_type}` (a human-
   or system-facing HTTP client) or the `agent.goal.execute` MCP capability
   (another Agent, or a future multi-agent orchestration one level up) —
   both reuse the exact same `ExecuteGoalAction`.
2. `ExecuteGoalAction` builds a `Goal`, dispatches `GoalReceived`, and asks
   the bound `PlannerInterface` for an `ExecutionPlan`.
3. `PlanExecutor` runs each `ExecutionStep` through `CapabilityToolInvoker`,
   in order. Each step is authenticated as the calling Agent, permission-
   checked against the target capability's own `requiredPermissions`, input-
   validated, and executed — identically to a direct `/mcp/v1/execute`
   call. **A failed step is recorded and execution continues** — it never
   aborts the rest of the plan.
4. The finished `ExecutionResult` is persisted via
   `ExecutionMemoryRepositoryInterface` and `GoalCompleted` is dispatched.
5. The caller gets back the full step-by-step result, a derived `status`,
   and a plain-language `summary`.

## Planner

**Today: `DeterministicPlanner`** — a small, hardcoded set of keyword
rules over the Goal's own text (not the `AgentType` — see "Known scope
decisions" below):

| Goal text contains | Plan |
|---|---|
| `sales` | `report.sales.generate` → `analytics.kpi.calculate` (top products) → `analytics.kpi.calculate` (low stock) → `commerce.coupon.create` → `notification.message.send` |
| `support` / `ticket` | `crm.ticket.list` (open tickets) |
| `finance` / `revenue` / `invoice` | `report.revenue.generate` → `finance.invoice.list` |
| anything else | an empty plan (`status: empty`, an explanatory summary) |

**Future: an LLM-based planner** — a drop-in replacement behind
`PlannerInterface`. Nothing above the Interface (`PlanExecutor`,
`ExecuteGoalAction`, either HTTP/MCP surface) needs to change; only the
`PlannerInterface` binding in `AgentOrchestratorServiceProvider::register()`
would be swapped.

## Supported Agents

`AgentType` (`ceo`/`sales`/`support`/`finance`) is recorded on every Goal
and every persisted Execution as routing/classification metadata. It is
**not** yet what `DeterministicPlanner` branches on — see "Known scope
decisions" below.

## API

```
POST /api/agents/{ceo|sales|support|finance}
Authorization: Bearer <agent-token>
{ "goal": "Increase sales by 15% this week" }
```

```json
{
  "id": 1,
  "goal": "Increase sales by 15% this week",
  "agent_type": "ceo",
  "steps": [
    { "capability": "report.sales.generate", "input": {"start_date": "2026-07-29", "end_date": "2026-08-05"}, "priority": "high", "status": "completed", "output": {"report": {"...": "..."}}, "error": null },
    { "capability": "analytics.kpi.calculate", "input": {"kpi_type": "top_products", "...": "..."}, "priority": "medium", "status": "completed", "output": {"...": "..."}, "error": null },
    { "capability": "analytics.kpi.calculate", "input": {"kpi_type": "low_stock_products", "...": "..."}, "priority": "medium", "status": "completed", "output": {"...": "..."}, "error": null },
    { "capability": "commerce.coupon.create", "input": {"code": "COUPON-A1B2C", "discount_type": "percentage", "discount_value": 15}, "priority": "low", "status": "completed", "output": {"coupon": {"...": "..."}}, "error": null },
    { "capability": "notification.message.send", "input": {"type": "promotion_announcement", "channel": "email", "...": "..."}, "priority": "low", "status": "completed", "output": {"...": "..."}, "error": null }
  ],
  "summary": "Goal executed: 5 of 5 step(s) completed, in 0.18s.",
  "status": "completed",
  "execution_time": 0.18
}
```

```
GET /api/agents/executions              # this tenant's own past runs (optional ?agent_type=&status=&limit=)
GET /api/agents/executions/{id}         # one past run by id
```

The identical 3 operations are also reachable over MCP, for a caller that
is itself an Agent: `agent.goal.execute` / `agent.execution.get` /
`agent.execution.list` (permissions `agent.goals.execute` /
`agent.executions.read`).

Every exception either surface can raise — a missing/invalid bearer token,
a missing permission, an empty goal, an unknown execution id — is mapped
to the correct HTTP status by Core's own `MCPExceptionHandler` (extended
this stage to also cover `api/agents/*`, not a second, parallel error
mapper). See that class's own docblock.

## Known scope decisions (read before extending this module)

These are deliberate, documented departures from a literal reading of the
original request — see `HANDOFF.md` §7.26 for the full reasoning behind
each:

- **Every capability name in `DeterministicPlanner` is real.** The
  request's own illustrative names (`reporting.sales.summary`,
  `analytics.top_products`, `inventory.check`) do not exist in this
  codebase's live Capability Registry — see the mapping table above for
  what each was corrected to.
- **Every step's `input` is filled with concrete, deterministic values**
  (a date range, a random `COUPON-XXXXX` code), not left empty — an empty
  `input` fails `MCPRequestValidationService` for every capability above.
  This is still orchestration, not business logic: it never decides what
  a *good* discount or campaign is, only supplies structurally-valid
  parameters, the same way any tool-calling orchestrator must.
- **`DeterministicPlanner` keys off the Goal's own text, not `AgentType`.**
  The request's own pseudocode did the same (`str_contains($goal->text, 'sales')`).
  A future LLM-based planner is the natural place for `AgentType` to start
  actually shaping the plan.
- **`summary` is a generic completion report** (step counts + timing),
  never a domain-aware narrative ("created coupon SALE15 and sent 500
  notifications") — producing that would require this module to
  understand what a capability's *output* means, which is exactly the
  business logic it must not contain. A future LLM-based summarizer is the
  natural place for a narrative summary.
- **`notification.message.send`'s `recipient` is a fixed placeholder
  address**, not a real customer/segment list — a Goal's own free text
  carries none, and building a segment/broadcast mechanism is out of
  scope for an orchestration layer. `NotificationType::PromotionAnnouncement`
  is a new, purely additive enum case (Notifications module) added because
  none of the pre-existing 5 types fit "a marketing message."
- **`ExecutionMemoryRepositoryInterface` is genuinely persisted** (two real
  tables), not a request-lifetime in-memory array — `GET /api/agents/executions/{id}`
  working across separate HTTP requests requires it.
- **`AuthContext` is threaded through `ToolInvokerInterface` /
  `PlanExecutorInterface` / `ExecuteGoalAction` directly** — the one
  deliberate exception to this codebase's usual "Actions/Domain Service
  interfaces take plain `tenantId`/`agentId`, never `AuthContext`" rule
  (`HANDOFF.md` §3 pattern #1), because this module's whole job is
  re-entering the same MCP capability boundary that rule was written to
  keep AuthContext *at*. See `ToolInvokerInterface`'s own docblock.

## Future Roadmap

- LLM-based planning (a second `PlannerInterface` implementation)
- Recursive planning (a step's own output feeding a later step's input)
- Self-reflection (the Orchestrator revising a plan mid-run based on a
  step's result)
- Multi-agent collaboration (one Agent's Goal spawning sub-Goals for
  another)
- A vector database for long-term, semantic execution memory — today's
  `ExecutionMemoryRepositoryInterface` is a simple relational log, chosen
  to already fit this future without implying it exists yet
- Folding `AgentType` into planning (see "Known scope decisions" above)
- A Dashboard page under `/dashboard/agents` (every other Phase 4/5
  resource got one; this module didn't request one)
