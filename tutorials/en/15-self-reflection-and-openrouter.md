← [Execution Memory and Multi-Agent Collaboration](14-execution-memory-and-multi-agent-collaboration.md) | Next: [The Showcase Demo](16-showcase-demo.md) →

# 15. Phase 6 (Stage 6, Final) and OpenRouter — Self-Reflection, Reasoning, and Free LLM Access

## Stage 6 — Self-reflection and reasoning

This is the final stage of Phase 6. From here on, every `agent.goal.execute` call follows this flow:

```
think()   ← before building a plan: "why am I doing this? how confident am I?"
   ↓
[the plan is built and executed, unchanged]
   ↓
reflect() ← after execution: "how did it go? how close to the goal did I get?"
```

Both steps produce a `ReasoningTrace` — a few short explanatory sentences (`thoughts`), up to 3 alternative options (pre-execution only), a confidence score between 0 and 1 (`ConfidenceScore`), and a human-readable explanation.

## The biggest principle of this stage: reasoning explains, it never changes the plan

This might be the single most important sentence of all of Phase 6: **neither `PlannerInterface` nor `PlanExecutorInterface` ever reads anything from a `ReasoningTrace`.** The actual sequence of capabilities that runs is decided exactly the way it always has been (first a learned pattern, then whichever Planner is configured). This is a deliberate decision, mirroring the same restraint seen in multi-agent collaboration (delegation never bypasses the plan) — here it's applied one more time: **reflection doesn't bypass the plan either.**

## Two implementations, exactly like the Planner pattern

```
SimpleReasoningEngine    ← no LLM call. Derives confidence from real execution-pattern history
LLMReasoningEngine        ← asks an LLM provider for structured JSON; falls back automatically
                             to SimpleReasoningEngine on any failure
```

## A critical technical decision: why `reasoning.type` also defaults to `simple`

`LLMClientInterface` is bound **unconditionally** in the module's service provider — independent of which Planner is configured. If reasoning had defaulted to `llm`, it wouldn't just be one opt-in path (choosing an LLM Planner) risking a real network call — it would mean **every single call** to `agent.goal.execute` in the entire platform would attempt one, the moment this stage was wired in. So `reasoning.type` got the exact same safe default as `planner.type`: `simple`. This was confirmed by re-running the entire pre-existing 1031-test suite unchanged — zero regressions, zero new network attempts.

## Three more real corrections to the request

1. The request's own pseudocode invented dependencies for `ExecuteGoalAction` that **didn't actually exist** — instead of rewriting the whole constructor, only the 3 genuinely new dependencies (`ReasoningEngineInterface`, `ReasoningTraceRepositoryInterface`, `ExplanationGeneratorInterface`) were added to the existing list.
2. `ExecutionResult` (the Domain entity) has no ID at all — the real ID only exists once it's saved. Solution: the pre-execution trace is held in memory until the real execution ID is known, then locked in with a one-time setter (`assignExecutionId()`) — the same pattern `AgentMessage`/`DelegationRequest` already used for their own IDs.
3. The request assumed a method, `getSimilarExecutions()`, that didn't exist on `LearningServiceInterface`. The real equivalent (`ExecutionPatternRepositoryInterface::findSimilarPatterns()`) already existed and was used directly.

## Two new capabilities

```
agent.reasoning.trace     ← retrieve both traces (pre/post execution) for a past run
agent.reasoning.explain    ← turn that trace into a human-readable explanation
```

By the end of this stage: **1067 tests, 124 capabilities — and Phase 6 (AI Agent Orchestration) is now fully complete, all six stages.**

---

## OpenRouter integration — after Phase 6 finished

This is no longer a formal "stage" — it's a small, self-contained piece of prep work for the live demo, similar to the Tech Debt Sprint that ran between two Phase 4 stages.

### Why OpenRouter?

Up to this point, `LLMClientInterface` only had two implementations (OpenAI and Claude), both requiring a real, paid API key. **OpenRouter** puts a single API in front of 100+ models from many providers — and several are **completely free**. This means a live demo could use a real LLM at zero cost.

A third implementation was added: `OpenRouterClient` — structurally almost identical to `OpenAIClient` (since OpenRouter's chat endpoint is OpenAI-compatible); the real difference is just a configurable `$baseUrl` and two recommended attribution headers (`HTTP-Referer`/`X-Title`).

Default model: `meta-llama/llama-3.1-405b-instruct:free` — meaning it works even with a key that has zero balance.

### A correct decision that was confirmed, not built

The request suggested building a new `SimpleLLMClient` class to fall back to whenever the API key is empty. This was **deliberately not built**. Why? Because the existing convention already covers this: an empty or invalid key still constructs a real client, which only fails **at the actual moment it's called** — and that failure is already caught one layer up (`LLMPlanner`→`DeterministicPlanner`, `LLMReasoningEngine`→`SimpleReasoningEngine`). A second, redundant safety net would have duplicated a guarantee that already exists.

By the end of this prep work: 1078 tests, 124 capabilities (unchanged — this is a new provider, not a new capability).

## Summary

| Topic | Key point |
|---|---|
| Self-reflection | Explains, never changes the plan |
| `reasoning.type` default | `simple`, for the same security reason as `planner.type` |
| OpenRouter | Free access to a real LLM; no extra fallback client needed |

With this prep work complete, everything was ready for a real, demonstrable live demo. The next file opens exactly that demo.

---
← [Execution Memory and Multi-Agent Collaboration](14-execution-memory-and-multi-agent-collaboration.md) | Next: [The Showcase Demo](16-showcase-demo.md) →
