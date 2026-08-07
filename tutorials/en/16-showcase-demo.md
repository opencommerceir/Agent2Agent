← [Self-Reflection, Reasoning and OpenRouter](15-self-reflection-and-openrouter.md) | Next: [Architecture Patterns and Gotchas](17-architecture-patterns-and-gotchas.md) →

# 16. The Showcase Demo — Live Chat, Data Panel, Delegation, and History

After Phase 6 finished and OpenRouter was added, the last step before real-world testing was building a **live chat interface** to demonstrate all of it. This was built in three consecutive passes, covered together in this file.

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
