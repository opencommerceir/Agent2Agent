# LLM-based Planner

> Added in Phase 6, Stage 3 (§7.28 in `HANDOFF.md`). This is the
> how-to-use guide; `docs/agent-orchestrator.md` is the module's own
> architecture reference and `HANDOFF.md` §7.28 carries the full build
> narrative.

## What it is

`LLMPlanner` is the second `PlannerInterface` implementation (alongside
Stage 1/2's `DeterministicPlanner`) — it asks a real LLM provider (OpenAI
or Claude) to plan a Goal against every capability the platform currently
has, instead of matching the Goal's text against a profile's own
hardcoded `planning_rules`. Enable it with one env var:

```bash
PLANNER_TYPE=llm
LLM_PROVIDER=openai        # or: claude
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4         # optional, this is the default
```

With `PLANNER_TYPE=deterministic` (the shipped default), none of the LLM
config matters — `LLMClientInterface` is never even resolved.

## How it plans

1. `LLMPlanner` calls Core's own `DiscoverCapabilitiesAction` — the exact
   same building block `GET /mcp/v1/capabilities` itself uses — to get
   every capability currently registered on the platform (name,
   description, `inputSchema`, `requiredPermissions`).
2. `PlanningPromptTemplate::forGoal()` builds a prompt from the Goal's own
   text, the calling `AgentProfile`'s own name/description/permissions,
   and that full capability list.
3. `LLMClientInterface::completeStructured()` sends it to the configured
   provider, asking for a JSON object matching a fixed schema
   (`{"steps": [{"capability": "...", "input": {...}, "priority"?: "..."}]}`).
4. The response is converted into a real `ExecutionPlan` — a step with no
   valid `capability` string, or a response with no `steps` array at all,
   is treated as a failure (see Fallback below), not silently dropped.
5. Execution proceeds exactly the same way it always has — `PlanExecutor`
   -> `CapabilityToolInvoker` doesn't know or care whether the plan it's
   running came from a keyword-matched config rule or an LLM. A
   capability name the LLM invents that doesn't actually exist fails that
   one step at execution time (`CapabilityNotFoundException`) the same
   way any other bad plan would — `LLMPlanner` does not pre-validate
   capability names against the registry itself before returning a plan.

## Fallback

Any failure anywhere in the sequence above — the HTTP call itself, a
malformed JSON body, a response missing the expected shape — is caught,
logged (`Log::warning('LLM planner failed', [...])` then
`Log::warning('Falling back to deterministic planner', [...])`), and
`LLMPlanner` returns whatever the injected fallback `PlannerInterface`
(a `DeterministicPlanner` in practice) produces instead — the *exact*
plan a caller would have gotten with `PLANNER_TYPE=deterministic`
outright. A caller never sees an error just because the LLM was
unreachable.

Set `PLANNER_FALLBACK_TO_DETERMINISTIC=false` to disable this (the
failure propagates instead, mapped to `INTERNAL_ERROR`/500) — useful
while debugging the LLM integration itself, where a silent substitution
would hide the real problem.

## Providers

| `LLM_PROVIDER` | Client | Structured output mechanism |
|---|---|---|
| `openai` (default) | `OpenAIClient` | Chat Completions, `response_format: json_object` + the schema embedded in the prompt text |
| `claude` | `ClaudeClient` | Messages API, a single forced tool call (`tool_choice`) whose `input_schema` is the caller's own schema — validated by the API itself before it's returned |

Both are real implementations (Guzzle-backed, matching
`WooCommerceClient`'s own "real client + injectable `ClientInterface`"
shape) — **no live credentials exist in this dev environment**, the same
"needs real credentials to test honestly" situation every external
Connector in this codebase is already in. Every test injects a fake
`LLMClientInterface` (or a Guzzle `MockHandler`-backed real client)
instead of calling a real API — see `docs/agent-orchestrator.md`'s own
testing notes.

## Adding a third provider

1. Implement `LLMClientInterface` (`Domain/Services/LLMClientInterface.php`)
   — `complete()`/`completeStructured()`.
2. Add its config block under `config/agent-orchestrator.php`'s own `llm`
   key.
3. Add a `match` arm in `AgentOrchestratorServiceProvider::register()`'s
   `LLMClientInterface` binding closure.

Nothing about `LLMPlanner`, `PlanningPromptTemplate`, or anything above
`PlannerInterface` needs to change.

## Known scope decisions

- **No pre-validation of LLM-returned capability names against the real
  registry** — a hallucinated capability name simply fails that one step
  at execution time, the same as any other bad plan. Pre-validating would
  mean re-implementing a check `CapabilityToolInvoker` already performs.
- **No per-call record of "was this specific plan built by the LLM or the
  fallback"** — `supportsLLM()` is a static capability flag on the
  Planner (`true` for `LLMPlanner` even on a call that silently fell
  back), not a per-`ExecutionResult` field. Log lines capture the real
  per-call outcome; the response/persisted `Execution` row does not (yet)
  — see `HANDOFF.md` §8.
- **The full capability list is sent on every single planning call, with
  no caching/pruning** — for a platform with 118 capabilities this is a
  real, non-trivial prompt size; not addressed this stage, since the
  request's own end-to-end scenario explicitly expected all of them
  included.
