# OpenRouter Integration

> Added in Showcase prep, §7.32 in `HANDOFF.md` (after Phase 6 finished).
> This is the how-to-use guide; `docs/llm-planner.md` and
> `docs/self-reflection.md` are the how-it-works guides for the two
> `LLMClientInterface` consumers this provider plugs into — read those
> first if you haven't. `HANDOFF.md` §7.32 carries the full build
> narrative, including the one thing this stage deliberately did *not*
> build (a `SimpleLLMClient` fallback for a missing API key) and why.

## What it is

A third real `LLMClientInterface` implementation, alongside `OpenAIClient`/
`ClaudeClient` (§7.28) — [OpenRouter](https://openrouter.ai) is a single
API in front of 100+ models from many providers, including several that
are genuinely free to call (`meta-llama/llama-3.1-405b-instruct:free`,
`mistralai/mistral-large:free`, `qwen/qwen-2.5-72b-instruct:free`,
`google/gemini-flash-1.5:free`, and more — see OpenRouter's own model
list for the current set; free-tier availability changes over time and
this file doesn't try to track it). OpenRouter's own Chat Completions
endpoint is OpenAI-compatible, so `OpenRouterClient` mirrors `OpenAIClient`
almost exactly — same request/response shape, same `LLMRequestFailedException`
error mapping — with two genuine differences: the base URL is a real,
configurable constructor parameter (routing to a chosen endpoint is this
provider's whole reason to exist), and two extra, OpenRouter-recommended
attribution headers (`HTTP-Referer`/`X-Title`) are sent on every request.

## Configuration

```env
LLM_PROVIDER=openrouter
OPENROUTER_API_KEY=sk-or-...
OPENROUTER_MODEL=meta-llama/llama-3.1-405b-instruct:free
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
```

Get a key at [openrouter.ai](https://openrouter.ai). `OPENROUTER_MODEL`
defaults to a free model, so a working key with $0 balance can still call
it. `OPENROUTER_BASE_URL` only needs changing for a self-hosted proxy or
gateway in front of OpenRouter — leave it at the default otherwise.

`LLM_PROVIDER` also still switches between `openai`/`claude`/`openrouter`,
same as before this stage — nothing about the existing two providers
changed.

## Using it

Once configured, OpenRouter is used exactly like OpenAI/Claude already
were — this stage adds a provider, not a new code path:

```env
PLANNER_TYPE=llm      # LLMPlanner asks OpenRouter to plan each Goal
REASONING_TYPE=llm    # LLMReasoningEngine asks OpenRouter to think()/reflect()
```

Both default to their own non-LLM sibling (`deterministic`/`simple`) —
see `docs/llm-planner.md`/`docs/self-reflection.md` for why, unchanged by
this stage.

## What a missing/invalid API key actually does

**There is no separate "no API key" fallback client in this codebase.**
`OpenRouterClient` (like `OpenAIClient`/`ClaudeClient` before it) is
constructed with whatever `OPENROUTER_API_KEY` resolves to — including an
empty string, the config's own default — and only fails, correctly, the
moment it actually calls the API. That failure is already caught one
layer up: `LLMPlanner` falls back to `DeterministicPlanner`,
`LLMReasoningEngine` falls back to `SimpleReasoningEngine` — both
automatic, both already built (§7.28/§7.31), neither needed touching for
this stage. A second, redundant "no API key" client was considered and
not built — see `HANDOFF.md` §7.32 for the full reasoning.

An unrecognized `LLM_PROVIDER` value (a typo, e.g. `openrouterr`) still
throws `InvalidArgumentException` at resolution time — unchanged,
deliberately not softened into a silent fallback, since a config typo
should fail loudly, not degrade quietly.

## Testing

No live OpenRouter credentials exist in this dev environment (the same
"needs real credentials to test honestly" reasoning every external
Connector in this codebase already gives) — every test either injects a
Guzzle `MockHandler`-backed real `OpenRouterClient` (`OpenRouterClientTest`,
Unit) or rebinds `LLMClientInterface` to a fake directly
(`OpenRouterIntegrationTest`, Feature — mirrors `LLMPlannerIntegrationTest`'s
own exact shape, covering the planner path, the reasoning path, and both
falling back gracefully together when the fake throws). No real network
call ever reaches OpenRouter from this suite.
