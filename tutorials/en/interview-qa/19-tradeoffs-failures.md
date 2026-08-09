← [The MCP Protocol](18-mcp-protocol.md) | Next: [Scaling & Redesign](20-scaling-redesign.md) →

# 19. Tradeoffs & Failures

Files 01 through 18 already built the full, detailed case for most of this platform's biggest technology decisions — the modular monolith (file 02), light CQRS (file 10), sync-over-async (file 09), HTTP+JSON over the literal MCP wire format (file 18) — and already told several of its best real bug stories in full technical depth (`OpenRouterClient`'s `base_uri` bug, file 06/17; the cross-tenant cache leak, file 04; `CheckInventoryAction`'s re-check bug, file 16). This file isn't where those get re-derived from scratch. It's the compressed, interview-paced version — the "why not X" question asked directly, the debugging *method* underneath several separate war stories named as one repeatable thing, and a few genuinely new, still-open items this handbook hasn't touched yet: unverified LLM providers, missing Dashboard pages, and the honest production risks nobody's forcing a fix on yet.

---

### Q1: Why not build this as microservices from the start, instead of a modular monolith?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can compress an already-strong architectural case into a tight, confident answer under real interview time pressure — plus whether they know the strongest, most concrete proof this project has that the decision actually held up.

✅ **Model answer:**
"Because a modular monolith gets the same real boundary discipline microservices give you — a module only ever depends on another through a published interface, never its concrete Model — without paying the operational cost (multiple databases, network coordination, distributed tracing) before any real, measured need for it existed (file 02, question 1). The strongest proof this decision actually held isn't theoretical — it's file 18's own closing argument: when Phase 6 added an entirely new *kind* of module, one that plans and executes rather than owning a business domain, it registered into the exact same capability registry every earlier module already used, with zero changes to the Gateway or Core. If the module boundaries had been fake — just folder organization with real coupling hiding underneath — Phase 6 would have needed to reach into Core to make that work. It didn't. That's the actual evidence a modular monolith's boundaries were real, not just an aspiration stated in a README."

🔁 **Likely follow-ups:**
1. "Which module would split out first if this ever did move to microservices?" → The Agent Orchestrator — LLM calls carry a genuinely different latency/load profile than Commerce, and it already only talks to the rest of the system through Core's own general-purpose mechanisms (file 02, question 1's own follow-up).
2. "What's the real cost you're avoiding today by not splitting?" → Distributed observability — tracing one request across several services is free today, since everything runs in one process; that's the concrete cost deferred, not avoided forever.

