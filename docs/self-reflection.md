# Self-Reflection & Reasoning

> Added in Phase 6, Stage 6 (§7.31 in `HANDOFF.md`) — the last Stage of
> Phase 6. This is the how-it-works guide; `docs/agent-orchestrator.md` is
> the module's own architecture reference and `HANDOFF.md` §7.31 carries
> the full build narrative. Read `docs/multi-agent-collaboration.md` first
> if you haven't — this stage reuses several of its own conventions
> (append-only log entities, config-driven fallback) without repeating the
> reasoning here.

## What it is

Every `agent.goal.execute` call (and its HTTP twin, `POST /api/agents/{agent_type}`)
now produces two `ReasoningTrace` rows:

1. **Pre-execution reasoning** (`think()`) — produced *before* a Plan is
   created. What does this goal need? What has worked on similar goals
   before? How confident am I? What would I do differently?
2. **Post-execution reflection** (`reflect()`) — produced *after* a real
   `ExecutionResult` exists. What actually happened? Was the approach
   right in hindsight? What's the one lesson worth keeping?

Both are real, persisted rows (`reasoning_traces` table) with a real
`confidence_score` (0.0-1.0) and a real, renderable `explanation` — not a
log line. Retrievable afterward via `agent.reasoning.trace`/`.explain` or
`GET /api/agents/reasoning/trace`/`/explain`.

## Reasoning is explanatory, not plan-changing

The single most important fact to understand before extending this
feature — the same weight `docs/multi-agent-collaboration.md`'s own
"Personas are not identities" section carries for delegation.

A `ReasoningTrace`'s own `decision`/`alternatives` document *why* a plan
was chosen, but **nothing reads them back into planning or execution**.
`PlannerInterface::createPlan()` and `PlanExecutorInterface::execute()`
are both completely unaware `ReasoningEngineInterface` exists — the
capability sequence that actually runs is decided exactly the same way it
always was (a learned `ExecutionPattern` first, then whichever
`PlannerInterface` is configured). Reasoning is a parallel, observational
record, not a second decision-maker — the same restraint §7.30 already
established for `agent.collaboration.delegate` (a real mechanism that
never automatically reroutes an in-flight plan). A future stage that wants
reasoning to actually steer planning (e.g. picking between the
`alternatives` it generated) is real, scoped, unstarted work — see
`HANDOFF.md` §9.

## A real LLM engine, with an honest, safe-by-default fallback

`ReasoningEngineInterface` has two implementations, mirroring
`PlannerInterface`/`LLMPlanner`/`DeterministicPlanner` (§7.28) field for
field:

- **`SimpleReasoningEngine`** — no LLM call. Reads this tenant's own
  `ExecutionPattern` history (`ExecutionPatternRepositoryInterface::findSimilarPatterns()`
  — the exact method `LearningService::suggestPlan()` itself already
  calls, not a new lookup) and derives an honest confidence from real
  numbers: a matched pattern's own `successRate()` when thinking, the real
  `ExecutionResult::successRate()` when reflecting.
- **`LLMReasoningEngine`** — asks a configured LLM provider to think/reflect
  in structured JSON (`LLMClientInterface::completeStructured()`, the same
  port `LLMPlanner` uses), and **falls back to `SimpleReasoningEngine`
  automatically** on any failure (network error, malformed response, a
  response missing a required field) — never a hard failure for the
  caller.

