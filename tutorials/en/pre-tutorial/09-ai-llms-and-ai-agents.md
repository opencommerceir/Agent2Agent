← [Software Testing](08-software-testing.md) | Next: [The MCP Protocol & Agent Ecosystem](10-the-mcp-protocol-and-agent-ecosystem.md) →

# 9. AI, LLMs & AI Agents

Everything up to this point was "classic" software engineering. From here on, we enter the territory that sets this project apart from an ordinary storefront: Phase 6, AI Agent Orchestration. This chapter unpacks the foundational vocabulary; the next chapter goes into the details of the MCP protocol itself.

## Large Language Model (LLM)

**Simple definition:** an AI model trained on a huge amount of text to predict what text comes next — and from that simple ability, remarkable things emerge, like answering questions, writing code, or summarizing text. Examples: GPT (from OpenAI), Claude (from Anthropic), open models available through OpenRouter.

**Why it matters:** an LLM is what lets this platform take a vague, text-based goal ("increase sales this week") and turn it into a concrete, executable plan ("run these 4 capabilities with these inputs").

📍 **In this project:** `LLMClientInterface` is a shared contract for talking to any LLM; it has three real implementations — OpenAI, Claude, and OpenRouter (main series, files 13 and 15).

## Prompt and Prompt Engineering

**Simple definition:** a "prompt" is the text you give an LLM to get a specific response from it. "Prompt engineering" is the art/skill of writing that text so the response is reliable, accurate, and in the correct format.

**Why it matters:** an LLM has no memory of your project — everything it needs to know (what capabilities exist, what format to return) has to be told to it, every single time, in that same prompt.

📍 **In this project:** `PlanningPromptTemplate` is exactly a prompt builder — it embeds the platform's full, real list of capabilities into the prompt text so the LLM knows exactly what tools it has available.

## Token and Context Window

**Simple definition:** LLMs break text into small chunks called "tokens" (roughly a few characters each). The "context window" is the maximum number of tokens a model can "see" at once — both the input prompt and the output response.

**Why it matters:** the longer the prompt, the higher the cost (most services price by token count) and the slower the response — and if it exceeds the context window, it simply doesn't work at all.

📍 **In this project:** a real, documented finding: when this platform embeds the full list of all 127 capabilities (about 20,700 characters) into every prompt, that size genuinely affects free-tier models' response speed — this is a real, measured finding, not a guess (`HANDOFF.md` §7.35), and reducing this size is a real item on the future roadmap.

## Tool Calling / Function Calling

**Simple definition:** an LLM's ability to, instead of just "writing text," say "I want to call this specific function with these specific inputs" — and then your system actually executes that function and returns the result back to the model.

**Why it matters:** this is exactly what turns an LLM from "something that only generates text" into "something that can actually do things in the real world."

📍 **In this project:** every "Capability" is exactly the callable "tool" here — the next chapter unpacks this fully.

## AI Agent

**Simple definition:** a system that uses an LLM for **decision-making**, not just text generation — it takes a goal, decides for itself what tools to call and in what order, sees the results, and adjusts its path if needed.

**Why it matters:** this is exactly the difference between a "simple chatbot" and an "Agent" — a chatbot only answers; an Agent actually **does things**.

📍 **In this project:** the "Agent Orchestrator" (all of Phase 6, main series files 12–15) implements exactly this cycle: goal → planning → execution → learning from the result.

## Fallback

**Simple definition:** a strategy where, if the "smart" path (calling a real LLM) fails for any reason (no internet, an invalid key, a timeout), the system automatically falls back to a simpler, always-working path — instead of letting the whole request fail with an error.

**Why it matters:** an external service (like an AI API) is never 100% reliable — a serious system always needs a plan B.

📍 **In this project:** this is one of this platform's strongest, most-repeated principles — `LLMPlanner` automatically falls back to `DeterministicPlanner` (a rules-based planner with no AI at all) if it fails; the exact same pattern repeats for Reasoning. This means even if the AI service goes completely down, the platform never fully stops working.

## Reasoning and Reflection

**Simple definition:** "Reasoning" means an LLM states its thought process (even briefly) before deciding — not just the final answer. "Reflection" means *after* a task runs, the system looks back at the result and produces an evaluation/explanation ("how well did this go?").

📍 **In this project:** every goal execution produces and persists a real `ReasoningTrace`, both before execution (`think()`) and after it (`reflect()`) — with one important, deliberate caveat: this reasoning is **explanatory only**, it never itself decides which capability actually runs (main series, file 14).

## Multi-Agent Systems and Delegation

**Simple definition:** instead of one single Agent trying to do everything, several specialized "personas" exist (e.g. one for sales, one for support), and one can "delegate" part of a task to another.

**Why it matters:** a specialized sales persona has different planning rules than a support persona; delegation means each part of a task goes to the best available "specialist."

📍 **In this project:** an interesting, honest architectural detail: a persona (like "CEO" or "Sales") is only a **planning classification**, not a separate identity with its own permissions — meaning delegating a task can **never** grant access to something the real caller didn't already have (a deliberate security decision, main series file 14).

## Hallucination

**Simple definition:** when an LLM confidently states something that isn't actually true — e.g. the name of a function/capability that doesn't actually exist.

**Why it matters:** no serious system should blindly trust an LLM's output; there always needs to be a real validation layer.

📍 **In this project:** if an LLM suggests a made-up, nonexistent capability name, that exact moment of execution is rejected with a real, specific error (`CapabilityNotFoundException`) — exactly like any other invalid input, with zero blind trust.

---

← [Software Testing](08-software-testing.md) | Next: [The MCP Protocol & Agent Ecosystem](10-the-mcp-protocol-and-agent-ecosystem.md) →
