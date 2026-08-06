# Agent Orchestrator Module

> Added in Phase 6, Stage 1 (§7.26 in `HANDOFF.md`), extended in Stage 2
> — Agent Profiles + the CEO Agent (§7.27) — Stage 3 — an LLM-based
> Planner (§7.28) — Stage 4 — Execution Memory & Learning (§7.29) — Stage
> 5 — Multi-Agent Collaboration (§7.30) — and Stage 6, the last Stage of
> Phase 6 — Self-Reflection & Reasoning (§7.31). This document is the
> module's own reference; `HANDOFF.md` §7.26/§7.27/§7.28/§7.29/§7.30/§7.31
> carry the full narrative of what was built, what was corrected from the
> original requests, and why. See `docs/agent-profiles.md` for the
> how-to-add-a-new-Agent guide, `docs/llm-planner.md` for the
> how-to-use-the-LLM-planner guide, `docs/execution-memory.md` for the
> how-learning-works guide, `docs/multi-agent-collaboration.md` for the
> how-delegation-works guide (**read that one before assuming "delegate to
> another Agent" means what it sounds like** — personas are not identities
> in this codebase, a real architectural correction from Stage 5's own
> request), and `docs/self-reflection.md` for the how-reasoning-works guide
> (**read that one before assuming reasoning changes which plan runs** —
> it's explanatory only, the same restraint Stage 5's own delegation
> mechanism already established). Phase 6 itself finished at Stage 6;
> `docs/openrouter-integration.md` (Showcase prep, §7.32 — after Phase 6)
> documents a third `LLMClientInterface` provider added afterward, not a
> new Stage.

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
| AgentProfile | `Domain\Entities\AgentProfile` | The config-driven definition of one Agent persona — `planning_rules` (goal-keyword → capabilities) + `default_inputs` (capability → raw input), read from `config/agents/{type}.php`. See `docs/agent-profiles.md`. |
| Profile repository | `Domain\Repositories\AgentProfileRepositoryInterface` | Loads an `AgentProfile` by type. `ConfigBasedAgentProfileRepository` is the one implementation — reads via Laravel's own `config()`, never the filesystem directly. |
| Planner | `Domain\Services\PlannerInterface` | Turns a Goal + the calling Agent's own `AgentProfile` into an `ExecutionPlan`. Two implementations: `DeterministicPlanner` (config-driven rule lookups, resolves a small set of template tokens) and `LLMPlanner` (asks a real LLM provider, falls back to `DeterministicPlanner` on any failure) — chosen by `config('agent-orchestrator.planner.type')`. See `docs/llm-planner.md`. |
| LLM client | `Domain\Services\LLMClientInterface` | A thin port over one LLM provider's own API. `OpenAIClient`/`ClaudeClient`/`OpenRouterClient` (the last one added in Showcase prep, §7.32 — 100+ models, several free) are the three real implementations, chosen by `config('agent-orchestrator.llm.provider')`. See `docs/openrouter-integration.md`. |
| ExecutionPlan / ExecutionStep | `Domain\Entities\{ExecutionPlan,ExecutionStep}` | The plan itself, and each individual planned capability call (`capability` + `input` + `priority`), with mutable `status`/`output`/`error` as it runs. |
| Executor | `Domain\Services\PlanExecutorInterface` | Runs every step of a plan, in order, never aborting on a single step's failure. `PlanExecutor` is the one implementation. |
| ToolInvoker | `Domain\Services\ToolInvokerInterface` | Invokes exactly one capability by name. `CapabilityToolInvoker` is the one implementation — backed entirely by Core's own `GetCapabilityAction` / `CheckPermissionAction` / `CapabilityExecutionService`, the same three building blocks `AbstractMCPGatewayController` itself uses. |
| ExecutionResult | `Domain\Entities\ExecutionResult` | The finished outcome of one plan run — every step's final state, a derived `status` (`completed`/`partial`/`failed`/`empty`), and a generic completion summary. `isSuccessful()`/`successfulCapabilities()`/`failedCapabilities()` (Stage 4, §7.29) are what Pattern Extraction reads. |
| Memory | `Domain\Repositories\ExecutionMemoryRepositoryInterface` | Persists every finished `ExecutionResult` (and its steps) so it can be listed/retrieved later. `EloquentExecutionMemoryRepository` is the one implementation — two real tables (`agent_executions`/`agent_execution_steps`), not an in-process array, so history survives across requests. |
| ExecutionPattern | `Domain\Entities\ExecutionPattern` | A learned, tenant-scoped "goals mentioning these keywords tend to need these capabilities" shorthand (Stage 4, §7.29) — `Domain\Repositories\ExecutionPatternRepositoryInterface` persists it (`execution_patterns` table); `EloquentExecutionPatternRepository` is the one implementation. |
| PatternExtractor | `Domain\Services\PatternExtractorInterface` | Classifies a Goal's own text into a keyword pattern and turns a successful `ExecutionResult` into a fresh `ExecutionPattern`. `PatternExtractor` is the one implementation — a pure Domain calculation, no Repository dependency. |
| LearningService | `Domain\Services\LearningServiceInterface` | Reads learned patterns back into a real `ExecutionPlan` (`suggestPlan()`) and aggregates recent execution history (`getInsights()`). `LearningService` is the one implementation. See `docs/execution-memory.md`. |
| AgentMessage | `Domain\Entities\AgentMessage` | A durable, append-only log entry of one persona-to-persona communication (Stage 5, §7.30) — `Domain\Repositories\AgentMessageRepositoryInterface` persists it (`agent_messages` table). |
| DelegationRequest | `Domain\Entities\DelegationRequest` | One `agent.collaboration.delegate` call's own work-tracking state machine (Stage 5, §7.30) — `Pending` -> `InProgress` -> exactly one of `Completed`/`Failed`/`Timeout`. `Domain\Repositories\DelegationRequestRepositoryInterface` persists it (`delegation_requests` table). |
| AgentCommunication | `Domain\Services\AgentCommunicationInterface` | Sends/reads `AgentMessage`s and runs a delegation by re-invoking the *unmodified* `ExecuteGoalAction` under the caller's own real `AuthContext`. `AgentCommunicationService` is the one implementation — see `docs/multi-agent-collaboration.md`. |
| ResultAggregator | `Domain\Services\ResultAggregatorInterface` | Combines several `ExecutionResult`s into one, or picks the best of several conflicting ones by `successRate()`. `ResultAggregator` is the one implementation — built and tested, no automatic caller yet. |
| ReasoningTrace | `Domain\Entities\ReasoningTrace` | One "thought" produced before (`pre_execution`) or after (`post_execution`) a Goal executes (Stage 6, §7.31) — `thoughts`, `alternatives`, a `ConfidenceScore`, a `decision`, and a human-readable `explanation`. `Domain\Repositories\ReasoningTraceRepositoryInterface` persists it, append-only (`reasoning_traces` table). |
| ReasoningEngine | `Domain\Services\ReasoningEngineInterface` | `think()`s before planning, `reflect()`s after execution. Two implementations, mirroring Planner: `SimpleReasoningEngine` (no LLM — derives confidence from real `ExecutionPattern`/`ExecutionResult` numbers) and `LLMReasoningEngine` (asks a real LLM provider, falls back to `SimpleReasoningEngine` on any failure) — chosen by `config('agent-orchestrator.reasoning.type')`. See `docs/self-reflection.md`. |
| ExplanationGenerator | `Domain\Services\ExplanationGeneratorInterface` | Renders one `ReasoningTrace` into a human-readable explanation string — pure formatting, no LLM call. `ExplanationGenerator` is the one implementation. |

