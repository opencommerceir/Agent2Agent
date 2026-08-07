← [Self-Reflection, Reasoning and OpenRouter](15-self-reflection-and-openrouter.md) | Next: [Architecture Patterns and Gotchas](17-architecture-patterns-and-gotchas.md) →

# 16. The Showcase Demo — Live Chat, Data Panel, Delegation, and History

After Phase 6 finished and OpenRouter was added, the last step before real-world testing was building a **live chat interface** to demonstrate all of it. This was built in three consecutive passes, covered together in this file.

## First, the real question: what is this Showcase actually for?

The entire pitch of OpenCommerce Platform is: "a business can become Agent Ready for AI agents." That sentence stays **abstract** as long as it only ever appears as a `curl` command and a JSON blob — especially to anyone who isn't a developer. The Showcase exists specifically to solve that problem: turning an abstract claim into a **live, tangible experience**.

In plain terms: the Showcase is a web page where, instead of clicking around a storefront yourself, **you type an ordinary sentence** (like "increase sales by 15% this week") and watch an "AI agent" actually:

1. **think** about that goal,
2. build a **real plan** out of MCP capabilities,
3. **execute** that plan against a real (seeded) store,
4. and finally **reflect** on the outcome — all of it live, in front of you.

## Where — and by whom — is it actually used?

### 1. Sales and business demos
This is the single most important real-world use of this page. When you want to show a prospective customer, an investor, or a business partner what OpenCommerce actually does, you don't need to show them code or JSON — they can type one sentence, or click a "Suggested Goal," and watch an "AI employee" genuinely act inside the store: creating a coupon, pulling a sales report, delegating work to another persona. This is the exact moment the "Agent Ready" pitch stops being a marketing sentence and becomes something they can watch happen.

### 2. An exploratory, visual QA tool for developers
As you'll see in Pass 2 below, the only real bug from the entire Phase 6 body of work that no automated test caught (a suggested button colliding with a learned pattern) was found only by **manually clicking through this exact page**. In other words, the Showcase is not just a demo tool — it's a genuine **exploratory testing surface**: the whole Planner → Executor → Reasoning → Delegation → Memory chain can be followed visually, on one page, without writing a single `curl` command.

### 3. An onboarding and fast-ramp-up tool for new team members
A developer who has just read the five Phase 6 stages (files 12–15 of this tutorial) can, in a few minutes of direct interaction with this page, see exactly what those abstract concepts (Planner, Reasoning, Delegation, Memory) actually look like — much faster than tracing through code alone.

