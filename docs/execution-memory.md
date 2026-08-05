# Execution Memory & Learning

> Added in Phase 6, Stage 4 (§7.29 in `HANDOFF.md`). This is the
> how-it-works guide; `docs/agent-orchestrator.md` is the module's own
> architecture reference and `HANDOFF.md` §7.29 carries the full build
> narrative, including the two corrections below.

## What it is

Three things, matching this stage's own request:

1. **Execution Memory Storage** — every finished Goal execution is a
   durable, queryable record (goal text, planned steps, real outcome per
   step, duration, tenant/agent).
2. **Pattern Extraction** — a successful run's own goal-and-capabilities
   shape is learned as an `ExecutionPattern`; a repeat occurrence of a
   similar goal reinforces (or, on a later failure, degrades) that same
   pattern's own success rate instead of being tracked independently.
3. **Learning & Suggestion** — `ExecuteGoalAction` consults the tenant's
   own learned patterns *before* either configured `PlannerInterface`
   (Deterministic or LLM) plans anything at all; a sufficiently-successful
   match skips planning entirely and reuses the learned capability list.

## Why no new `ExecutionMemory` entity

The request's own design named a new `ExecutionMemory` Entity +
`ExecutionMemoryRepositoryInterface` + `execution_memories` table. Both
that Entity name and that Repository Interface name already exist —
Phase 6 Stage 1 (§7.26) built `ExecutionMemoryRepositoryInterface` (backed
by `agent_executions`/`agent_execution_steps`) specifically to persist
every finished Goal execution, and `ExecuteGoalAction` has called
`$this->memory->save($result, ...)` after every run since that stage.
`docs/agent-orchestrator.md`'s own Future Roadmap already earmarked this
exact interface as the future home for execution memory:

> "A vector database for long-term, semantic execution memory — today's
> `ExecutionMemoryRepositoryInterface` is a simple relational log, chosen
> to already fit this future without implying it exists yet."

Building a second, parallel entity/table for the same concept would have
been the same two-sources-of-truth mistake this codebase has already
caught and corrected twice before — Product Variants extending `Inventory`
instead of adding a second stock column (§7.21), and Analytics reusing
Reporting's own Query Builders instead of re-aggregating (§7.18). Raised
and confirmed with the user before writing any code (the same weight those
two corrections carried) — this stage's own "Execution Memory Storage"
part is served entirely by reuse; only Pattern Extraction and
Learning/Suggestion are genuinely new work, on top of it.

## Why `agent.memory.history` doesn't exist

The request's own third MCP capability, `agent.memory.history`, would have
been functionally identical to the already-existing, already-tested
`agent.execution.list` (same tenant-scoped Execution history, same
`{executions: [...]}` shape) — reachable both via MCP and via
`GET /api/agents/executions`. Confirmed with the user and dropped rather
than built as a duplicate read path; only `agent.memory.insights` and
`agent.memory.suggest` are genuinely new capabilities this stage.

## How Pattern Extraction works

`PatternExtractor::patternFor(Goal $goal): string` classifies a goal's own
text against a fixed keyword vocabulary
(`sales`/`revenue`/`inventory`/`customer`/`report`) — every keyword found
is joined with `|` (e.g. `"sales|revenue"`); no match at all is the
sentinel `"general"`, which `ExecutionPattern::matches()` never matches
against anything (a `'general'` pattern would otherwise be a
false-positive machine, matching every future unrecognized goal). This is
a deliberate, documented MVP simplification — not NLP/embedding-based
similarity — the same "real, working, honestly scoped down" precedent
`CustomerLifetimeValue`'s own formula already set (§7.18/§8.52).

`LearnFromExecutionListener` reacts to the *existing* `GoalCompleted`
event (dispatched by `ExecuteGoalAction` since §7.26, unlistened-to until
now) on every finished Goal, success or failure:

- An already-learned pattern for this tenant/goal-pattern/Agent persona is
  looked up first (`ExecutionPatternRepositoryInterface::findExisting()`).
  If found, `ExecutionPattern::recordOutcome()` blends the new outcome
  into its running `successRate` — **on a failure too**, so a pattern that
  used to work can genuinely stop being suggested if it starts failing.
  This is a deliberate correction from the request's own pseudocode, which
  only ever called pattern extraction on success and never revisited an
  existing pattern on a later failure — a success rate that can only rise
  is not a real signal.
- If nothing matches and the run succeeded, a brand-new pattern is created
  from it (`PatternExtractor::extract()`).
- If nothing matches and the run failed, nothing is created — there is no
  successful capability list to seed a pattern from.