## Execution flow

1. A caller sends a Goal — either `POST /api/agents/{agent_type}` (a human-
   or system-facing HTTP client) or the `agent.goal.execute` MCP capability
   (another Agent, or a future multi-agent orchestration one level up) —
   both reuse the exact same `ExecuteGoalAction`.
2. `ExecuteGoalAction` builds a `Goal`, dispatches `GoalReceived`, and loads
   the calling `AgentType`'s own `AgentProfile` (`AgentProfileNotFoundException`
   — 404 — if no `config/agents/{type}.php` exists for it) —
   **unconditionally, since Stage 6 (§7.31), even on a path that ends up
   using a learned plan**, because the next step needs one.
3. `ReasoningEngineInterface::think()` (Stage 6, §7.31) produces a
   `ReasoningTrace` — what this goal needs, what's worked before, how
   confident the Agent is, held in memory (no execution id exists yet).
   **This is explanatory only — nothing below reads it back.**
4. `ExecuteGoalAction` asks `LearningServiceInterface::suggestPlan()`
   (Stage 4, §7.29) whether this tenant has already solved a similar goal
   successfully before. If so, that learned plan is used and **the
   Planner is never consulted**. Otherwise it asks the bound
   `PlannerInterface` for an `ExecutionPlan`.
5. `PlanExecutor` runs each `ExecutionStep` through `CapabilityToolInvoker`,
   in order. Each step is authenticated as the calling Agent, permission-
   checked against the target capability's own `requiredPermissions`, input-
   validated, and executed — identically to a direct `/mcp/v1/execute`
   call. **A failed step is recorded and execution continues** — it never
   aborts the rest of the plan.
