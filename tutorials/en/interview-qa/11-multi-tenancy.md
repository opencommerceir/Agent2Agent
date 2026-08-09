← [CQRS & Read Models](10-cqrs-read-models.md) | Next: [Security](12-security.md) →

# 11. Multi-Tenancy

File 04 of this handbook (questions 2, 3, and 9) already covered multi-tenancy at the schema level — the shared-database-with-`tenant_id` model, the real cache-leak bug it once caused, and the NULL-distinctness caveat on a widened unique index. This file goes one layer up: how tenant scoping actually flows through a live request from the Agent's own bearer token all the way down to a Repository query, why an MCP capability is never allowed to accept a caller-supplied `tenant_id`, the two genuinely separate authorization systems this platform runs (one scoped *inside* a Tenant, one sitting *above* every Tenant), and where the isolation story is still honestly incomplete.

---

### Q1: What isolation model does this platform actually use, and what's the long-term plan?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate knows this is a deliberate, staged decision, not a permanent architectural ceiling.

✅ **Model answer:**
"`CLAUDE.md` states the strategy explicitly: Phase 1 is a shared database with `tenant_id`-column isolation on every tenant-scoped table — the cheapest model to build and operate at early scale. Phase 2, still unbuilt, is database-per-tenant for whichever Tenants genuinely need physical isolation (compliance, noisy-neighbor load). The explicit rule guiding Phase 1's own design was 'never design anything that blocks future migration' — which is why, as file 04 (question 2) already covers, every Repository method takes `tenant_id` as an explicit parameter rather than relying on some implicit 'current tenant' concept. That single discipline is what keeps Phase 2 a real, reachable option instead of a rewrite."

🔁 **Likely follow-ups:**
1. "So nothing today prevents database-per-tenant?" → Correct, in principle — question 11 of this file walks through exactly what would and wouldn't need to change.
2. "Why not build Phase 2 now?" → Because no real Tenant has yet needed it — the same 'don't build for a scale you haven't reached' discipline file 02 (question 1) already applies to microservices.

🚩 **Red flags:**
Describing this as "the" multi-tenancy model rather than "the current, deliberately staged" one — it misses that the whole point of the design was leaving Phase 2 open.

---

### Q2: Where does a request's `tenant_id` actually come from? Walk me through the real mechanism.

🎯 **What the interviewer is REALLY testing:**
Whether tenant scoping is understood as a real, traced mechanism or just a vague "there's a column for that" answer.

✅ **Model answer:**
"It never comes from anything the caller states directly. File 02 (question 3) already traced the full request lifecycle — the specific step that matters here is step 2: `AgentAuthenticationService` reads the bearer token, hashes it, and looks up the owning Agent in `agent_tokens`. That Agent record carries its own `tenant_id`, fixed the moment the Agent was registered — not something read from the request body, a header, or a query string. `AuthContext::forAgent($agent)` captures that real value into `AuthContext::$tenantId`, and from that point on it's passed explicitly, parameter by parameter, all the way down through the handler closure, the Action, and the Repository — never re-resolved from a session, a global, or the container."

🔁 **Likely follow-ups:**
1. "Could a bug anywhere let a caller override this?" → Structurally no — no capability's `inputSchema` includes a `tenant_id` field at all (question 3 of this file explains why that's an explicit, deliberate rule, not an oversight).
2. "What if `AuthContext` were resolved from a container singleton instead of passed explicitly?" → It would be actively dangerous under PHP-FPM/mod_php connection or object reuse across unrelated requests — the same class of risk question 8 of this file covers for database connections specifically.

🚩 **Red flags:**
Suggesting the tenant is "whatever the request says it is" — that's the exact assumption this project's design goes out of its way to make structurally impossible.

---

### Q3: Why doesn't a single MCP capability accept a caller-supplied `tenant_id` input, even when a request explicitly asked for one?

🎯 **What the interviewer is REALLY testing:**
A concrete, real instance of catching a cross-tenant leak *during planning*, before it ever became runtime code — not just a rule stated in the abstract.

