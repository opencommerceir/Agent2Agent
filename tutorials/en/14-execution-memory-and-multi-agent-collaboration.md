← [Agent Profiles and the LLM Planner](13-agent-profiles-and-llm-planner.md) | Next: [Self-Reflection, Reasoning and OpenRouter](15-self-reflection-and-openrouter.md) →

# 14. Phase 6 (Stages 4 and 5) — Execution Memory, Learning, and Multi-Agent Collaboration

## Stage 4 — Execution memory and learning

### The biggest correction of this stage: no new entity

The original request wanted a brand-new entity and table called `ExecutionMemory`. But a simple check showed: **this already exists** — `ExecutionMemoryRepositoryInterface` (file 12) has been doing exactly this since the very first stage: recording the goal text, the planned and executed steps, duration, status, tenant, and agent. Even the module's own documentation had already flagged this Interface as the future home for execution memory. Building a second, parallel entity would have re-created exactly the "two sources of truth" risk seen repeatedly in files 10 and 11. **Result: zero new code for this part — pure reuse.**

### What was genuinely new: pattern extraction and learning

- `ExecutionPattern` (a new entity) — a learned pattern: goal keyword + persona + which capabilities succeeded/failed + success rate.
- `PatternExtractor` — a fixed, simple vocabulary of 5 keywords (`sales`, `revenue`, `inventory`, `customer`, `report`) — deliberately **not** derived from any profile's own `planning_rules`, so a learned pattern doesn't silently stop matching if a profile's config later changes.
- `LearningService` — suggests a learned plan (`suggestPlan`) and returns summary stats (`getInsights`).

### A subtle, real correction: success rate must be able to go down, too

The first version of this logic only ever built a pattern after a **successful** run, and never revisited it after a later failure — meaning the success rate could only climb, never fall. This was fixed: after **every** finished run (success or failure), if a matching pattern already exists, `recordOutcome()` is called either way — a real failure genuinely lowers a previously-learned pattern's success rate.

### A real bug this stage's own tests caught before shipping

The first version of `suggestPlan()` inserted a raw, unresolved token (like the literal string `'{date:-7}'`) directly into a suggested step — without converting it to a real date. Result: a "learned" plan that should have succeeded instead failed with a validation error. Fix: the token-resolution logic was extracted from `DeterministicPlanner` into a shared class (`AgentProfileInputResolver`) that both `DeterministicPlanner` and `LearningService` now depend on — instead of two copies that could drift apart.

### Memory sits outside the main planning path

`ExecuteGoalAction` asks `LearningService` **before** calling any `PlannerInterface` at all — "do we already have a sufficiently successful learned pattern for this goal?" If yes, planning is skipped entirely and the same previously-successful sequence runs again. This is deliberately not a Decorator wrapping `PlannerInterface`, because that Interface is explicitly documented as tenant-independent, while a learned suggestion is inherently tenant-specific.

## Stage 5 — Multi-agent collaboration

### The biggest correction of the entire Phase 6 body of work

The original request described `ExecuteGoalAction` automatically detecting that a plan step needs a permission missing from the current persona's own (*descriptive*) `permissions` list, and delegating to a different `AgentType` to "fix" it. Careful review found this design **impossible** under this codebase's real identity model:

1. A profile's `permissions` list is already, explicitly, documentation-only, never a second enforcement layer (see the previous file) — using it as a real gate would contradict that already-established rule.
2. There is no separate, permission-bearing identity per `AgentType`. The Agent Orchestrator's own `AgentType` (`ceo`/`sales`/`support`/`finance`) is a **completely different, unrelated** enum from Core's own `Agent.type` (`shopping`/`analytics`/...). **The same real, token-authenticated Agent can call `/api/agents/ceo` for one goal and `/api/agents/sales` for the next** — with identical real permissions both times.

The logical conclusion: delegating to a different persona only ever changes *who builds the plan*, never *what the real, authenticated caller is actually allowed to do* — so a permission gap can never be fixed by delegating around it. This was raised and confirmed with the user before any code was written.

### The confirmed solution: capability-based delegation

`agent.collaboration.delegate` is just an ordinary MCP capability — exactly like any other. When called, it re-invokes the exact same, unmodified `ExecuteGoalAction` for the target persona, but under the **original caller's own real `AuthContext`** ("Actions composing Actions," file 2). If the real Agent genuinely lacks the required permission, the delegated step fails exactly the way any other unauthorized step already does — `PlanExecutor` marks that one step `Failed` and continues; delegation never returns a 403, it returns 200 with an honest `status: "failed"` in the nested result.

### An important distinction to keep straight

`DelegationRequest.status` only tracks whether the delegation *mechanism* completed a real attempt — never whether the delegated task's own business outcome succeeded. Even if the nested result is `partial`/`failed`, `DelegationRequest` can still be `Completed`. `Failed`/`Timeout` are reserved only for the mechanism itself breaking (an unrecognized persona, a real timeout).

### Real elapsed-time timeout, not true concurrent interruption

No real interrupt mechanism (like `pcntl`) exists in this codebase — `AgentCommunicationService::requestDelegation()` measures real elapsed time and, if it exceeds the default 30-second limit, throws `DelegationTimeoutException`.

## Summary table

| Stage | Key takeaway |
|---|---|
| Memory & Learning | No new entity for memory; success rate can also decrease |
| Multi-Agent Collaboration | Delegation only changes who builds the plan, never real access |

By the end of these two stages: 1031 tests, 122 MCP capabilities. In the next file, we see the last stage of Phase 6 — where agents learn to **think before acting and reflect after acting** — and then their connection to free LLMs through OpenRouter.

---
← [Agent Profiles and the LLM Planner](13-agent-profiles-and-llm-planner.md) | Next: [Self-Reflection, Reasoning and OpenRouter](15-self-reflection-and-openrouter.md) →