6. The finished `ExecutionResult` is persisted via
   `ExecutionMemoryRepositoryInterface` (this is where a real execution id
   first exists) and `GoalCompleted` is dispatched — `LearnFromExecutionListener`
   (Stage 4, §7.29) reacts to it, creating or reinforcing an `ExecutionPattern`
   for next time. See `docs/execution-memory.md`.
7. `ReasoningEngineInterface::reflect()` (Stage 6, §7.31) produces a second
   `ReasoningTrace` from the real outcome. Both traces are persisted
   together (the pre-execution one now has its execution id assigned) and
   an `ExplanationGeneratorInterface::generate()` call renders a
   human-readable explanation. See `docs/self-reflection.md`.
8. The caller gets back the full step-by-step result, a derived `status`,
   a plain-language `summary`, and both reasoning traces + the rendered
   explanation.

## Planner

**Today: `DeterministicPlanner`** — reads the calling Agent's own
`AgentProfile` (Stage 2, §7.27) instead of Stage 1's own hardcoded
per-agent-type keyword branches. `AgentProfile::getCapabilitiesForGoal()`
matches the Goal's own text against that profile's `planning_rules`
(first keyword found, in config declaration order, wins — falls back to
the profile's required `default` rule); `DeterministicPlanner` then
resolves each matched capability's own `default_inputs` template into
real values (see `docs/agent-profiles.md`'s own token table) and builds
the `ExecutionStep`s.

Four profiles ship today (`config/agents/{ceo,sales,support,finance}.php`)
— see `docs/agent-profiles.md` for the exact rules each one declares and
how to add a fifth.

**Future: an LLM-based planner** — a drop-in replacement behind
`PlannerInterface`. Nothing above the Interface (`PlanExecutor`,
`ExecuteGoalAction`, either HTTP/MCP surface) needs to change; only the
`PlannerInterface` binding in `AgentOrchestratorServiceProvider::register()`
would be swapped. It would presumably still take the same `AgentProfile`
as context.

## Supported Agents

`AgentType` (`ceo`/`sales`/`support`/`finance`) is recorded on every Goal
and every persisted Execution, and is what `AgentProfileRepositoryInterface`
looks a profile up by — but `DeterministicPlanner` itself still keys off
the Goal's own *text*, not the type, when matching a profile's planning
rules (a CEO Agent and a Sales Agent asking the identical goal text
against their own, different profiles can still get different plans,
since each profile's own rules differ — see `CEOAgentTest`/the Sales
profile in `docs/agent-profiles.md` — but within one profile, `AgentType`
itself plays no role in *which* rule matches). See "Known scope
decisions" below.

## Agent Profile API

```
GET /api/agents/profiles              # every configured profile
GET /api/agents/profiles/{agent_type} # one profile's own rules/inputs/permissions
```

Also reachable over MCP: `agent.profile.list` / `agent.profile.get`
(permission `agent.profiles.read` for both). An unknown `agent_type`
raises `AgentProfileNotFoundException` (404) — the same mechanism as
every other module's own `*NotFoundException`.

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
    { "capability": "report.sales.generate", "input": {"start_date": "2026-07-29", "end_date": "2026-08-05"}, "priority": "medium", "status": "completed", "output": {"report": {"...": "..."}}, "error": null },
    { "capability": "analytics.kpi.calculate", "input": {"kpi_type": "revenue", "time_period": "weekly", "...": "..."}, "priority": "medium", "status": "completed", "output": {"...": "..."}, "error": null },
    { "capability": "commerce.coupon.create", "input": {"code": "COUPON-A1B2C", "discount_type": "percentage", "discount_value": 15}, "priority": "medium", "status": "completed", "output": {"coupon": {"...": "..."}}, "error": null },
    { "capability": "notification.message.send", "input": {"type": "promotion_announcement", "channel": "email", "...": "..."}, "priority": "medium", "status": "completed", "output": {"...": "..."}, "error": null }
  ],
  "summary": "Goal executed: 4 of 4 step(s) completed, in 0.18s.",
  "status": "completed",
  "execution_time": 0.18
}
```

(Stage 1's own worked example showed 5 steps, with `analytics.kpi.calculate`
called twice for two different `KPIType`s — Stage 2's config-driven CEO
profile calls each matched capability exactly once per its own
`planning_rules` list, 4 steps for the `sales` rule. `priority` is now
always `medium`, since `DeterministicPlanner` no longer hand-assigns a
per-step priority the way its Stage 1 hardcoded branches did — a config-driven
`planning_rules` list is just an ordered array of capability names today,
with no per-rule priority concept yet; see `docs/agent-profiles.md`.)

```
GET /api/agents/executions              # this tenant's own past runs (optional ?agent_type=&status=&limit=)
GET /api/agents/executions/{id}         # one past run by id
```

The identical 3 operations are also reachable over MCP, for a caller that
is itself an Agent: `agent.goal.execute` / `agent.execution.get` /
`agent.execution.list` (permissions `agent.goals.execute` /
`agent.executions.read`) — plus `agent.profile.get`/`agent.profile.list`
(Stage 2), mirroring the `/api/agents/profiles*` HTTP routes above.

```
GET  /api/agents/memory/insights?agent_type=ceo   # aggregate stats over recent history
POST /api/agents/memory/suggest                   # preview the learned plan for a goal, or null
```

Also reachable over MCP: `agent.memory.insights`/`agent.memory.suggest`
(permission `agent.memory.read` for both, Stage 4, §7.29). There is
deliberately no `agent.memory.history` — it would be functionally
identical to `agent.execution.list`/`GET /api/agents/executions` above.
See `docs/execution-memory.md`.

```
POST /mcp/v1/execute { "capability": "agent.collaboration.delegate",
  "input": { "from_agent": "ceo", "to_agent": "sales", "task": "...", "priority": 8 } }
