← [AI, LLMs & AI Agents](09-ai-llms-and-ai-agents.md) | Next: [Online Payments & Fintech](11-online-payments-and-fintech.md) →

# 10. The MCP Protocol & Agent Ecosystem

The last chapter covered general AI and Agent concepts. This chapter reaches straight into the heart of this project's own architecture — the thing the entire platform is built around.

## Model Context Protocol (MCP)

**Simple definition:** a standard contract that defines how an AI system should **discover** the "capabilities" of an external system (like a store) and then **execute** them — without knowing anything in advance about how that external system is actually implemented.

**Why it matters:** before a standard like this existed, every time a team wanted to connect an AI agent to a specific business system, they had to build a completely custom, one-off integration. A shared protocol standardizes this once, for good — exactly the same problem HTTP solved for "how a client and server talk" (chapter 1), MCP solves for "how an AI agent and a business system talk."

📍 **In this project:** the entire architecture, as stated in main series file 01, is built directly around this idea — "Infrastructure First": before ever thinking about e-commerce, the platform first solved "how does an AI agent discover and execute a business's capabilities."

## Capability

**Simple definition:** the core unit of this protocol — a specific, named "ability" an external system can perform, e.g. "search products" or "place an order." Every Capability has a name, a description, a defined input shape (Input Schema), a defined output shape (Output Schema), and the permissions required to run it.

**Why it matters:** instead of an agent needing to directly understand your code or your database, it just sees a simple, documented list of "things I can do."

📍 **In this project:** `commerce.product.search`, `commerce.order.place`, `agent.goal.execute` — these are all Capabilities. This platform has 127 of them today, spread across 10 different domain modules.

## Capability Discovery

**Simple definition:** a simple request that asks "what capabilities even exist?" — and a complete list of every available Capability, along with its full documentation, comes back.

**Why it matters:** this is exactly what lets an AI agent (or even an LLM) "figure out for itself" what it's able to do — without a human pre-coding each one for it.

📍 **In this project:** `GET /mcp/v1/capabilities` does exactly this. `LLMPlanner` (previous chapter) embeds this exact list into its own prompt, so the LLM knows what tools it has available.

## Capability Execution

**Simple definition:** actually calling a Capability with specific inputs — "run capability X with these inputs" — and getting back a real result.

📍 **In this project:** `POST /mcp/v1/execute`, with a body like `{"capability": "commerce.product.search", "input": {"query": "laptop"}}`, does exactly this. Interestingly, every capability runs through this one single address (not a separate address per capability) — because the number of capabilities can keep growing (127 today), and hardcoding a fixed address per capability would contradict the whole "dynamic discovery" idea.

## The Response Envelope and API Versioning

**Simple definition:** the "response envelope" is the fixed, consistent shape every response gets wrapped in (e.g. always a `data` field for the result and a `meta` field for extra info). "API versioning" means when this shape needs to change, instead of breaking every existing client, you add a new version (`v2`) alongside the old one (`v1`) — anyone stays on the old version until they explicitly opt in.

📍 **In this project:** `v1` puts the response in `data`/`meta`; `v2` puts the exact same underlying data in `result`/`metadata` — with one interesting, strict rule: whatever version is specified in the URL always wins, even if an HTTP header says otherwise — because behavior that silently changes because of a hidden header is exactly what makes a real integration untrustworthy.

## Planner and Executor

**Simple definition:** the "Planner" takes a vague, text-based goal ("increase sales") and turns it into a precise list of Capabilities with specific inputs. The "Executor" actually runs that list, one by one, recording the outcome of each step.

**Why it matters:** this separation means "deciding what should happen" is completely independent from "actually making it happen" — you can swap the Planner (e.g. from fixed rules to a real LLM) without changing a single line of the Executor.

📍 **In this project:** this is exactly why there are two fully separate Planner implementations — `DeterministicPlanner` (based on fixed, configured rules; fast and free) and `LLMPlanner` (actually asks a real AI model) — and if the second one fails, it automatically falls back to the first (Fallback, previous chapter).

## Execution Memory & Learning

**Simple definition:** a complete record of every goal that's ever been executed and what happened — and using that history so that next time a similar goal comes in, instead of planning from scratch, the same plan that already worked gets reused.

**Why it matters:** if every goal — even one requested a hundred times before — gets handed to an LLM from scratch every single time, both cost and time are wasted needlessly.

📍 **In this project:** `ExecutionPattern` is exactly this memory — and even when a goal later fails, that same pattern gets weakened (not just successes being recorded), so an outdated pattern never keeps getting confidently suggested forever.

## Universal Commerce Protocol (UCP)

**Simple definition:** one normalized, consistent data model for commerce concepts (product, order, ...) — regardless of where the data actually came from (this platform's own store, or an external system like WooCommerce).

**Why it matters:** without a normalized model, every time a new external system gets connected, the rest of the platform would have to learn that specific system's own weird format too. With UCP, only that one Adapter (chapter 5 of this pre-tutorial) has to translate the external format into UCP; the rest of the platform always only ever works with UCP.

📍 **In this project:** `UCPProduct` is exactly this — whether a product comes from this platform's own database or a connected WooCommerce store, it always reaches the rest of the system in this one same shape.

## Capability-Level vs. Goal-Driven — Two Levels of Interaction

**Simple definition:** this platform offers two completely different ways to be used — **Level 1**: call a specific Capability directly (when you know exactly what you want). **Level 2**: just hand over a text-based goal and let the Planner/Executor figure out for themselves which Capabilities to run, and in what order.

📍 **In this project:** a simple agent that just wants "search for this product" uses Level 1; a more advanced agent that's itself an AI system and just wants to hand over a general goal uses Level 2 (`agent.goal.execute`) — both paths always available, at the same time.

---

← [AI, LLMs & AI Agents](09-ai-llms-and-ai-agents.md) | Next: [Online Payments & Fintech](11-online-payments-and-fintech.md) →