### 4. A safe sandbox for testing real AI models
The "🧠 Use real AI" toggle lets you try the real behavior of `LLMPlanner`/`LLMReasoningEngine` without touching any real Agent token and at zero cost (via OpenRouter's free tier, file 15) — a completely safe environment to see how a real LLM actually plans and reasons about these same goals, before you'd ever consider wiring it into a real environment.

### 5. A reference implementation for third-party developers
Anyone who wants to build their own UI on top of MCP (not necessarily a chatbot) can read `ShowcaseController`'s own code directly to see exactly what "correctly authenticating an Agent" and "correctly calling `ExecuteGoalAction`" look like — a real, working example, not just documentation.

### 6. A live, on-stage or video-call demo
Because the seeded data is real and varied (not a hollow empty fixture), and an optional passcode (`SHOWCASE_PASSCODE`) lets you share the link safely with an external audience, this page is specifically built for a live demo at a conference or on a sales call.

## What this Showcase is *not* — to avoid any confusion

- **It is not a production feature.** The `demo-showcase` tenant is a fully fictional demo tenant with realistic but fabricated data, not a real customer's real store.
- **It is not a replacement for the Admin Dashboard.** The Admin Dashboard (file 9) is for real management of any real tenant on the platform; the Showcase only ever demonstrates "AI capability" on one demo tenant — the two never substitute for each other.
- **It is not a real customer-support channel.** The conversation here is between an operator/visitor and an internal AI agent, not between a real end customer and a storefront.

## A concrete usage walkthrough

Imagine you want to show a prospective customer's CEO what this platform does:

1. You send them the `/showcase` link (probably behind a demo passcode).
2. They select the "CEO" persona.
3. They click the suggested goal "Delegate this promotional campaign to the sales team."
4. In front of their eyes: the agent first "thinks" about why this makes sense, then genuinely delegates the work to the Sales persona, the Sales persona creates a real coupon and sends a real notification, and finally an honest reflection on the outcome appears.
5. They glance at the side panel and see real (seeded) KPIs and orders sitting right there.

This is the exact moment file 1's whole vision ("AI agents that actually run a business") stops being an idea and becomes a clickable experience.

## What exactly is this demo?

A web page at `/showcase` that:

1. Lets you pick one of 4 personas (CEO, Sales, Support, Finance).
2. Lets you click a "Suggested Goal" or type your own.
3. Has an optional "🧠 Use real AI" toggle.
4. Renders the entire `think() → plan → execute → reflect()` cycle **live** on screen.
5. Shows a live data panel (KPIs, Products, Orders) alongside it.
6. Has a history sidebar (🕘) that replays any past conversation.

## Important architectural note: no new logic here

`/showcase` follows the exact same pattern as the Admin Dashboard (file 9): a thin layer on top of `ExecuteGoalAction` — **the same** Action that `agent.goal.execute` and `/api/agents/{agent_type}` already call. No Domain/Application layer of any module was changed for this demo; it's purely a new consumer.

```
ShowcaseController::chat()
   → AuthenticateAgentAction (the real Agent authentication Action)
   → the same authenticate → rate-limit → authorize → execute sequence
   → ExecutionResultData::toArray()   ← unchanged shape
```

This means the chat UI is **Planner-agnostic** — whatever sits behind `PlannerInterface`/`ReasoningEngineInterface` (Deterministic or LLM-backed) is rendered exactly as-is.

## Demo data — `DemoShowcaseSeeder`

A well-known tenant (`demo-showcase`) with real, meaningful data, not an empty fixture:

- 40 products across 5 categories, 2 warehouses
- 6 products with variants
- 40 customers, roughly 40% with an active loyalty account
- 10 support tickets
- 2 coupons + 2 active discount rules
- **180 real orders** (through the actual `AddToCartAction`/`ProcessPaymentAction`) backdated across the last 85 days, so sales charts show real day-to-day variance instead of a flat spike.
- 3 real prior executions, run through the actual, unmodified `ExecuteGoalAction` (never a raw database insert), so the execution history isn't empty on first visit.

**This seeder is never run by default** — it must be explicitly requested:

```bash
php artisan db:seed --class=DemoShowcaseSeeder
# or
php artisan demo:reset
```

## `demo:reset` — a safe reset between demo runs

This command wipes and rebuilds the demo tenant — safe to run **mid-demo**, since every controller resolves the demo tenant fresh, by slug, on every request (never cached at startup).

A technical detail worth knowing: there is no generic "cascading delete" operation anywhere in this codebase — so this command deletes the one `tenants` row directly through the query builder and relies on the schema's own `->cascadeOnDelete()` foreign keys (present on every tenant-scoped table). The one deliberate exception, correctly never touched: the global `permissions` table, which has no `tenant_id` at all.

## Pass 1 — the basic chat

The first version only built the chat itself: pick a persona, send a goal, see the full response. A fresh Agent token is minted per browser session (not a shared, hardcoded secret).

## Pass 2 — the live panel, suggested goals, and real delegation

### The live data panel

Three tabs beside the chat (products/orders/KPIs), fed by the exact same Actions the Admin Dashboard already uses. Only the **active** tab refetches after each chat turn, never all three at once.

### Suggested goals

Each persona has 2-4 one-click "suggested goal" buttons, each proven against a real rule in `config/agents/{type}.php` — never a made-up phrase.

### Turning on real delegation

This is the first time delegation (built in file 14) became reachable through a **planned goal**, not just a direct capability call — a new `delegate` rule was added to `config/agents/ceo.php`.

### A real bug only caught by live testing, not `php artisan test`

The first "suggested goal" text for the delegate button contained the word "Sales." Problem: because `DemoShowcaseSeeder` had already recorded a successful "sales" execution, `PatternExtractor` (file 14) had already learned a pattern keyed on the word "sales" — and since pattern matching is just a plain substring check, the delegate button's own text matched that old pattern and **silently** re-ran the old 4-step plan, never reaching the new `delegate` rule at all! The full test suite stayed green the entire time, because no test had ever exercised exactly this collision before.

**The lesson from this bug:** this was the only place in the entire session where a real bug was found only by manually running a real server (`php artisan serve` + `curl`), not by the automated test suite. The fix was two-fold: the button's text was reworded to avoid every keyword, **and** a new regression test was written that deliberately pre-seeds the exact colliding pattern before asserting delegation still resolves correctly — closing the actual coverage gap, not just the symptom.

## Pass 3 — the real-AI toggle, history, and an access gate

### The "🧠 Use real AI" toggle

When enabled, configuration is temporarily switched to `llm`/`openrouter`, `ExecuteGoalAction` is run, and then configuration is restored — inside a `finally` block, regardless of success or failure.

A real technical constraint identified during planning: this **couldn't** be done with `ExecuteGoalAction` as a method-injected controller parameter, since Laravel resolves method parameters **before** the method body runs — meaning the Planner would already be built before the config override ever took effect. Instead, `ExecuteGoalAction` is resolved manually (`app(ExecuteGoalAction::class)`) *after* the override — safe only because all three relevant Interfaces are bound as closures, never singletons, so each resolution re-evaluates them.

If no real API key is configured, the toggle never causes a hard failure — the same automatic fallback (files 13 and 15) kicks in quietly.

### Conversation history

A slide-in drawer (🕘 button) showing the 20 most recent executions; clicking one re-renders the exact same response card, with the reveal animation skipped (since it's a historical replay, not a fresh run).

### The access gate (optional)

A simple passcode (`SHOWCASE_PASSCODE`, blank/disabled by default) that lets this demo be shared safely. **Completely independent** of the Admin Dashboard's human login system — two sessions, two mechanisms, never combined.

## Summary table of the three passes

| Pass | Added |
|---|---|
| 1 | Basic chat, per-session token |
| 2 | Live panel, suggested goals, real delegation + a pattern-collision bug fix |
| 3 | Real-AI toggle, history, passcode gate |

By the end of these three passes: **1102 tests, 124 capabilities, zero known regressions.**

In the next file, we gather every recurring architectural pattern seen throughout this tutorial into one reference "cheat sheet" for anyone working on this project.

---
← [Self-Reflection, Reasoning and OpenRouter](15-self-reflection-and-openrouter.md) | Next: [Architecture Patterns and Gotchas](17-architecture-patterns-and-gotchas.md) →