`config('agent-orchestrator.reasoning.type')` (`REASONING_TYPE`)
**defaults to `simple`, not `llm`** — the identical "safe default,
explicit opt-in for real network calls" reasoning `planner.type` already
established. This matters more here than it did for planning:
`LLMClientInterface` is bound unconditionally regardless of this setting
(it's shared with the planner), so leaving `reasoning.type` at `llm` by
default would make *every single goal execution* attempt a real, keyless
network call the moment reasoning was wired into `ExecuteGoalAction` — not
just the calls that explicitly opt into an LLM planner. `phpunit.xml`
pins `REASONING_TYPE=simple` explicitly, the same way it already pins
`PLANNER_TYPE=deterministic`, so the whole test suite never attempts one.

## How it fits into ExecuteGoalAction

```
Goal received
  -> AgentProfile loaded (now unconditional — see below)
  -> think() -> PreExecution ReasoningTrace (held in memory, no id yet)
  -> learned plan? else Planner.createPlan()
  -> PlanExecutor.execute()
  -> ExecutionMemoryRepositoryInterface.save() -> real execution id
  -> preReasoning.assignExecutionId(id)
  -> reflect() -> PostExecution ReasoningTrace (execution id already set)
  -> both traces persisted together
  -> GoalCompleted event
  -> ExecutionResultData (now carrying pre_reasoning/post_reasoning/explanation)
```

Two things worth calling out explicitly:

- **`AgentProfile` is now loaded unconditionally**, before the
  learned-plan check. Before this stage, a learned-plan hit skipped
  loading the profile entirely (it isn't needed once a plan is already
  known) — `think()` needs one regardless of which planning path
  eventually runs, so this is one extra
  `AgentProfileRepositoryInterface::findByType()` call on the learned-plan
  path where there was none before. A deliberate, small, additive
  widening — not a bug.
- **The pre-execution trace is held in memory, not persisted, until a
  real execution id exists.** `ExecutionResult` (the Domain Entity) never
  carries an id at all — the int id only exists once
  `ExecutionMemoryRepositoryInterface::save()` returns it. Rather than
  inventing a "save now, patch `execution_id` later" path,
  `ExecuteGoalAction` calls `ReasoningTrace::assignExecutionId()` (a
  one-time mutator, the same shape `AgentMessage`/`DelegationRequest`'s
  own `assignId()` already establishes) once the id is known, then
  persists both traces together, right before the `GoalCompleted` event.
  `ReasoningTraceRepositoryInterface::save()` refuses a trace with no
  `executionId()` assigned yet — a class invariant, not a suggestion.

## Example response

```json
POST /api/agents/ceo
{ "goal": "Increase sales by 15% this week" }
```

```json
{
  "id": 123,
  "goal": "Increase sales by 15% this week",
  "agent_type": "ceo",
  "steps": [ "..." ],
  "status": "completed",
  "summary": "Goal executed: 4 of 4 step(s) completed, in 0.42s.",
  "pre_reasoning": {
    "reasoning_type": "pre_execution",
    "thoughts": [
      "Found 2 similar past goal pattern(s) for this tenant, averaging 88% success.",
      "Proceeding with the [CEO Agent] persona's own planning rules for this goal."
    ],
    "alternatives": [],
    "confidence_score": 0.88,
    "decision": "Proceed with the ceo persona's planned capability sequence.",
    "explanation": "Deterministic reasoning (no LLM configured or the LLM call failed) — decision based on this tenant's own recorded execution history, not a generated narrative."
  },
  "post_reasoning": {
    "reasoning_type": "post_execution",
    "execution_id": 123,
    "thoughts": [
      "Execution finished with status [completed], 100% success rate.",
      "Succeeded: report.sales.generate, analytics.kpi.calculate, commerce.coupon.create, notification.message.send"
    ],
    "confidence_score": 1.0,
    "decision": "The planned approach worked; no change recommended for a similar future goal.",
    "explanation": "Deterministic reflection (no LLM configured or the LLM call failed) — based directly on the real execution outcome, not a generated narrative."
  },
  "explanation": "🤔 **Pre-Execution Reasoning**\n\n**Goal:** Increase sales by 15% this week\n..."
}
```

With `REASONING_TYPE=llm` and real credentials, `thoughts`/`decision`/
`explanation` read as a genuine generated narrative instead of the
deterministic sentences above — the wire shape is identical either way.

## Reading it back later

```
GET /api/agents/reasoning/trace?execution_id=123
GET /api/agents/reasoning/explain?execution_id=123
```

or the MCP equivalents, `agent.reasoning.trace`/`agent.reasoning.explain`
(permission `agent.reasoning.read` for both). `.../trace` returns both
traces' full structured data; `.../explain` renders whichever trace(s)
exist through `ExplanationGeneratorInterface`, joined with a `---`
separator when both are present. An execution with no recorded trace at
all (see below) is `ExecutionNotFoundException` — a real 404, not an
empty string.

## Known scope decisions

- **An execution that fails before `reflect()` ever runs leaves only a
  `PreExecution` trace behind** — an honest, documented gap, not a bug.
  `PlanExecutor` catches every ordinary per-step failure internally and
  always returns a real `ExecutionResult` (unchanged since §7.26), so this
  only happens if something genuinely uncaught interrupts
  `ExecuteGoalAction` between `think()` and `reflect()` (e.g. planning
  itself throws, wrapped in `GoalExecutionFailedException` before
  execution ever starts) — narrower than it sounds, but real.
  `ReasoningTraceRepositoryInterface::findByExecution()`'s own docblock
  says so explicitly.
- **No caller reads `alternatives` back for anything.** They're persisted
  and rendered in `explanation`, but nothing compares them, ranks them
  against what actually happened, or offers to re-run with a different
  one — a real, scoped future increment ("did the alternative I rejected
  turn out to be the better choice?").
- **`SimpleReasoningEngine`'s own confidence is a real number from real
  history, but a simple one** — an unweighted average of matched
  patterns' own `successRate()`s (thinking) or the plan's own
  `successRate()` (reflecting), never a calibrated probability. This is
  the reasoning-side equivalent of `PatternExtractor`'s own "plain keyword
  substring check, not semantic similarity" documented MVP simplification
  (§7.29/§8.80).
- **No Dashboard UI for Self-Reflection & Reasoning** — the same gap every
  Phase 6 Stage before this one has flagged for its own new surface
  (§8.69/§8.75/§8.84/§8.89) — `GetReasoningTraceAction`/`ExplainReasoningAction`
  are already shaped for a `/dashboard/agents` page the same way every
  other resource's own Controller reuses its Actions (HANDOFF §3 pattern
  #19); only the page itself is missing.
