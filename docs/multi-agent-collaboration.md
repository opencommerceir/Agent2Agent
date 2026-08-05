# Multi-Agent Collaboration

> Added in Phase 6, Stage 5 (§7.30 in `HANDOFF.md`). This is the
> how-it-works guide; `docs/agent-orchestrator.md` is the module's own
> architecture reference and `HANDOFF.md` §7.30 carries the full build
> narrative, including the identity-model correction below — read that
> section before extending this feature.

## What it is

Two things, matching this stage's own request:

1. **Agent Communication** — a durable, tenant-scoped log
   (`AgentMessage`) of every persona-to-persona interaction.
2. **Delegation** — one Agent persona hands a sub-task to another
   persona's own planning rules, gets back a real, executed
   `ExecutionResult`, and the outcome is tracked start-to-finish
   (`DelegationRequest`).

There is deliberately **no automatic mid-plan delegation** — the request's
own design (`ExecuteGoalAction` detecting a missing permission and
rerouting to another persona) does not work in this codebase's real
identity model. Read the next section before assuming otherwise.

## Personas are not identities

This is the single most important fact to understand before touching this
feature. `AgentType` (`ceo`/`sales`/`support`/`finance`) is a **per-call
planning classification** — which `AgentProfile` config plans a given
Goal — not a real, permission-bearing identity:

