# Agent Profiles

> Added in Phase 6, Stage 2 (§7.27 in `HANDOFF.md`). This is the
> how-to-add-a-new-Agent guide; `docs/agent-orchestrator.md` is the
> module's own architecture reference and `HANDOFF.md` §7.27 carries the
> full build narrative.

## What a profile is

An `AgentProfile` is the config-driven definition of one Agent persona —
what `DeterministicPlanner` reads to decide *which* capabilities to call
for a given Goal, and *what input* to call them with. It lives entirely in
`config/agents/{type}.php`; there is no PHP code specific to any one
Agent persona anywhere in this module.

Four profiles ship today: `ceo`, `sales`, `support`, `finance` — one file
each under `config/agents/`.

## Adding a new Agent — the only steps

1. Add the new case to `AgentType` (`Domain/ValueObjects/AgentType.php`)
   if it doesn't already exist.
2. Create `config/agents/{type}.php` (see shape below).
3. Add the new type to the `{agentType}` route constraint in
   `routes/agents.php` (`ceo|sales|support|finance` → add yours).

That's it — no other file changes. `ConfigBasedAgentProfileRepository`
picks up the new file automatically (Laravel's own recursive config
directory loading maps `config/agents/{type}.php` to
`config('agents.{type}')`), `DeterministicPlanner` reads it the same way
it reads every other profile, and both `/api/agents/{type}` and
`agent.goal.execute` work immediately.

## Config file shape

```php
<?php

return [
    'name' => 'Human-readable name',
    'description' => 'One sentence describing this persona.',

    'planning_rules' => [
        // goal-text keyword => ordered list of real MCP capability names.
        // The FIRST keyword found (case-insensitive substring match, in
        // the order declared here) wins — only one rule's capability
        // list is ever used per Goal.
        'some-keyword' => ['some.capability.name', 'another.capability.name'],

        // Required. Used whenever no other keyword matches.
        'default' => ['some.capability.name'],
    ],

    'default_inputs' => [
        // capability name => the input that capability call will use.
        // Every field a target capability's own inputSchema declares as
        // required MUST be present here, or the call fails
        // MCPRequestValidationService before it ever runs.
        'some.capability.name' => [
            'a_literal_field' => 'used exactly as written',
            'a_computed_field' => '{date:-7}',
        ],
    ],

    // Descriptive only — see "What `permissions` does NOT do" below.
    'permissions' => ['some.permission.key'],
];
```

## Template tokens `DeterministicPlanner` resolves

Any `default_inputs` value that isn't one of these is used **literally,
unresolved** (e.g. `'channel' => 'email'`, `'kpi_type' => 'revenue'`).

| Token | Resolves to |
|---|---|
| `{date:N}` | N days from today (negative for the past, `0` for today), `Y-m-d` — e.g. `{date:-7}` for "7 days ago." |
| `{coupon_code}` | A freshly generated `COUPON-XXXXX` (5 random alphanumeric characters), matching `CouponCode`'s own required format. |
| `{discount_percent}` | Parsed from the Goal's own text (`/(\d{1,3})\s*%/`), defaulting to `10` when absent, clamped to `1`-`100`. |

**Before adding a new capability to a profile, check its real
`inputSchema`** (`HANDOFF.md` §6, or the module's own
`Interfaces/MCP/*Capabilities.php` manifest) — every field it declares as
required must appear in your `default_inputs` entry, with either a
literal value or one of the tokens above. A field left out entirely (for
a capability with genuinely optional fields) is fine; a field present but
resolving to the wrong shape (e.g. `commerce.coupon.create`'s `code` not
matching `COUPON-XXXXX`) fails that one step — the Orchestrator continues
past it, but it never succeeds.

## What `getCapabilitiesForGoal()` does NOT do

It does not combine multiple matching rules, does not deduplicate across
rules, and does not weigh keywords by specificity — the first match in
your own declared order wins, full stop. If two rules could plausibly
match the same goal text, put the more specific one first.

## What `permissions` does NOT do

A profile's own `permissions` array is **descriptive metadata only** — the
permission keys an operator should grant an Agent of this type so its
planned capabilities can actually succeed. It is **not** enforced as a
second authorization gate. Real enforcement happens once, per planned
step, inside `CapabilityToolInvoker` — the exact same
`CheckPermissionAction` check `/mcp/v1/execute` itself uses against that
capability's own `requiredPermissions`. A profile whose `permissions`
list drifts out of sync with what its `planning_rules` actually call is a
real, honest gap (see `HANDOFF.md` §8) — it doesn't break anything (the
real per-step check still applies), it just means the descriptive list
lied. Keep them in sync by hand for now.

## Testing a new profile

- `tests/Unit/AgentOrchestrator/AgentProfileTest.php` — the pattern for
  testing `AgentProfile::fromConfig()`/`getCapabilitiesForGoal()`/
  `getDefaultInput()` in isolation, no Laravel container needed.
- `tests/Feature/AgentOrchestrator/ConfigBasedAgentProfileRepositoryTest.php`
  — the pattern for asserting a real `config/agents/{type}.php` file
  loads correctly through `config()`.
- `tests/Feature/AgentOrchestrator/CEOAgentTest.php` — the pattern for an
  end-to-end "this profile's own config genuinely drives what gets
  called, with what resolved input" test for a specific persona.