🚩 **Red flags:**
Reciting "monolith first, split later" as a slogan without being able to name the actual, concrete evidence (file 18's Phase 6 registration story) that proves the boundaries in *this* project were real rather than aspirational.

---

### Q2: Why not build a full CQRS system with a genuinely separate read database, instead of Reporting's own in-process Query Builder exception?

🎯 **What the interviewer is REALLY testing:**
The same compressed-confidence test as question 1, for a second major "why not the textbook version" decision — can the candidate state the real trade-off without re-deriving the whole CQRS spectrum from scratch.

✅ **Model answer:**
"Because the full form's real cost — a genuine sync mechanism between two physical databases, and the eventual consistency that comes with it — has no real, measured justification yet; this platform's light version (one shared database, a fully separate *read path* through five dedicated Query Builders) already gets CQRS's main benefit, an optimized read model with no Entity-reconstruction overhead, at a much lower operational cost (file 10, question 1). The honest limitation this trade accepts: Reporting's heavy aggregate reads can, in principle, affect Commerce's own write performance, since they share one database — a real, named cost, not a hidden one. If this platform ever needed the full form, file 10 (question 12) already names exactly where to start: point Reporting's own existing Query Builders at a Read Replica, since that module's boundary is already drawn cleanly enough to make that a connection-source change, not a rewrite."

🔁 **Likely follow-ups:**
1. "How would you know it's actually time to make that move?" → When Reporting's/Analytics' reads genuinely and measurably start hurting Commerce's own write performance — a real, observed threshold, not a pre-scheduled milestone (file 10, question 10).
2. "Isn't Redis caching already a step toward that?" → A related but different step — cache is a temporary layer on the same database; a Read Replica is a fully independent, always-current data source (file 10, question 10's own distinction).

🚩 **Red flags:**
Treating "we don't have real CQRS" and "we don't have CQRS at all" as the same claim — missing that this platform has a real, working light version with a clearly named limitation, not an absence of the pattern.

---

### Q3: Why does this platform run its Domain Events synchronously instead of through a real message queue?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate treats "async is always better" as a fact or a trade-off — a common, telling mistake this exact question is designed to surface.

✅ **Model answer:**
"Because synchronous, in-process events give this platform real, immediate consistency today with zero extra infrastructure — no queue, no worker fleet, no distributed tracing needed to debug one request — and nothing about this platform's current scale has made that cost worth paying yet (file 09, questions 2 and 3). The honest trade this accepts: if a listener is slow, the whole HTTP response gets slow with it, and today's genuine immediate consistency would become genuine eventual consistency the moment this changes, opening a real time window between 'the order was placed' and 'points were awarded' that would need explicit handling. Moving to real async would introduce three concrete new risk classes this platform doesn't have today: the dual-write problem (needing a real Outbox pattern), the loss of an exactly-once guarantee (every listener needing to become genuinely idempotent), and that same eventual-consistency window becoming real instead of theoretical (file 09, question 12) — none of them free, all of them currently deferred rather than solved."

🔁 **Likely follow-ups:**
1. "Which listener would be the first real candidate to convert?" → `WebhookSender`, since it's the one listener already talking to a genuinely unreliable external system — the same answer file 03 (question 7) and file 09 (question 2) both independently arrive at.
2. "Is there anywhere in this platform that already has real eventual consistency today?" → Queued Jobs, like a CSV import — the client explicitly polls for status after the initial HTTP response returns, the one place this platform already accepts a real time window (file 09, question 3).

🚩 **Red flags:**
"You should always build async from day one" — ignoring that async carries a real, non-optional cost (a queue, a worker, three new bug classes) that has to be justified by an actual, measured need, not assumed as a default best practice.

---

### Q4: Why didn't you build this on the official Model Context Protocol's own wire format, or on GraphQL, instead of a custom HTTP+JSON gateway?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can defend a "we followed the idea, not the letter" decision against two different, real alternatives in one tight answer — a genuinely common pushback this exact framing invites.

✅ **Model answer:**
"Two separate 'why not X' questions, both with the same underlying answer: this platform optimized for what its own real audience and real requirements actually needed, not for matching a spec's exact letter. Against the official MCP wire format (JSON-RPC over stdio/Streamable HTTP): this platform needed a stateless, multi-tenant, Bearer-token-authenticated API, trivially callable with `curl`, portable to five independent language SDKs with zero protocol-specific tooling in any of them — HTTP+JSON delivers all of that directly, while a specialized wire format would have added real integration cost with no concrete benefit this platform has actually needed yet (file 18, question 1). Against GraphQL: it solves the same 'dynamic discovery' problem MCP solves, just through a different mechanism (schema introspection) — but MCP is the deliberately-followed, emerging industry standard specifically for agent-oriented communication, not a proprietary invention this platform reached for instead of an established alternative (file 05, question 1). Both decisions optimize for the same thing: solving the real problem (an AI agent discovering and executing capabilities without prior, hardcoded knowledge) with the simplest mechanism that actually delivers it."

🔁 **Likely follow-ups:**
1. "Would adopting the literal official wire format ever make sense?" → If a real, MCP-native client expecting that exact transport needed direct integration — today, every real consumer already speaks HTTP+JSON, so nothing is currently driving that change (file 18, question 1).
2. "Doesn't GraphQL's introspection solve discovery better than a REST-ish endpoint?" → A fair, debatable point on pure mechanism — but the deciding factor wasn't 'which discovery mechanism is technically superior in isolation,' it was following the standard this platform's own actual audience (AI agents built against the MCP concept) already expects.

🚩 **Red flags:**
Answering only one of the two alternatives (official MCP format or GraphQL) when asked "why not X" phrased broadly — missing that this project has two, related-but-distinct real answers ready, not one generic "we chose simplicity" deflection.

---

### Q5: Why isn't `PointTransaction` — an append-only ledger — actually Event Sourcing?

🎯 **What the interviewer is REALLY testing:**
The sharpest, most commonly-confused "why not X" question in this whole handbook — does the candidate know the precise, structural difference between a Ledger pattern and real Event Sourcing, not just that both involve an append-only log.

✅ **Model answer:**
"Because `LoyaltyAccount.current_balance` is maintained directly and independently — `earn()`/`redeem()` both append a new `PointTransaction` *and* separately update `current_balance` in the same operation — and that number is never derived by replaying the ledger. Real Event Sourcing would be exactly reversed: no independent `current_balance` column at all, always recomputed from a full replay of every historical event. This platform deliberately keeps both a full historical ledger (for reporting and auditing) and an independent, fast current-state field (for ordinary reads) rather than picking one (file 09, question 9) — the real, accepted cost is that the two could theoretically drift apart if a bug only updates one of them, a risk true Event Sourcing structurally eliminates by having exactly one source of truth. The deeper reason this platform isn't Event Sourced at all: `Order` and every other Aggregate store their *current* state directly in a real table row; a Domain Event like `OrderWasPlaced` is a side notification dispatched after that state was already durably saved, never the source of truth itself (file 09, question 8) — delete every listener today and zero data is lost."

🔁 **Likely follow-ups:**
1. "Where would real Event Sourcing actually have been worth the complexity?" → Somewhere the complete change history itself has real, standalone business value, like a genuine accounting ledger — this platform hasn't hit a real need at that level yet (file 09, question 8).
2. "Is `WorkflowLog` the same shape?" → Yes, identically — a full historical run log, while `Workflow`'s own current `active`/`inactive` state lives directly on the Aggregate, never computed from the log (file 09, question 9).

🚩 **Red flags:**
Claiming "we use Domain Events, so this is Event Sourced" — the single most common version of this mistake, and exactly the one this platform's own documentation is careful to correct directly rather than let stand ambiguous.

---

### Q6: `Money` is duplicated, nearly identically, across Commerce, Finance, and Shipping. Why not put it in a shared package instead?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can defend deliberate duplication as a real trade-off against the obvious-sounding "just share it" alternative, including naming the actual cost this decision accepts.

✅ **Model answer:**
"Because a shared kernel for `Money` would mean every module depending on one shared class outside its own Domain layer — the exact same coupling the module-independence rule exists to prevent, just relocated from 'depending on another module's concrete class' to 'depending on a shared one that isn't really neutral once several modules start needing slightly different behavior from it over time' (file 07, question 9; file 08, question 2). The real, accepted cost: roughly 40 lines of near-identical logic duplicated per module, and if a real bug surfaces in the formula, it has to be fixed in each independent copy — a genuine, named trade-off, not an oversight. This project has never built a shared kernel for anything, including something this small and stable, because the version-coordination cost across modules (what happens when one module needs `Money` to change in a way another doesn't want?) was judged higher than the cost of duplicating 40 stable lines a few times."

🔁 **Likely follow-ups:**
1. "When would a shared kernel actually be justified?" → When a concept is foundational and stable enough that it practically never needs to change, and the coordination cost across versions genuinely outweighs duplication's cost — something this project hasn't run into yet (file 07, question 9).
2. "Is this the same reasoning behind CRM/Finance each throwing their own `CustomerNotFoundException`/`OrderNotFoundException` instead of reusing Commerce's?" → The identical reasoning, one layer over — never depend on another module's concrete class, even a small, stable-looking one, in exchange for a small amount of real, accepted duplication (files 15/16's own repeated confirmations of this rule).

🚩 **Red flags:**
Calling the duplication "obviously a mistake" without engaging with the real coupling cost it avoids — the strong answer names the actual trade-off being made, not just asserts DRY should have won.

---

### Q7: Walk me through your actual debugging method — not one specific bug, your general approach — using real examples from this project.

🎯 **What the interviewer is REALLY testing:**
Whether several separately-told war stories actually reflect one repeatable method, or were each solved ad hoc — this is the "connect the dots" version of a question the candidate has already answered piecemeal elsewhere in this handbook.

✅ **Model answer:**
"One consistent method shows up across every real bug this platform's own documentation tells in detail: never conclude 'it's a code bug' from an ambiguous signal alone — isolate the actual cause before assuming which layer it's in. When `OpenRouterClient`'s first live call failed with a `403`, the fix wasn't guessed from the error alone — the actual RFC 3986 mechanic (Guzzle replacing a `base_uri`'s own path instead of appending to it) was identified and confirmed before writing the fix (file 06, question 8; file 17, question 3). When a live Zibal call timed out, instead of assuming the gateway code itself was broken, a bare `curl` with zero application code involved was run against the exact same host — proving the *environment's* own network couldn't reach it, not `ZibalPaymentGateway` (file 13, question 10). When a cross-tenant cache leak was suspected, the test that caught it didn't just check 'the right tenant's data comes back' — it explicitly tried accessing a real record belonging to a *different* tenant by guessing its id, the only test shape that can actually catch that specific class of bug (file 04, question 3; file 06, question 6). The common thread: every one of these isolates the *specific* layer at fault — application code, environment, or test design itself — before writing a fix, rather than patching the first plausible-looking cause."

🔁 **Likely follow-ups:**
1. "Has this method ever been wrong or wasted time?" → An honest cost worth naming — isolating the real cause (like the Zibal `curl` test) takes real extra time versus just guessing and trying a fix; the trade is fewer wrong fixes and fewer bugs that come back later misdiagnosed.
2. "Does this method scale to a bug you can't reproduce locally?" → The `CheckInventoryAction` re-check bug (file 16, question 9) is exactly that case — discovered indirectly, through a new test needing specific quantities to cross a real threshold, then documented with the exact numbers that trip it rather than reproduced from scratch each time.

🚩 **Red flags:**
Describing debugging as "I read the error and fix what it points to" — missing the actual, repeated discipline this platform's own real bug stories demonstrate: confirming which layer is actually at fault before trusting an ambiguous first signal.

---

### Q8: OpenRouter has been live-verified with real credentials. OpenAI and Claude haven't. Is that a real gap?

🎯 **What the interviewer is REALLY testing:**
A genuinely new, honestly-named limitation this handbook hasn't covered yet — can the candidate state a real gap plainly without either downplaying it or overstating the risk.

✅ **Model answer:**
"Yes, a real and directly acknowledged one, not hidden behind 'we support three providers.' `OpenRouterClient` has actually been run against a real network with real credentials — that's exactly the run that caught the real `base_uri` bug (file 06, question 8; file 17, question 3), genuine proof its request-building and response-parsing logic work against a real server, not just a `MockHandler`. `OpenAIClient` and `ClaudeClient` are real, complete, Guzzle-backed implementations behind the same `LLMClientInterface` (file 03, question 1) — but neither has been exercised against a real OpenAI or Claude endpoint with real credentials; every test for both today runs against simulated HTTP only. The honest risk this leaves: the exact class of bug OpenRouter's own live run caught (a subtle, provider-specific request-construction quirk no mock would ever surface) could still be lurking, undiscovered, in either of the other two clients, since the one method proven to catch that class of bug hasn't been applied to them yet."

🔁 **Likely follow-ups:**
1. "Why didn't the OpenRouter fix get automatically assumed safe for the other two?" → Structurally, the same underlying Guzzle `base_uri` fix *was* applied preemptively to `OpenAIClient`/`ClaudeClient` (the same discipline applied to `ZibalPaymentGateway`/`StripePaymentGateway`, file 06 question 8) — but that only closes the *one specific* bug class already found; it doesn't stand in for a full live verification the way OpenRouter actually got.
2. "What would it take to close this gap for real?" → A real API key for either provider and one live call, the exact same low-cost verification OpenRouter already had — genuinely cheap once a key exists, not a real engineering project.

🚩 **Red flags:**
Assuming "we support OpenAI and Claude too" implies they've been verified the same way OpenRouter has — missing the real, honestly-named difference between 'implemented and mock-tested' and 'implemented and live-verified.'

---

### Q9: None of Phase 5's advanced-commerce features or Phase 6's AI Orchestrator stages have a real Admin Dashboard page. Why, and what's the actual recommended fix?

🎯 **What the interviewer is REALLY testing:**
Another genuinely new, current gap — and whether the candidate can state the real, considered recommendation for closing it rather than a generic "just build the pages."

✅ **Model answer:**
"Every Action behind warehouses, product variants, bulk operations, discount rules, and subscriptions (Phase 5), and every one of the five Phase 6 AI Orchestrator stages — goals/executions, agent profiles, memory and learning, multi-agent collaboration, reasoning — is real, tested, and MCP-reachable; none of them has a corresponding Blade page or Dashboard controller. This isn't an oversight discovered late — the Dashboard has always been built as a thin `Interfaces` layer that reuses existing Actions rather than a parallel implementation (the same discipline every Dashboard Controller already follows, file 08 of the main tutorial series' own pattern #19), so adding these pages later is real, additive, low-risk work, not a redesign. The actually-considered recommendation, not a default 'add five small pages': a single, unified `/dashboard/agents` page covering all five Phase 6 areas together, rather than five separate, disconnected ones — because an operator reasoning about 'how is my AI Orchestrator doing' genuinely wants goals, memory, collaboration, and reasoning traces in one connected view, not five tabs that don't talk to each other."

🔁 **Likely follow-ups:**
1. "Why prioritize a unified page over shipping five smaller ones faster?" → Because five disconnected pages would need to be re-unified later anyway once someone actually tries to use them together — designing the real shape first, even if it ships slightly later, avoids that rework.
2. "Is this the same 'built but not surfaced' shape as unwired MCP capabilities (files 15/16's own repeated pattern)?" → A related but distinct gap — those are real capabilities missing only their MCP registration; this is real capabilities missing only a human-facing view, the identical underlying discipline (don't build a page until it's actually needed) applied to a different surface.

🚩 **Red flags:**
Assuming Phase 5/6 features are somehow less complete than earlier modules because they lack Dashboard pages — missing that every one of them is fully built and MCP-reachable; only the human-facing view is the real, honestly-named gap.

---

### Q10: This platform has no customer-facing checkout page. Isn't that a critical missing piece for an e-commerce platform?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can defend a confirmed scope boundary under a pointed, skeptical framing without either caving to "yes it's incomplete" or getting defensive about a real, honest gap.

✅ **Model answer:**
"No, because this was never positioned as a customer-facing e-commerce platform in the first place — its entire public surface is the MCP Gateway plus the Admin Dashboard, both aimed at an AI agent or an internal operator, never a shopper with a browser (file 13, question 12; file 01, question 4's own 'audience' distinction). `commerce.payment.initiate` already returns a real, working `redirect_url` — the actual missing piece is a frontend that would put that URL in front of a real buyer, something explicitly out of scope for this platform's own stated identity as infrastructure between AI agents and business systems, not a storefront. This was confirmed as in-scope reasoning with the user before the payment-gateway work even started, the same way every genuine scope boundary in this project gets raised rather than silently assumed (file 13, question 12) — and it's named directly as the natural next step for whoever builds the layer on top, not hidden as an accidental gap."

🔁 **Likely follow-ups:**
1. "Would you build a reference checkout page yourself?" → Only if asked — a full checkout UI is exactly the kind of thing a real business built *on* this platform would own, not something the infrastructure layer itself needs to provide (file 13, question 12).
2. "How would a real frontend actually use this today?" → Call `commerce.payment.initiate`, get `redirect_url` plus `tracking_reference`, send the buyer there directly, then use that same `tracking_reference` with `commerce.payment.inquiry` to show the buyer their real order status (file 13, question 12).

🚩 **Red flags:**
Treating "no checkout page" as an unfinished feature rather than checking whether it was a confirmed scope decision first — exactly the "audit before concluding something's missing" discipline this handbook keeps testing throughout Part E.

---

### Q11: Name a real production risk you know about and haven't fixed. Why is it still open?

🎯 **What the interviewer is REALLY testing:**
Comfort naming a genuine, unresolved risk without either panicking about it or dismissing its real severity — a direct test of engineering honesty under a pointed question.

✅ **Model answer:**
"Two real ones, both explicitly documented rather than quietly known only to whoever last touched them. First: `DB_PERSISTENT_CONNECTIONS` deliberately defaults to `false`, because enabling it in a multi-tenant application carries a genuine data-leak risk between requests sharing a persistent connection — the performance benefit was judged not worth that risk without real, careful measurement first, so it stays off by default rather than being enabled and hoped-safe (file 11's own coverage of this exact risk). Second: WooCommerce connector credentials are configured per-deployment today, not per-tenant — meaning a real, multi-tenant SaaS deployment of this platform would need real per-tenant credential management built before that connector could safely serve multiple, mutually-untrusting tenants at once (file 11's own documented gap). Neither is fixed because neither has a real, current deployment pressuring it — the honest reason these stay open isn't 'we forgot,' it's 'nobody has yet needed the specific deployment shape that would make ignoring it unsafe.'"

🔁 **Likely follow-ups:**
1. "What would you actually measure before enabling persistent connections?" → Real connection-pool exhaustion under this platform's own actual concurrent load — the risk is theoretical until measured against a real traffic pattern, not something to accept or reject on intuition alone.
2. "Is the default admin credential the same category of risk?" → A related, smaller one — the seeded `admin@opencommerce.test` account must be changed or removed before any real deployment, the same 'known, named, not yet forced to be fixed' shape (file 12's own coverage of this gap).

🚩 **Red flags:**
Either claiming "there are no known open risks" (implausible for any real system, and specifically contradicted by this project's own documentation) or being unable to name a specific one with a real, considered reason it's still open — both signal the risk isn't actually understood, just vaguely gestured at.

---

### Q12: Every "why not X" answer in this file ends up naming a real, accepted cost rather than claiming the alternative was simply wrong. Is that a coincidence?

🎯 **What the interviewer is REALLY testing:**
A closing, whole-file synthesis — does the candidate see the one discipline underneath eleven separately-argued decisions, or just eleven unrelated justifications.

✅ **Model answer:**
"Not a coincidence — it's the same discipline this entire handbook keeps testing, stated once, directly. None of these decisions are framed as 'the alternative was bad' — a modular monolith doesn't mean microservices are wrong, sync events don't mean async is wrong, duplicating `Money` doesn't mean shared kernels are wrong. Every one names the real, specific cost the chosen path accepts (Reporting's reads can affect Commerce's writes; a slow listener slows the whole request; a `Money` bug has to be fixed in three places) and the real, specific cost the alternative would have imposed instead (distributed observability with no current need; three new async bug classes; a shared dependency that isn't neutral once modules diverge). That's the actual difference between a reasoned trade-off and a rationalization — a rationalization defends the choice already made; a reasoned trade-off can state exactly what was given up, on both sides, and would revise the decision the moment the real, measured need for the alternative actually showed up. Every technology decision in this file is written to survive being asked 'but what did that cost you' — because an honest answer to that question was already part of making the decision, not invented afterward to defend it."

🔁 **Likely follow-ups:**
1. "Has any of these decisions actually been revisited since?" → Not yet, in this project's own real history — but the criteria for revisiting each one (a measured performance threshold, a real deployment shape, a genuine multi-provider LLM need) are already named directly in this file and file 10/09/18's own deeper coverage, not left as a vague 'someday.'
2. "Is this the same discipline as HANDOFF's own documented-technical-debt approach?" → The identical discipline, one layer up — file 01 (question 9) already covers it for individual technical-debt items; this file applies the same honesty to whole architectural decisions, not just leftover implementation details.

🚩 **Red flags:**
Summarizing this file as "we made good choices" — missing that the real, interview-worthy point is *how* each choice is defended: by naming its actual cost, not by declaring the alternative inferior.

---

← [The MCP Protocol](18-mcp-protocol.md) | Next: [Scaling & Redesign](20-scaling-redesign.md) →