✅ **Model answer:**
"This came up for real during the Analytics stage. The original request's own input schema for `analytics.dashboard.stats`, `analytics.snapshot.generate`, and `analytics.kpi.list` each named an optional, caller-supplied `tenant_id` field. Building it literally would have meant any Agent could read a *different* Tenant's revenue, customer count, or order history just by passing that Tenant's id — a real, structural cross-tenant leak, not a hypothetical one. It was caught and corrected before any code was written: every one of those three capabilities scopes exclusively to `AuthContext::$tenantId`, the same as every other capability in this codebase, with the caller-supplied field dropped from the schema entirely. This is now a platform-wide, exceptionless rule — grep every `*Capabilities.php` manifest and none of them accept a tenant id as input."

🔁 **Likely follow-ups:**
1. "Wouldn't a platform operator legitimately need to query across Tenants sometimes?" → Yes — but that's a different, higher-privileged surface entirely (the Admin Dashboard's own `User`/`UserRole` system, question 7 of this file), never an ordinary Agent-facing MCP capability.
2. "Is this rule enforced by code, or just convention?" → Convention plus a very deliberate, repeated review discipline — the exact 'audit the request against the real codebase before writing code' habit file 01 (question 11) already described elsewhere in this project.

🚩 **Red flags:**
Saying "it's fine as long as you check the tenant_id matches" — the whole point of dropping the field is that there's nothing to check; a field that doesn't exist in the schema can never be misused.

---

### Q4: Does Eloquent apply `tenant_id` filtering automatically (a global scope), or is it explicit? Why that choice?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate assumes "framework magic" handles this, or actually knows the real discipline the codebase enforces.

✅ **Model answer:**
"Explicit, everywhere — there is no Eloquent global scope auto-appending `WHERE tenant_id = ?` to every query. Every `EloquentXRepository` method that reads or writes tenant-scoped data takes `tenant_id` as a real, explicit parameter and filters on it directly in the query builder chain. This is a direct instance of `CLAUDE.md`'s own 'Explicit Over Magic' principle — a global scope is convenient, but it hides the tenant boundary inside framework behavior a reader can't see just from reading an Action's own method signature. With an explicit parameter, `EloquentOrderRepository::listByTenant(int $tenantId)`'s own signature *is* the proof the query is scoped — nothing has to be inferred from a trait silently attached somewhere else."