## How Learning & Suggestion works

`ExecuteGoalAction` calls `LearningServiceInterface::suggestPlan($goal,
$tenantId)` before consulting `PlannerInterface` at all:

```php
$plan = $this->learning->suggestPlan($goal, $context->tenantId);

if ($plan === null) {
    $profile = $this->profiles->findByType($agentType->value);
    $plan = $this->planner->createPlan($goal, $profile);
}
```

`suggestPlan()` finds every pattern matching the goal for this tenant/Agent
persona (`ExecutionPatternRepositoryInterface::findSimilarPatterns()`,
ordered by success rate then usage count), picks the best one **only if
its success rate is at least 50%**, and rebuilds an `ExecutionPlan` from
its own `successfulCapabilities()` — each capability's own default input
resolved through `AgentProfileInputResolver` (see below), never a raw,
literal `default_inputs` value.

This intentionally applies to **both** `PlannerInterface` implementations
uniformly — `DeterministicPlanner` and `LLMPlanner` never know learning
exists. `PlannerInterface` itself is deliberately tenant-independent (see
its own docblock); a learned suggestion is not, so it lives one level up,
in `ExecuteGoalAction` — the one Action in this codebase that already
legitimately holds a full `AuthContext` (see that class's own docblock for
why). Wrapping the configured Planner in a decorator instead was
considered and rejected: it would have meant either widening
`PlannerInterface`'s own contract (touching two already-reviewed
implementations) or duplicating the "try learning first" call inside both.

### The bug this stage's own tests caught

A learned pattern only ever remembers *which capabilities* succeeded —
never their resolved input values (`ExecutionPattern` has no `input`
field, by design; storing a full input template per learned pattern would
duplicate what `AgentProfile::defaultInputs()` already owns and let the
two drift apart). The first working version of `LearningService::suggestPlan()`
passed a capability's *raw* `AgentProfile::getDefaultInput()` value
straight into a new `ExecutionStep` — meaning a suggested
`report.sales.generate` step carried the literal, unresolved string
`'{date:-7}'` as `start_date` instead of a real `Y-m-d` date, which then
failed that capability's own real input validation the moment the
suggested plan actually ran. Caught by `LearningServiceTest`'s own
assertion on the *resolved* input shape, not just the capability list.
Fixed by extracting `DeterministicPlanner`'s own token-resolution logic
(`{date:N}` / `{coupon_code}` / `{discount_percent}`) into a shared
`AgentProfileInputResolver` both `DeterministicPlanner` and
`LearningService` now depend on — one resolver, not two independently
drifting copies of the same token vocabulary.

## Reading the results

```bash
GET  /api/agents/memory/insights?agent_type=ceo
POST /api/agents/memory/suggest   {"goal": "...", "agent_type": "ceo"}
```

Both require the `agent.memory.read` permission and are also reachable as
MCP capabilities (`agent.memory.insights` / `agent.memory.suggest`) — the
same "reachable both via `/api/agents/*` and via MCP" shape every other
capability in this module already has.

`insights` aggregates over the tenant's own most recent 50 Executions for
one Agent persona (a bounded, recent window — not a full-table scan, the
same "prove the proportional behavior, not raw throughput" scope this
codebase's test suite always uses for a data-volume claim, §7.23):
`total_executions`, `average_duration`, `most_used_capabilities` (top 5,
from successful runs only), `success_rate`, and the 5 most recent goal
texts.

`suggest` is a pure preview — the same plan `ExecuteGoalAction` would
silently prefer over real planning, without actually running it. `null`
when nothing qualifies. The first real caller of `ExecutionPlanData`
(built in §7.26 for exactly this "preview my plan" shape, unused until
now).

## Known scope decisions

- **The keyword vocabulary is fixed and small** — `sales`/`revenue`/
  `inventory`/`customer`/`report`, deliberately not derived from each
  Agent persona's own `AgentProfile::planningRules()` keys, so a learned
  pattern doesn't silently stop matching if a profile's config changes
  later.
- **A pattern below 50% success is never suggested** — not named in the
  original request; added so a pattern learned once by accident (usage 1,
  one early failure) doesn't keep getting suggested forever.
- **No vector/semantic goal similarity** — `matches()` is a plain
  case-insensitive keyword substring check. A future embedding-based
  approach is the natural upgrade path this stage's own
  `ExecutionPatternRepositoryInterface` doesn't block.
- **Patterns are never pruned or expired** — a tenant's own pattern table
  only ever grows; no TTL/decay mechanism exists yet.