- A real `Agent` row (Core's own Agent Registry) has its own `type` too,
  but from a completely different, unrelated enum
  (`App\Core\Domain\ValueObjects\AgentType`: `shopping`/`analytics`/
  `customer_service`/`custom`). There is no mapping between "a real
  Agent's own Core type" and "which Orchestrator persona it plans as."
- The same real, bearer-token-authenticated Agent can call
  `POST /api/agents/ceo` for one Goal and `POST /api/agents/sales` for the
  next — same identity, same real Role/Permission grants, different
  persona per call.
- Every real permission check (`CapabilityToolInvoker::invoke()`) runs
  against `AuthContext::$agentId` — the real Agent — never against
  `AgentProfile::$permissions` (which HANDOFF §7.27 already documents as
  descriptive metadata only, never a second enforcement layer).

**Consequence**: delegating a task to a different persona changes *whose
planning rules and prompt framing produce the plan* — it never changes
*what the real, already-authenticated caller is actually allowed to do*.
If the real Agent's Role doesn't grant `commerce.coupons.create`,
delegating to the `sales` persona does not grant it — the same real
identity runs the delegated plan too. See "Known scope decisions" below
for what this means for error handling.

## How delegation actually works

`agent.collaboration.delegate` is an **ordinary MCP capability** —
reachable exactly like `commerce.coupon.create` or any other, from a
direct MCP call, from `/mcp/v1/execute`, or (in the future) as a plan step
`PlanExecutor` invokes through `CapabilityToolInvoker` like any other
capability. There is no special execution branch inside `ExecuteGoalAction`
for it — that Action is completely unmodified by this stage.

```
POST /mcp/v1/execute
{ "capability": "agent.collaboration.delegate",
  "input": { "from_agent": "ceo", "to_agent": "sales",
             "task": "Create a 15% discount coupon for summer promotion",
             "priority": 8 } }
```

1. `DelegateToAgentAction` builds a `DelegationRequest` (rejects
   delegating a persona to itself) and calls
   `AgentCommunicationService::requestDelegation()`.
2. A `delegation`-type `AgentMessage` is recorded (audit log).
3. **The delegated sub-goal runs through the *unmodified* `ExecuteGoalAction`**
   — same `AuthContext` the caller was invoked with, `agentType` swapped to
   the delegate target (Actions composing Actions, HANDOFF §3 pattern #3).
   This means the sub-goal is itself planned (possibly using a learned
   pattern, §7.29), executed, persisted, and event-dispatched exactly like
   any other Goal.
4. Real elapsed wall-clock time is measured; exceeding the request's own
   30s timeout throws `DelegationTimeoutException` (`DelegationRequest`
   marked `Timeout`) instead of returning the late result.
5. Otherwise `DelegationRequest` is marked `Completed` — **regardless of
   whether the delegated Goal's own `status` was `completed`, `partial`,
   or `failed`** (see "Known scope decisions" for why) — and a
   `response`-type `AgentMessage` is recorded.
6. The real `ExecutionResultData` is returned as this capability's own
   `result` field.

```json
{ "delegation_id": 7,
  "result": {
    "goal": "Create a 15% discount coupon for summer promotion",
    "agent_type": "sales",
    "steps": [
      { "capability": "commerce.coupon.create", "status": "completed", "...": "..." },
      { "capability": "notification.message.send", "status": "completed", "...": "..." }
    ],
    "status": "completed",
    "summary": "Goal executed: 2 of 2 step(s) completed, in 0.09s."
  } }
```

## Reading the log

```
POST /mcp/v1/execute { "capability": "agent.collaboration.messages", "input": { "agent_type": "sales" } }
```

Returns every `AgentMessage` where this persona is either the sender or
the recipient, most recent first — permission `agent.collaboration.read`.
There is no dedicated `/api/agents/collaboration/*` HTTP route this stage
(unlike §7.29's own `/api/agents/memory/*`) — not requested, MCP-only.

## Known scope decisions

- **`DelegationRequest.status` tracks the delegation *mechanism*, not the
  delegated task's own business outcome.** `Completed` means "a real
  attempt ran and a real `ExecutionResultData` came back" — even if that
  result's own `status` is `partial` or `failed` (e.g. the delegate lacks
  a real permission the task needed). `Failed`/`Timeout` are reserved for
  the mechanism itself breaking (an unrecognized `agent_type`, a genuine
  timeout) — not for a nested step's own ordinary failure, which
  `PlanExecutor` already catches and records per-step without ever
  throwing (unchanged since §7.26). Check `result.status` inside the
  returned `ExecutionResultData`, not just `DelegationRequest.status`, to
  know whether the delegated task actually succeeded.
- **A permission failure inside a delegated task surfaces as a generic
  failed step, not a 403.** `PlanExecutor` discards the original exception
  type by design (`$step->markAsFailed($e->getMessage())`) — the same
  behavior `agent.goal.execute` itself already has for any plan with an
  unauthorized step. `agent.collaboration.delegate` inherits this exactly;
  it is not a new gap this stage introduced.
- **No automatic mid-plan delegation.** The request's own
  `ExecuteGoalAction::requiresDelegation()`/`executeWithDelegation()`
  design was not built — see "Personas are not identities" above for why
  it can't detect anything real to delegate *for*. `config/agents/*.php`
  profiles were not changed to include a delegation step either, so
  existing tests (`GoalExecutionTest`/`CEOAgentTest`) stay byte-identical.
- **`parent_execution_id` is always null this stage.** A delegation
  happens *while* the parent's own plan is still running — `ExecuteGoalAction`
  only persists its own `Execution` row after the *whole* plan finishes,
  so no real parent id exists yet at delegation time.
- **`ResultAggregatorInterface`/`ResultAggregator` have no automatic
  caller yet.** `agent.collaboration.delegate` only ever targets one
  persona per call; combining several delegations' own results into one
  is real, tested, unused machinery — the same "built the mechanism, no
  caller yet" shape `ExecutionPlanData` carried between §7.26 and §7.29.
- **Priority is stored and validated (1-10), not load-bearing.** Every
  delegation runs synchronously and immediately — there is no real queue
  of multiple *pending* delegations for a numeric priority to reorder yet.
- **No cycle detection beyond "can't delegate to yourself."**
  `DelegationRequest::create()` rejects `from_agent === to_agent`, but a
  longer cycle (A delegates to B, B delegates to A) is not detected —
  none of the 4 shipped profiles declare a delegation step today, so this
  is latent, not exercised, but a real gap for a future profile/LLM plan
  that does.
- **`MessageStatus::Pending`/`Received` are modeled but unreached** — every
  delegation runs synchronously, so a message is always recorded already
  `Sent` or `Processed`, never sitting in an intermediate state. Real
  async delegation (a queued Job another process later picks up) is the
  natural future trigger for these two states — see `HANDOFF.md` §8/§9.
