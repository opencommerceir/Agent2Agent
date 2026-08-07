← [AI Agents: the Agent Orchestrator](12-agent-orchestrator.md) | Next: [Execution Memory and Multi-Agent Collaboration](14-execution-memory-and-multi-agent-collaboration.md) →

# 13. Phase 6 (Stages 2 and 3) — Agent Profiles and the LLM-Backed Planner

## Stage 2 — Agent Profiles and the CEO Agent

### The problem with the previous version

In the previous stage, `DeterministicPlanner` had each persona's rules (sales, support, finance) hardcoded directly in PHP — meaning adding a new persona meant changing Planner code. This stage fixes that: every persona is now a **configuration file**.

```
config/agents/ceo.php
config/agents/sales.php
config/agents/support.php
config/agents/finance.php
```

Each file contains:
- `planning_rules` — a list of rules; each rule has a keyword and a sequence of capabilities.
- `default_inputs` — default input values per capability (using special tokens, like `{date:-7}` or `{coupon_code}`).
- `permissions` — a **descriptive** (not enforced) list of permissions this persona typically needs.

`AgentProfile` (a new Domain entity) reads this array, and `DeterministicPlanner` now reads a profile's own `planning_rules` instead of hardcoded branches.

### A crucial point: `permissions` is descriptive only

This is a decision worth always remembering: the `permissions` list inside a profile **has no enforcement role at all** — it's documentation only. Real authorization always happens where it always has: inside `CapabilityToolInvoker`, exactly where every other capability in the platform is checked. This point becomes critical in the next file (multi-agent collaboration).

### Two real corrections to the request's own example config

1. `'start_date' => '-7 days'` was a valid relative PHP date string, but not the `Y-m-d` shape `report.sales.generate` actually expects. Replaced with an explicit `{date:N}` token that `DeterministicPlanner` resolves into a real date.
2. `'code' => 'AUTO_{date}'` could never become a valid `CouponCode` (which must start with `COUPON-`) — it would fail every single time. Replaced with a `{coupon_code}` token that generates a genuinely valid code.

### Another latent bug: `glob()` vs. `config()`

The request's own pseudocode used `glob()` (reading files directly off disk) to list every profile. This works in local development, but silently breaks in any real deployment running `php artisan config:cache` (a standard Laravel optimization) — a cached config repository no longer has the original file paths for `glob()` to find. This was caught before shipping: instead of `glob()`, `config('agents', [])` is used, which is config-cache-safe.

## Stage 3 — the LLM-backed Planner

### `LLMPlanner` — the second real `PlannerInterface` implementation

This is the first time a real large language model enters the project. Its architecture is exactly the Connector pattern we've seen several times already:

```
LLMClientInterface     ← the contract
OpenAIClient            ← a real implementation (Guzzle)
ClaudeClient             ← a real implementation (Guzzle)
```

### Automatic fallback — a critical safety rule

`LLMPlanner::createPlan()` wraps the entire "discover capabilities → build prompt → call the LLM → parse the response → build a plan" chain in one `try/catch`. Any failure — a network error, a malformed response, a missing field — takes the same path: log a warning, and let the already-injected `DeterministicPlanner` build the plan instead. Turning the LLM planner on never exposes the end user to a hard failure.

### A very important default: `PLANNER_TYPE=deterministic`

The request's own `.env.example` defaulted to `PLANNER_TYPE=llm`. This was **deliberately rejected**. Why? Because that would mean a fresh install, with no API key configured, would attempt a real, keyless network call the moment it received its first `/api/agents/*` request — unpredictable behavior (it might fail fast or hang, depending on the network). This follows the exact same "safe default for dev/test, real infrastructure only by explicit opt-in" pattern already seen with `WOOCOMMERCE_*` and `CACHE_STORE=database`. `phpunit.xml` also explicitly pins `PLANNER_TYPE=deterministic` so the entire test suite never makes a real network call.

### A real difference between the two providers

OpenAI and Claude both offer structured output, but differently: OpenAI's `response_format: json_object` (only guarantees valid JSON, not schema conformance) vs. Claude's own Tool Use mechanism (which actually validates the schema at the API level). This is a real capability difference between providers, respected in the code rather than assumed away.

### Tested without any real network call

No test in this codebase makes a real call to OpenAI/Claude (no real credentials exist in this dev environment) — tests either use a fake `LLMClientInterface`, or a real Guzzle client sitting behind a `MockHandler` (so the real HTTP code itself is tested, just with a simulated response).

## Summary table

| Stage | What was added | Key decision |
|---|---|---|
| Agent Profiles | `config/agents/*.php`, `AgentProfile` | `permissions` is descriptive only; enforcement is always checked separately |
| LLM Planner | `LLMPlanner`, `OpenAIClient`, `ClaudeClient` | Default `deterministic`; automatic fallback on any failure |

By the end of these two stages: 966 tests, 118 MCP capabilities. Now it's time to see how these agents can **learn from past runs** and **collaborate with each other**.

---
← [AI Agents: the Agent Orchestrator](12-agent-orchestrator.md) | Next: [Execution Memory and Multi-Agent Collaboration](14-execution-memory-and-multi-agent-collaboration.md) →