🔁 **Likely follow-ups:**
1. "Doesn't that risk someone forgetting to filter?" → Yes, honestly — file 04 (question 2) already names this as the real cost of the explicit approach: it depends on code review and a fixed convention ('every Repository method's first parameter is `tenant_id`'), not a framework guarantee.
2. "Would a global scope have been safer?" → Debatable — it trades one risk (a forgotten filter) for another (a query that's tenant-scoped in a way that's invisible at the call site, harder to reason about during review).

🚩 **Red flags:**
Assuming Laravel's multi-tenancy packages (or a custom global scope) are already doing this work somewhere — a fair guess for someone who hasn't actually read the Repositories, but wrong for this codebase specifically.

---

### Q5: Why does looking up another Tenant's record by a guessed id return 404, not 403?

🎯 **What the interviewer is REALLY testing:**
A real security-design detail — status codes as an information-disclosure surface, not just an HTTP-semantics trivia question.

✅ **Model answer:**
"Because a `403` itself leaks information: it confirms 'this record exists, you're just not allowed to see it' — which tells an attacker probing sequential ids that record `#42` is real, just owned by someone else. A `404` says nothing at all beyond 'nothing matches, as far as you're concerned' — structurally indistinguishable from an id that was never created. This project's convention (`crm.ticket.get`'s own documented behavior is a direct example: 'Tenant-scoped by `findById()`; cross-tenant id -> 404, not 403') applies this everywhere a lookup is scoped by id — the Repository's own `findById(int $id, int $tenantId)` simply returns nothing for a real record belonging to a different Tenant, and the Action maps 'nothing found' to the same `NotFoundException` it would throw for an id that was never real. There's exactly one failure shape for both cases, on purpose."

🔁 **Likely follow-ups:**
1. "Is this actually tested, or just a convention?" → Tested directly and repeatedly — file 06 of this handbook (its tenant-isolation question) describes the exact pattern: guessing a real id belonging to a different Tenant must return `404`, never real data, and nearly every Feature test in this codebase includes that scenario.
2. "Does this pattern ever break down?" → Only for genuinely platform-level resources with no Tenant at all (a `User` in the Admin Dashboard, question 7 of this file) — there, ordinary Tenant-scoped lookup semantics don't apply in the first place.

🚩 **Red flags:**
Treating 404-vs-403 as an arbitrary style choice rather than a deliberate information-disclosure decision — a strong Senior-level distinction.

---

### Q6: How is the Role/Permission RBAC scoped per Tenant — and why does the `permissions` table itself have no `tenant_id` at all?

🎯 **What the interviewer is REALLY testing:**
Recognizing that not every table needs to be tenant-scoped — knowing exactly which one is the deliberate exception, and why.

✅ **Model answer:**
"Every table that holds one Tenant's *own* data — Roles granted, member-role assignments, Agents, and everything the Domain Modules themselves persist — carries `tenant_id` and cascades on it. `permissions`, though, is the one deliberate exception: it's a shared, global vocabulary of *what a permission string can even mean* (`commerce.products.read`, `agent.reasoning.read`, and so on) — the same fixed catalog every Tenant's own Roles are built from, not one Tenant's private data. This becomes very concrete in the `demo:reset` command: every tenant-scoped migration declares its own `tenant_id` foreign key with `cascadeOnDelete()`, so deleting one row from `tenants` cascades through the Tenant's entire tree — Agents, Roles, Orders, Warehouses, everything — in a single statement. `permissions` is the one table that command correctly never touches, because it doesn't belong to any one Tenant to begin with."

🔁 **Likely follow-ups:**
1. "So a new permission string is added once, platform-wide?" → Yes — a new capability's permission requirement is registered once in the global catalog; granting it to a specific Tenant's Agents is the tenant-scoped part.
2. "Could two Tenants end up with conflicting permission needs?" → No — permissions describe *capabilities the platform can do*, not tenant-specific business rules, so there's nothing for two Tenants to conflict over at that layer.

🚩 **Red flags:**
Assuming every table in a multi-tenant system needs `tenant_id` "for consistency" — missing that a genuinely shared, platform-wide vocabulary is the correct exception, not an inconsistency.

---

### Q7: How does the tenant-scoped RBAC Agents use differ from the platform-level `User`/`UserRole` system the Admin Dashboard uses? Why are the two never combined?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can name a real, non-obvious identity split most projects blur together by accident.

✅ **Model answer:**
"They answer two genuinely different questions. The Role/Permission/`MemberRole` system answers 'what is this Agent allowed to do *inside its own Tenant*?' — every check runs through `CheckPermissionAction` against a specific Agent's Roles, always inside the boundary of one Tenant. `User` (the Admin Dashboard's own login) is a platform-level entity with **no `tenant_id` at all** — the second Core entity above tenancy, alongside `Tenant` itself — because the Dashboard's own Tenants-management page does full CRUD *across every Tenant at once*: a platform operator managing other businesses' tenants, not a business's own staff working inside their one store. `User` is gated by a plain `UserRole::Admin`/`Operator` enum and an `admin` route middleware, deliberately never the tenant-scoped RBAC system — because 'what can this member do inside this Tenant' is a question that simply doesn't apply to someone who isn't scoped to any one Tenant to begin with."

🔁 **Likely follow-ups:**
1. "Could a `User` ever also be an Agent?" → No — completely separate authentication mechanisms (session cookie vs. bearer token) and completely separate tables, by design; file 12 of this handbook covers the full authentication-flow detail for both.
2. "Isn't having two RBAC systems more to maintain?" → It's more code, yes — but merging them would mean either forcing every platform operator into a fake 'Tenant' of their own, or letting a Tenant-scoped Role somehow grant cross-tenant access — both worse than two small, clearly-separated systems.

🚩 **Red flags:**
Assuming a Dashboard `Admin` automatically has every permission every Agent has — the two systems don't compose at all; an Admin's own access is a completely separate grant, not a superset of tenant-scoped permissions.

---

### Q8: What connection-pooling risk is specific to a multi-tenant app, and how did this project actually handle it?

🎯 **What the interviewer is REALLY testing:**
A deeper, infrastructure-level tenant-isolation risk most candidates never think about — state leaking through a *reused connection*, not a query.

✅ **Model answer:**
"`PDO::ATTR_PERSISTENT` connections, under mod_php or PHP-FPM, get reused across genuinely unrelated requests to save the cost of re-connecting. That's fine in a single-tenant app — but in a multi-tenant one, a transaction, a session variable, or an advisory lock left open by one request can leak into the *next* request that happens to reuse the same pooled connection — and 'the next request' is very likely a completely different Tenant's own request. This project treats it as a real, unaccepted-by-default risk: `DB_PERSISTENT_CONNECTIONS` defaults to `false` in `config/database.php`, with the exact risk documented inline in that file — a deployment that has actually measured and accepted the trade-off can opt in explicitly, but nothing in this codebase assumes it's safe by default."

🔁 **Likely follow-ups:**
1. "Has this actually happened in this project?" → No known incident — this is a defensive, considered default, the same 'safe default, explicit opt-in for real infrastructure' pattern `CACHE_STORE=database`/`PLANNER_TYPE=deterministic` already use elsewhere in this codebase.
2. "What's the performance cost of not using persistent connections?" → A small, real per-request connection overhead — accepted deliberately, since the cross-tenant state-leak risk of the alternative is worse than that cost in a multi-tenant deployment.

🚩 **Red flags:**
Not knowing persistent connections can leak state across requests at all — this is exactly the kind of infrastructure detail that separates a candidate who's only worked on single-tenant systems from one who's actually reasoned about multi-tenancy risk.

---

### Q9: Rate limiting is scoped per Agent (file 05, question 12), not per Tenant. Is there a fairness gap left open by that?

🎯 **What the interviewer is REALLY testing:**
Independent critical thinking about a real design choice — can the candidate reason past what's already documented, not just recite it?

✅ **Model answer:**
"There's a real, honest gap one level up from what `EnforceRateLimitAction` actually protects against. Its 100-requests-per-minute cap (`config/mcp.php`) is keyed `mcp-agent:{$agentId}` — which genuinely guarantees one high-traffic Agent can never starve another Agent's own quota, exactly what file 05 (question 12) already covers. But nothing keys it by Tenant: a Tenant that provisions ten Agents effectively gets a ten-times-higher aggregate ceiling against the platform than a Tenant running one Agent — a real 'noisy neighbor' risk at the Tenant level, even though the per-Agent fairness guarantee holds perfectly. This isn't something this project's own `HANDOFF.md` currently flags as a tracked debt item — it's a genuine, reasoned observation worth raising, and the fix would be straightforward: a second, Tenant-keyed rate-limit layer alongside the existing per-Agent one, the same `RateLimiter`-based mechanism, one more key."

🔁 **Likely follow-ups:**
1. "Would you actually build this today?" → Only once a real Tenant's own multi-Agent usage pattern demonstrably threatened another Tenant's fair share — the same 'measured need before the fix' discipline this project applies everywhere else (file 04, question 2's migration-timing answer is the same shape).
2. "Is per-Agent or per-Tenant the more natural default for a platform like this?" → Per-Agent is the right first layer (it's the one identity every request actually authenticates as); per-Tenant would be a second, additive layer on top, not a replacement.

🚩 **Red flags:**
Insisting this must already be handled somewhere without being able to point at the actual mechanism (`mcp-agent:{$agentId}`) — a real interviewer will ask exactly what key the limiter uses.

---

### Q10: WooCommerce connector credentials live in `.env`, per-deployment, not per-Tenant. What does that reveal about this platform's multi-tenancy maturity?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can honestly locate where a genuinely solid isolation story (business data) still has a real, acknowledged gap (external credentials).

✅ **Model answer:**
"It shows the isolation story is complete for *business data* — every Order, Product, Customer row is `tenant_id`-scoped and structurally protected — but not yet complete for *external integration credentials*. Today, `WOOCOMMERCE_*` lives once in `.env` for the whole deployment, so every Tenant syncing through `commerce.woocommerce.sync` hits the exact same one configured store — fine for a single-store demo, wrong for a real SaaS deployment where two different Tenants each need to connect their *own* separate WooCommerce store. This is an honestly documented, tracked gap, not a silent one — `ConnectorRegistry`'s own docblock already names 'credential storage' as belonging to a future, fuller Connection Manager, one this codebase hasn't built yet."

🔁 **Likely follow-ups:**
1. "How would you actually fix this?" → A new, tenant-scoped credentials table (`tenant_id` + connector name + encrypted config), with `WooCommerceConfig::fromConfig()` (today's one static entry point) becoming `fromTenant(int $tenantId)` instead — a real, bounded change, not a redesign.
2. "Does this affect the real payment gateways (Zibal/Stripe) too?" → Yes, the same shape — `PaymentGatewayRegistry`'s own registered gateways are configured once per deployment today; file 13 of this handbook covers the payment-gateway architecture in full.

🚩 **Red flags:**
Claiming this platform is "fully multi-tenant" without qualification — a strong candidate names exactly where that claim currently breaks down, rather than treating multi-tenancy as a single yes/no property of the whole system.

---

### Q11: If a real Tenant needed to move into its own physically separate database tomorrow, what would actually have to change — and what wouldn't?

🎯 **What the interviewer is REALLY testing:**
An Architect-level migration plan, grounded in the specific discipline this codebase already enforces — not a vague "we'd shard the database" answer.

✅ **Model answer:**
"Remarkably little of the Domain and Application layers would need to change, and that's the direct payoff of a decision made from day one. Because every Repository interface and every Action already takes `tenant_id` as a plain, explicit `int` parameter (question 4 of this file) rather than resolving a 'current tenant' from a global or a container, the only thing that genuinely needs to change is *which physical connection* an `EloquentXRepository` uses for a given `tenant_id` — a connection-resolver keyed by Tenant, sitting entirely in the Infrastructure layer, deciding 'this Tenant's queries go to `connection: tenant_42`, everyone else stays on `connection: default`.' Nothing above that — no Action, no Domain Entity, no MCP capability — would need to know a physical split happened at all. The one real prerequisite this file already surfaced is question 10's gap: per-Tenant connector credentials would need solving at the same time, since a fully isolated Tenant is exactly the kind of Tenant most likely to also want its own separate WooCommerce/payment-gateway configuration."

🔁 **Likely follow-ups:**
1. "How would you migrate the data itself?" → A one-time export/import of that Tenant's own rows (all `tenant_id`-scoped, so the boundary is already exact), the same kind of bounded, low-risk operation `demo:reset`'s own cascade-delete already proves the schema supports cleanly.
2. "Would this need downtime for that one Tenant?" → A short one, most likely — enough to freeze writes, copy the data, and flip the connection resolver; every *other* Tenant would be completely unaffected throughout.

🚩 **Red flags:**
"You'd have to rewrite most of the Repositories" — that's exactly the outcome this project's explicit-`tenant_id`-parameter discipline was designed to avoid; not recognizing that is missing the whole point of the pattern.

---

### Q12: Of everything this file covered, what's the single biggest thing you'd actually fix before calling this platform "real SaaS-ready"?

🎯 **What the interviewer is REALLY testing:**
A closing, synthesizing, prioritized answer — can the candidate weigh several real gaps against each other, not just list them.

✅ **Model answer:**
"Per-Tenant connector credentials (question 10), ahead of the rate-limit fairness gap (question 9). The reasoning is concrete, not abstract: the moment a second real, paying Tenant wants to connect their *own* WooCommerce store or their *own* Zibal/Stripe account, today's single, deployment-wide `.env` configuration breaks immediately and visibly — that's not a scale problem years away, it's the very next real customer. The rate-limiting gap, by contrast, only matters once a single Tenant is already provisioning enough Agents to meaningfully out-compete another Tenant for the platform's own shared 100-per-minute-per-Agent ceiling — a real but later-stage concern. This is the same prioritization discipline this project applies everywhere else in `HANDOFF.md`'s own technical-debt list (§8): rank by what actually breaks first for a real user, not by which fix is architecturally more interesting."

🔁 **Likely follow-ups:**
1. "What would you fix after that?" → Database-per-tenant itself (question 11) — but only once a real Tenant's own scale or compliance need justifies it, not preemptively.
2. "Is there anything from file 04 you'd add to this list?" → The cross-tenant cache-leak class of bug (file 04, question 3) is already fixed and now has a direct regression test — the honest remaining risk there is the same one everywhere: a future engineer introducing a new global (not tenant-scoped) cache key without knowing that history.

🚩 **Red flags:**
Picking database-per-tenant as the top priority — it's the most architecturally dramatic answer, but not the one that actually blocks the next real customer; a strong answer prioritizes by real, near-term impact.

---

← [CQRS & Read Models](10-cqrs-read-models.md) | Next: [Security](12-security.md) →