POST /mcp/v1/execute { "capability": "agent.collaboration.messages",
  "input": { "agent_type": "sales" } }
```

MCP-only this stage (Stage 5, §7.30) — no dedicated `/api/agents/collaboration/*`
HTTP route was requested. `agent.collaboration.delegate` (permission
`agent.collaboration.delegate`) re-invokes the *unmodified* `ExecuteGoalAction`
for a different persona, under the *same* real `AuthContext` the caller
already has — delegating does not grant a new real permission, see
`docs/multi-agent-collaboration.md`'s own "Personas are not identities"
section before assuming otherwise. `agent.collaboration.messages`
(permission `agent.collaboration.read`) lists the tenant's own
persona-to-persona communication log.

```
GET /api/agents/reasoning/trace?execution_id=123     # both traces' full structured data
GET /api/agents/reasoning/explain?execution_id=123   # a rendered, human-readable explanation
```

Also reachable over MCP: `agent.reasoning.trace`/`agent.reasoning.explain`
(permission `agent.reasoning.read` for both, Stage 6, §7.31). Every
`agent.goal.execute`/`POST /api/agents/{agent_type}` response also now
carries `pre_reasoning`/`post_reasoning`/`explanation` inline — these two
endpoints are for reading them back *afterward*, by execution id. See
`docs/self-reflection.md`.

Every exception either surface can raise — a missing/invalid bearer token,
a missing permission, an empty goal, an unknown execution id, an unknown
agent/profile type (`AgentProfileNotFoundException`) — is mapped to the
correct HTTP status by Core's own `MCPExceptionHandler` (extended in
Stage 1 to also cover `api/agents/*`, not a second, parallel error
mapper). See that class's own docblock.

## Known scope decisions (read before extending this module)

These are deliberate, documented departures from a literal reading of the
original requests — see `HANDOFF.md` §7.26 (Stage 1) / §7.27 (Stage 2)
for the full reasoning behind each:

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
- **`DeterministicPlanner` keys off the Goal's own text against a profile's
  own `planning_rules`, not `AgentType` directly.** Both requests' own
  pseudocode did the same (`str_contains($goal->text, 'sales')`); Stage 2
  moved the *rules themselves* into config (one profile per `AgentType`),
  but matching still happens by keyword within whichever profile was
  already selected by type — `AgentType` chooses *which profile*, not
  *which rule inside it*. A future LLM-based planner is the natural place
  for `AgentType` (and a profile's own `description`) to shape the plan
  more directly.
- **Stage 2's `AgentProfile::fromConfig()` requires the type argument
  separately from the config array** — the request's own literal
  `fromConfig(array $config)` signature had nowhere to read the type from,
  since `config/agents/ceo.php` (per the request's own example) never
  embeds its own type as a key; the type is implied by the filename, the
  same convention Laravel's own recursive config loading already uses.
  `AgentProfile::fromConfig(AgentType $type, array $config)` reads it from
  the caller instead.
- **`ConfigBasedAgentProfileRepository::listAll()` reads `config('agents')`,
  not `glob(config_path('agents/*.php'))`** as the request's own literal
  implementation did — `glob()` reads the filesystem directly, which
  silently breaks under `php artisan config:cache` (a cached config
  repository has no original file paths left to glob); `config()` reads
  through Laravel's own already-cache-aware repository instead. Found and
  fixed during this stage, not a pre-existing bug.
- **`config/agents/support.php`/`finance.php` were added even though only
  `ceo.php`/`sales.php` were requested this stage** — required by this
  stage's own explicit "backward compatible" rule: Stage 1's own hardcoded
  `support`/`finance` keyword rules would otherwise 404
  (`AgentProfileNotFoundException`) the instant `DeterministicPlanner`
  switched to being profile-driven, since no config existed for either
  type yet. Both migrate Stage 1's own hardcoded plan for that type
  verbatim into the new config shape.
- **A profile's own `permissions` array is descriptive metadata only,
  never a second enforcement layer** — see `docs/agent-profiles.md`'s own
  "What `permissions` does NOT do" section for the full reasoning; real
  enforcement stays exactly where Stage 1 put it, inside
  `CapabilityToolInvoker`, per planned step.
- **Every `ExecutionStep` a `DeterministicPlanner` produces now carries
  `Priority::Medium`**, not the mixed High/Medium/Low Stage 1's own
  hardcoded branches hand-assigned — a config-driven `planning_rules`
  list is just an ordered array of capability names, with no per-entry
  priority concept yet (a real, honest simplification, not an oversight;
  see `HANDOFF.md` §8).
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
- **`LLMPlanner` uses Core's own `DiscoverCapabilitiesAction`, not a
  "CapabilityRegistry" class** — no such class exists anywhere in this
  codebase; `DiscoverCapabilitiesAction` is the real, already-existing
  building block `GET /mcp/v1/capabilities` itself uses.
- **`config/agent-orchestrator.php`'s own `planner.type` defaults to
  `deterministic`, not `llm`** — defaulting a fresh environment with no
  real API key to attempting real network calls on every goal isn't a
  safe default; see `docs/llm-planner.md`.
- **No pre-validation of LLM-returned capability names, no per-call
  "which planner actually built this plan" record** — see
  `docs/llm-planner.md`'s own "Known scope decisions."
- **Stage 4's own "Execution Memory Storage" request is served entirely by
  the *existing* `ExecutionMemoryRepositoryInterface` (Stage 1)** — not a
  new, parallel `ExecutionMemory` entity/table, and `agent.memory.history`
  was dropped as a duplicate of `agent.execution.list`. Confirmed with the
  user before writing any code — see `docs/execution-memory.md`'s own "Why
  no new ExecutionMemory entity" section for the full reasoning.
- **A pattern's success rate can fall, not just rise** — a real correction
  from the request's own pseudocode, which only ever extracted a pattern
  on success and never revisited one on a later failure. See
  `docs/execution-memory.md`'s own "How Pattern Extraction works."
- **`config/agents/*.php` profiles were not updated to list
  `agent.memory.read` in their own `permissions` array** — that array is
  descriptive metadata only, never a second enforcement layer (see
  `docs/agent-profiles.md`'s own "What `permissions` does NOT do"), so
  omitting it doesn't block anything; it's simply not yet reflected there.
- **"Delegate to another Agent" does not mean "run as a different
  identity with different permissions"** — a real, load-bearing
  correction from Stage 5's own request (§7.30), confirmed with the user
  before writing any code: there is no separate, permission-bearing
  identity per persona in this codebase (`AgentType` is a per-call
  planning classification, not an Agent identity). See
  `docs/multi-agent-collaboration.md`'s own "Personas are not identities"
  section for the full reasoning — read it before touching this feature.
- **No automatic mid-plan delegation** — the request's own
  `ExecuteGoalAction::requiresDelegation()`/`executeWithDelegation()`
  design relied on the identity assumption above and cannot work as
  specified; `agent.collaboration.delegate` is an ordinary MCP capability
  instead, reachable explicitly, never a special execution branch inside
  `ExecuteGoalAction` (which is completely unmodified by Stage 5). See
  `docs/multi-agent-collaboration.md`.
- **`DelegationRequest.status` tracks the delegation mechanism, not the
  delegated task's own business outcome** — `Completed` even when the
  nested `ExecutionResultData.status` is `partial`/`failed`. See
  `docs/multi-agent-collaboration.md`'s own "Known scope decisions."
- **Reasoning is explanatory, never plan-changing** (Stage 6, §7.31) —
  neither `PlannerInterface` nor `PlanExecutorInterface` reads anything a
  `ReasoningTrace` produces; the capability sequence that runs is decided
  exactly the same way it always was. The same restraint this module
  already applied to delegation (no automatic mid-plan rerouting) applied
  a second time, to reasoning. See `docs/self-reflection.md`.
- **`config('agent-orchestrator.reasoning.type')` defaults to `simple`,
  not `llm`** — the identical safe-default reasoning `planner.type`
  already established (§7.28), more load-bearing here since
  `LLMClientInterface` is shared/unconditional: leaving reasoning at `llm`
  by default would make *every* goal execution attempt a real network
  call, not just the ones that opt into an LLM planner. See
  `docs/self-reflection.md`.
- **`AgentProfile` is now loaded unconditionally in `ExecuteGoalAction`**,
  even on the learned-plan path where it previously wasn't needed —
  `think()` needs one regardless of which planning path eventually runs.
  A small, deliberate, additive widening (one extra
  `AgentProfileRepositoryInterface::findByType()` call on that path). See
  `docs/self-reflection.md`.

## Future Roadmap

- Recursive planning (a step's own output feeding a later step's input)
- ~~Self-reflection (recording what an Agent thought before/after
  executing a Goal)~~ **Delivered in Phase 6, Stage 6 (§7.31)** —
  `think()`/`reflect()` are real, LLM-backed (with a real deterministic
  fallback), and persisted. What's still genuinely open, narrower than
  the original roadmap line: the Orchestrator *revising a plan mid-run*
  based on a step's own result — today's reasoning is recorded and
  explanatory, never fed back into `PlanExecutor`. See
  `docs/self-reflection.md`'s own "Reasoning is explanatory, not
  plan-changing."
- ~~Multi-agent collaboration (one Agent's Goal spawning sub-Goals for
  another)~~ **Superseded in Phase 6, Stage 5 (§7.30)** — reshaped around
  this codebase's real identity model (see "Known scope decisions"
  above); `agent.collaboration.delegate` is real and MCP-reachable today.
  A genuinely automatic version (a plan step that decides *for itself* to
  delegate, or true multi-persona parallel delegation) is still open —
  see `docs/multi-agent-collaboration.md`'s own "Known scope decisions."
- A vector database for long-term, semantic execution memory — today's
  `ExecutionMemoryRepositoryInterface` is a simple relational log, chosen
  to already fit this future without implying it exists yet.
  `ExecutionPattern` matching (Stage 4, §7.29) is still a plain keyword
  substring check, not embedding-based similarity — the natural next step
  once this exists.
- Pattern pruning/decay — a tenant's own `execution_patterns` rows never
  expire or get removed today (Stage 4, §7.29)
- A real permission-sync check between `config/agents/*.php`'s own
  `permissions` list and the new `agent.memory.read` capabilities (Stage
  4, §7.29 didn't add it to any profile's own list — see "Known scope
  decisions")
- A database-backed `AgentProfileRepositoryInterface` implementation,
  letting an operator edit a profile without a deployment — a drop-in
  replacement behind the same Interface `ConfigBasedAgentProfileRepository`
  implements today
- Per-rule `priority` in `planning_rules` (today every step is
  `Priority::Medium`, see "Known scope decisions" above)
- A real permission-sync check between a profile's own `permissions` list
  and what its `planning_rules` actually call (today purely descriptive,
  can drift — see `docs/agent-profiles.md`)
- A Dashboard page under `/dashboard/agents` (every other Phase 4/5
  resource got one; this module didn't request one)
- A third `LLMClientInterface` provider (see `docs/llm-planner.md`'s own
  "Adding a third provider")
- Capability-list caching/pruning for `LLMPlanner`'s own prompt (today
  sends all 122, uncached, on every planning call)
- Real async delegation — a queued Job another process later picks up,
  the natural trigger for `MessageStatus::Pending`/`Received` (both
  modeled, unreached this stage, §7.30) and for `DelegationPriority`
  actually reordering multiple pending delegations
- Cycle detection beyond "can't delegate to yourself" (§7.30) — a longer
  delegation cycle (A -> B -> A) is not detected yet
- A `ResultAggregatorInterface` caller — today built and tested with none;
  the natural one is delegating to *multiple* personas at once (§7.30)
- Feeding `ReasoningTrace.alternatives` back into planning — e.g. letting
  a caller ask "run with alternative #2 instead" (§7.31, today purely
  recorded/rendered)
- A Dashboard page under `/dashboard/agents` covering reasoning traces too
  (§7.31, same gap every Phase 6 Stage's own new surface has flagged)
- Semantic/embedding-based confidence instead of `SimpleReasoningEngine`'s
  own plain average of matched patterns' success rates (§7.31)
