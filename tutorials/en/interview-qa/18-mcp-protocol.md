← [AI Agents & the Orchestrator](17-ai-agents-orchestrator.md) | Next: [Tradeoffs & Failures](19-tradeoffs-failures.md) →

# 18. The MCP Protocol

The full, step-by-step MCP request trace (file 02, question 3), why this API isn't fully RESTful (file 05, question 1), the real v1/v2 versioning contradiction this project caught and corrected (file 05, question 2), input validation via `inputSchema` (file 05, question 3), the MCP Gateway as a deliberate architectural chokepoint (file 02, question 10), and the MCP Gateway's own status as a real Open Host Service — with UCP as its companion Published Language (file 08, questions 5 and 11) — were all already covered from other angles. This file is where the protocol itself gets the direct, standalone treatment: what MCP actually is versus what this platform actually built, the discovery/execution split as a real security boundary, the naming rule's own reject-and-rename history, a genuine registration gotcha, and how five independent SDKs and an entire AI Orchestrator both end up leaning on the exact same, deliberately unglamorous HTTP+JSON contract.

---

### Q1: Is this platform's MCP Gateway literally speaking the official Model Context Protocol wire format, or something else?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate actually knows the difference between "implements a protocol's underlying idea" and "speaks that protocol's literal wire format" — a distinction a lot of people blur when a project's own naming makes it sound like a checkbox.

✅ **Model answer:**
"Something else, and worth being precise about it rather than letting the name do the talking. This platform's own MCP Gateway is a real, ordinary HTTP+JSON API — `POST /mcp/v1/execute` with a Bearer token and a JSON body, `GET /mcp/v1/capabilities` returning a JSON list — not the official Model Context Protocol's own JSON-RPC 2.0 message format over stdio or Streamable HTTP with session negotiation. What this platform genuinely did follow is the *underlying idea* MCP represents as an industry concept: a standard contract for an AI agent to **discover** capabilities and **execute** them without prior, hardcoded knowledge of how the system behind them actually works — the same discover-then-execute shape the official protocol is built around, just carried over HTTP+JSON instead of the official wire format. That's a reasonable, deliberate fit for what this platform actually needs: a stateless, multi-tenant, Bearer-token-authenticated API that's trivially callable with `curl`, testable without any special client library, and portable across five different language SDKs (question 7 of this file) with zero protocol-specific tooling required in any of them."

🔁 **Likely follow-ups:**
1. "Does that mean this platform's own 'MCP' is really just a REST API with a different name?" → Not quite REST either — file 05 (question 1) already covers why: one single execution address for all 127 capabilities, not a resource-per-address REST shape, specifically so dynamic discovery doesn't require a pre-known address map.
2. "Would adopting the literal official wire format ever make sense?" → If this platform needed to plug directly into an MCP-native client expecting that exact transport, yes — today, every real consumer (the five SDKs, this platform's own Agent Orchestrator) speaks HTTP+JSON already, so there's no real, currently-felt need driving that change.

🚩 **Red flags:**
Assuming "it's called MCP" settles the question of what wire format is actually in play — the interesting, correct answer requires actually knowing what the official protocol's wire format looks like and comparing it honestly to what this platform's own routes actually do.

---

### Q2: `GET /mcp/v1/capabilities` says a capability exists. Does that mean a caller is actually allowed to run it?

🎯 **What the interviewer is REALLY testing:**
A precise, real security boundary this platform states directly — Discovery and Execution are two independent checks, and conflating them is a genuine, common mistake.

✅ **Model answer:**
"No, and this platform is explicit about it: discovery is documentation only, real authorization is always, separately, checked at execution time. `GET /mcp/v1/capabilities` returns every capability's own name, description, input/output shape, and required permission — a complete, honest catalog of what the platform *can* do — but seeing a capability listed there is never a guarantee any specific caller is actually allowed to run it. That real check happens exclusively inside `POST /mcp/v1/execute`'s own request lifecycle (file 02, question 3), where `CheckPermissionAction` verifies this specific Agent, through one of its Roles, actually holds the permission this specific capability requires. The two are cleanly separate concerns for a real reason: Discovery has to answer 'what exists on this platform at all' the same way for every caller, while Execution has to answer 'can *you specifically* do this' differently for every caller — collapsing them into one check would mean either hiding real capabilities from callers who can't use them yet (making Discovery caller-specific and expensive) or letting Discovery quietly imply an authorization guarantee it was never designed to make."

🔁 **Likely follow-ups:**
1. "Does `LLMPlanner` see a filtered list, just the capabilities its own Agent can use?" → No — it calls the same `DiscoverCapabilitiesAction` for the platform's full capability list regardless of the requesting Agent's own permissions, meaning an LLM can genuinely plan a step it isn't actually authorized to execute; question 4 of file 17 covers what happens next — a real `403` at execution time, no special-cased bypass.
2. "Isn't listing capabilities a caller can't use a security leak?" → Not by this platform's own design — a capability's name/description/schema being visible is treated as public API documentation, not a secret; the real secret-worthy boundary (can you actually call it) is enforced exactly once, at the one place that matters.

🚩 **Red flags:**
Assuming a capability appearing in Discovery means a caller can safely call it without expecting a possible `403` — exactly the false guarantee this platform's own documentation is careful never to imply.

---

### Q3: Give me the real story behind the "exactly 3 segments" capability-naming rule — not just the rule, but actual names that got rejected and rewritten.

🎯 **What the interviewer is REALLY testing:**
Concrete, memorized familiarity with a structural constraint this project enforces in code (`CapabilityName` itself validates it), not just a paraphrased rule.

✅ **Model answer:**
"`CapabilityName`/`PermissionKey` both require exactly three dot-separated segments — `domain.resource.action` — and this constraint has forced real, specific renames throughout the project's history, not just a hypothetical guideline. `crm.ticket.comment.add` (4 segments) became `crm.comment.create` — 'comment' promoted to its own resource, since a comment genuinely isn't a sub-property of a ticket's own action space. `workflow.create` (2 segments) became `workflow.definition.create` — the implied 'definition' concept made explicit. `commerce.variant.attribute.create` (4 segments) became `commerce.attribute.create`, and `commerce.subscription.plan.create` (4 segments) became `commerce.plan.create` — the same move both times, promoting a middle word into its own top-level resource rather than inventing a more deeply nested naming scheme. The pattern repeats so often it's a named rule of thumb: when a requested name lands on 4 segments, one of the middle words is almost always the one that should get promoted to its own resource; when it lands on 2, the missing concept the request only implied gets made explicit instead."

🔁 **Likely follow-ups:**
1. "Has any module gone through its entire build without hitting this?" → Only Loyalty and Reporting, per this project's own history — WooCommerce, CRM, Finance, Workflows, and Shipping each needed at least one rename, making this one of the most consistently recurring naming gotchas across the whole platform.
2. "Why not just allow a flexible number of segments?" → A fixed, exactly-3 shape is what makes the naming scheme predictable and parseable at a glance (`domain.resource.action`) for every consumer, human or LLM — a variable-length scheme would trade that predictability for accommodating whatever shape a single request happened to propose.

🚩 **Red flags:**
Stating the rule abstractly ("names have to be short") without being able to produce a real, specific rejected name and its actual replacement — the concrete history is what proves this is an enforced constraint, not a style preference.

---

### Q4: A capability can fail with "no execution handler found" even though you're sure it's wired in the ServiceProvider. What's actually going on?

🎯 **What the interviewer is REALLY testing:**
A specific, real, easy-to-hit gotcha this project's own documentation names directly — and the structural reason *why* it exists, not just "remember to do both steps."

✅ **Model answer:**
"Registering a capability's real execution **handler** and registering its **description** (for Discovery, and for the Seeder that feeds it) are two completely independent steps, and it's genuinely easy to do one and forget the other. The handler is a closure registered inside the owning module's `ServiceProvider::boot()` — pure, in-memory, no database involved. The description is a database row, written by that module's own dedicated Seeder (like `CommerceCapabilitiesSeeder`), never registered directly in `boot()` at all. The reason they can't both live in `boot()`: Laravel's `ServiceProvider::boot()` runs *before* `RefreshDatabase` migrates the test database, so anything that needed a real database row to exist wouldn't have one yet at that point in a test's own lifecycle. If a capability 404s with 'no execution handler found' despite looking correctly wired in the ServiceProvider, the real fix is almost always checking whether the test actually seeded the matching Capabilities Seeder — the handler was probably always fine."

🔁 **Likely follow-ups:**
1. "Does Discovery ever fail for the opposite reason — a seeded description with no real handler behind it?" → Yes, structurally possible the same way in reverse — a capability could appear in `GET /mcp/v1/capabilities` yet 500 with a handler-not-found error at execution time, which is exactly why this gotcha is framed as 'two independent registrations,' not 'one registration with two optional parts.'
2. "Why not just have the Seeder register the handler too, so it's one step?" → A handler is pure PHP with zero database dependency; forcing it through the Seeder would mean the in-memory execution path now depends on the database being migrated first, undoing the exact reason handlers live in `boot()` in the first place.

🚩 **Red flags:**
Assuming "wired in the ServiceProvider" is a single, atomic action — missing that this project deliberately splits it into two independent registrations for a real, structural reason (test database timing), not by accident.

---

### Q5: The version-detection logic checks the URL, then an `Accept` header, then a query string — but only the URL tier ever actually engages in production. Why keep the other two at all?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can defend keeping fully-built, fully-tested code paths that never fire in practice — a real, deliberate call, not dead code left behind by accident.

✅ **Model answer:**
"Because 'never engages today' and 'dead code' are two different claims, and this is genuinely the first, not the second. Every real route in this platform already carries an explicit version segment (`/mcp/v1/...` or `/mcp/v2/...`), so the URL tier always wins before the other two are ever consulted — but the header/query fallback tiers are fully implemented and fully tested, kept as real, working forward compatibility for a caller that might someday hit a version-less route, or an intermediary that rewrites URLs but preserves headers. This three-tier design is also exactly what surfaced a real, caught contradiction in this feature's own original spec (file 05, question 2): the stated priority was 'URL beats Header beats Query,' but the spec's own example test expected an `Accept` header to override an already-explicit v1 URL — precisely the kind of hidden, surprising version-switch a trustworthy API can't allow. Catching that meant the header/query tiers exist today exactly as originally specified, minus the one contradiction, not as untested guesswork."

🔁 **Likely follow-ups:**
1. "Is this the same refactor that produced AbstractMCPGatewayController?" → Directly related — before v2 existed, there was only one Controller and no real risk of the four security-critical steps (authenticate/rate-limit/authorize/execute) drifting between two versions; v2's arrival is exactly what turned 'don't duplicate this' from a nice-to-have into a real requirement, which is why the extraction happened at that specific moment, not earlier.
2. "How do you know v1 and v2 actually return identical underlying data?" → A direct test proves it, not an assumption — it calls the same capability through both versions and asserts `v1.data === v2.result`, the exact same underlying payload under two different envelope shapes.

🚩 **Red flags:**
Suggesting the header/query tiers should be deleted since "they never run" — missing that untested-in-production and unused-by-design are different from broken or pointless; these paths are real, verified, working code kept for a genuine forward-compatibility reason.

---

### Q6: The Agent Orchestrator has its own dedicated HTTP routes (`POST /api/agents/{agent_type}`, `GET /api/agents/executions/{id}`) *in addition to* its MCP capabilities. Doesn't that mean the platform has two competing ways to do the same thing?

🎯 **What the interviewer is REALLY testing:**
Recognizing a real, named architectural pattern (a human-facing or specialized surface reusing existing Actions, never re-implementing business logic for a new transport) rather than seeing two routes to the same outcome as inherently duplicative.

✅ **Model answer:**
"Two routes, never two implementations — and that distinction is exactly the point. `POST /api/agents/{agent_type}` and `agent.goal.execute` call the **exact same** underlying Action; `GET /api/agents/executions/{id}` and `agent.execution.get` do too. This is a named, recurring pattern in this project's own architecture: a specialized or human-facing surface always reuses the Actions a module's own MCP capabilities already call, never re-implementing the same business logic a second time for a different transport — the identical discipline the Admin Dashboard follows for every one of its own Controllers, reusing Commerce's/CRM's own Actions directly rather than duplicating their logic behind a session-based UI. The dedicated Orchestrator routes exist because a plain `/api/agents/{agent_type}` URL segment is a more natural, ergonomic shape for a specific, narrower integration use case than a generic `POST /mcp/v1/execute` body — but 'more ergonomic for one use case' never becomes an excuse to fork the actual logic; it's genuinely the same Action, reached through a second door."

🔁 **Likely follow-ups:**
1. "Does this mean every module eventually needs its own dedicated HTTP surface too?" → No — this is specific to a case where a narrower, more specialized transport shape genuinely helps a real caller; most modules have no comparable need and are perfectly well served by the single, general MCP surface alone.
2. "How would you catch it if someone accidentally let the two surfaces drift apart?" → The same test-suite discipline file 05 (question 2) already uses for proving v1/v2 identical behavior — a direct test calling both the MCP capability and the dedicated route and asserting identical results, not just trusting they were built to match once.

🚩 **Red flags:**
Describing the dedicated Orchestrator routes as "a second implementation" or "a workaround" — missing that the entire architectural point is that it's the same Action reached two ways, not competing logic that happens to produce similar results.

---

### Q7: Five official SDKs exist, in five different languages, three of them with zero external dependencies. What does that actually depend on the Gateway itself being built the way it is?

🎯 **What the interviewer is REALLY testing:**
Connecting a concrete, cross-language engineering outcome back to the specific protocol-design choice (question 1 of this file) that made it possible — not just listing the SDKs as a feature.

✅ **Model answer:**
"It depends directly on the Gateway being plain HTTP+JSON, not a protocol needing a specialized client library to speak correctly. Python, Node.js/TypeScript, and Go's own SDKs were deliberately built with zero external dependencies — each one only uses its own language's standard-library HTTP client (Python's own, `fetch`, `net/http`) — something that's only possible because talking to this platform never requires anything more specialized than 'send a JSON body over HTTPS with a Bearer header, parse a JSON response.' All five SDKs, including the two PHP ones, share the exact same contract on purpose: one config object, one client exposing three operations (`discoverCapabilities`/`execute`/`getCapability`), and an error hierarchy matching the server's own HTTP status codes — because the underlying protocol itself is simple enough that every language's own SDK can be a thin, honest wrapper rather than needing real protocol-specific logic of its own. And for any language without an official SDK at all, the documented fallback is genuinely just 'speak HTTP+JSON directly' — a real language gap costs a caller a few hours writing a thin client, not weeks implementing an unfamiliar wire protocol from scratch."

🔁 **Likely follow-ups:**
1. "Why does the PHP SDK need Guzzle when the other three don't need anything?" → PHP, unlike Python/Node.js/Go, has no standard-library HTTP client at all — Guzzle is the one genuinely necessary dependency, not an inconsistency in the zero-dependency philosophy the other three follow.
2. "Is the Go SDK identical in shape to the others?" → Structurally the same three-operation contract, with one deliberate language-idiom difference — every method takes a `context.Context` as its first parameter, Go's own standard idiom for timeouts/cancellation, and errors use Go's native `error` type instead of exceptions.

🚩 **Red flags:**
Treating the five SDKs as five independent engineering efforts — missing that their shared simplicity is a direct, structural consequence of the protocol itself being deliberately unglamorous HTTP+JSON, not a coincidence five separate teams happened to converge on.

---

### Q8: This platform documents two "levels" of interaction — calling a capability directly, and handing over a plain-text goal. When would a real integration actually choose one over the other?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate sees these as two genuinely different, deliberately coexisting interaction shapes — not an old way and a new way, with one destined to replace the other.

✅ **Model answer:**
"Level 1 is calling a specific, already-known capability directly — `commerce.product.search` with a real query — for a caller that already knows exactly what it wants done; this is the shape every one of the five SDKs (question 7 of this file) exposes as `execute()`. Level 2 is handing over a plain-text Goal to `agent.goal.execute` and letting the Orchestrator's own six stages (file 17) figure out which capabilities to call, in what order — for a caller that only has a general intent, not a precise plan. Both are always available at the same time, for the same platform, because they serve genuinely different callers: a simple integration script that just needs 'search for this product' has no reason to pay the real cost (latency, an LLM call, planning overhead) of Level 2 for something it already knows how to ask for directly; a more autonomous caller — itself an AI system, or a human typing a vague request into a chat box — has no way to express 'which exact capability sequence' in the first place, which is exactly the gap Level 2 exists to close. Neither level is positioned as the 'real' or 'advanced' one relative to the other; they're two different shapes of the same underlying capability surface."

🔁 **Likely follow-ups:**
1. "Could a Level 2 Goal ever be rewritten as a fixed Level 1 sequence?" → Often, yes, for a Goal that turns out to be routine — this is exactly what `ExecutionPattern`/Execution Memory (file 17, question 5) is for: a repeated Goal increasingly gets served by a learned, reused plan rather than fresh Level-2 planning every single time, blurring the line in practice without ever removing either level.
2. "Does Level 1 skip authorization the way Level 2 might for an LLM-planned step?" → No — both levels ultimately execute through the identical `CapabilityExecutionService` path (file 17, question 4), so a permission gap produces the same real `403` regardless of which level the call originated from.

🚩 **Red flags:**
Describing Level 2 as a strict upgrade over Level 1, or Level 1 as a legacy path being phased out — missing that this platform deliberately keeps both as permanently valid, differently-suited interaction shapes for genuinely different kinds of callers.

---

### Q9: Walk me through exactly where "does this capability exist" and "can this caller actually run it" get checked, mechanically, in the code.

🎯 **What the interviewer is REALLY testing:**
A precise, mechanical answer building on question 2 of this file — can the candidate name the actual classes involved, not just restate the discovery/execution security principle in the abstract.

✅ **Model answer:**
"`GET /mcp/v1/capabilities` is backed by `DiscoverCapabilitiesAction` — it returns the platform's full, real capability catalog, and notably, this is the same Action `LLMPlanner` itself calls internally when it needs 'every capability the platform has' to plan against, with no permission-based filtering applied at that point for either caller. The real authorization boundary is entirely inside `POST /mcp/v1/execute`'s own request lifecycle (file 02, question 3): after authentication and rate limiting, `CheckPermissionAction` checks the resolved Agent's own Roles against the specific permission the target capability declares it needs — only past that check does `CapabilityExecutionService` ever actually run the handler. This means the exact same Discovery response is handed to every caller and every planning process regardless of what they're individually allowed to do, and the one, singular gate that actually matters sits later, at execution — never duplicated, never re-implemented per caller type, and never skipped for a call that originated from an LLM-planned Goal instead of a direct MCP call."

🔁 **Likely follow-ups:**
1. "Does that mean a caller could probe Discovery to learn about capabilities they're not authorized for?" → Yes, and that's treated as an acceptable, deliberate trade-off — capability names/schemas are public API documentation, not secrets; the real thing worth protecting (actual execution) is protected at the one place that matters.
2. "Why not have `DiscoverCapabilitiesAction` itself take an `AuthContext` and pre-filter?" → It could, but that would mean every discovery call pays a real cost checking permissions for capabilities the caller may never even try to use — the current design keeps Discovery cheap and universal, accepting that a later, real `403` at execution time is a fine, honest way to communicate 'you can't actually do that.'

🚩 **Red flags:**
Assuming `DiscoverCapabilitiesAction` filters its own output by the caller's permissions — missing that this platform deliberately keeps Discovery and Execution's authorization checks completely separate, not layered.

---

### Q10: Why does the MCP Gateway itself contain zero business logic — not even something as small as "is this discount allowed"? What would actually go wrong if it did?

🎯 **What the interviewer is REALLY testing:**
Understanding the real, concrete cost of violating the Gateway's own stated golden rule — not just repeating "the Gateway shouldn't have business logic" as a slogan.

✅ **Model answer:**
"Because the Gateway's entire value is being the one, identical chokepoint (file 02, question 10) every capability passes through — authenticate, rate-limit, authorize, execute, format the response — and nothing else. The moment a real business decision (even something as narrow as 'is this specific discount allowed') lived inside the Gateway itself, that decision would be coupled to every capability that happens to pass through it, rather than owned by the one module that actually understands it. Concretely, it would mean the Gateway now has to know about Commerce's own Discount rules to make that call correctly — a domain-specific dependency inside the one class every single module, business or not, routes through, which is exactly the kind of coupling this platform's entire module-independence discipline (files 02, 08, 14-16) is built to prevent. Business logic belongs inside the relevant module's own Action, which the Gateway only ever calls through `CapabilityExecutionService` without needing to understand what that Action actually decided — the Gateway's job stops at getting a properly authenticated, authorized, validated call to the right handler, never at judging what that handler should do."

🔁 **Likely follow-ups:**
1. "Isn't input validation itself a kind of business logic living in the Gateway?" → A deliberately shallow, generic kind — `MCPRequestValidationService` only checks a field exists and matches a declared primitive type (file 05, question 3), never a real domain rule like 'is this discount percentage actually valid for this Coupon'; that deeper check still lives entirely inside the owning module's own Action.
2. "Has this rule ever been bent the way the module-boundary rule has (Reporting's Conformist exception, file 08 question 9)?" → Not documented as bent anywhere — unlike the Reporting exception, which is a scoped, named departure from one specific rule, 'no business logic in the Gateway' has held as an absolute across every module built on this platform so far.

🚩 **Red flags:**
Treating "keep business logic out of the Gateway" as a style preference rather than a structural requirement — missing the real, concrete coupling cost (the Gateway needing domain-specific knowledge about every module that routes through it) that would follow from violating it even once.

---

### Q11: UCP and MCP are both described as "protocols" in this platform. What's the actual difference in what each one standardizes?

🎯 **What the interviewer is REALLY testing:**
A brief, precise synthesis of two related strategic patterns already covered separately (file 08, questions 5 and 11) — can the candidate state the real, complementary difference concisely, without conflating them.

✅ **Model answer:**
"They standardize two different things, and the names make that easy to blur. MCP (an Open Host Service, file 08 question 11) standardizes the **access mechanism** — how any caller, regardless of what it wants to do, discovers and executes a capability through one shared gateway, instead of every consumer needing a custom, one-off integration. UCP (a Published Language, file 08 question 5) standardizes the **shape of the data** flowing through that mechanism — `UCPProduct` and its siblings are the one normalized commerce model both this platform's own native data and an external system's data (WooCommerce, question 4 of file 08) get translated into, so nothing downstream ever needs to know or care where a given Product actually came from. Put together: MCP is how you *reach* a capability at all; UCP is what the *data* looks like once you're inside one that happens to deal with commerce concepts. A capability like `commerce.product.search` needs both — MCP gets a caller to it in the first place, UCP guarantees the `Product` it returns means the same thing regardless of its real origin."

🔁 **Likely follow-ups:**
1. "Does every capability on the platform involve UCP?" → No — UCP is specifically a commerce-data concern; a capability like `agent.reasoning.explain` (file 17, question 11) returns its own domain-shaped result with no UCP involvement at all, since there's no external-vs-internal commerce-data question to normalize there.
2. "Could a future domain need its own Published Language the way Commerce has UCP?" → Genuinely possible, and the same reasoning would apply — a Published Language is only worth the design cost when a real Domain concept needs to look identical regardless of which of several real sources it actually came from, which is UCP's whole reason for existing in the first place.

🚩 **Red flags:**
Treating UCP and MCP as two names for roughly the same idea — missing that one is about *reaching* a capability and the other is about the *shape of the data* a commerce-related capability happens to hand back.

---

### Q12: Files 17 and 18 together cover the Orchestrator and the Gateway. Looking at both, what's the one thing that actually made Phase 6 possible without ever touching either the Gateway or the Core?

🎯 **What the interviewer is REALLY testing:**
A closing, Part-F-wide synthesis — pulling file 17's own closing argument (Phase 6 needed zero Core changes) together with this file's own protocol-level detail into one concrete, mechanical explanation of *how* that was actually possible, not just that it happened.

✅ **Model answer:**
"The capability model itself being a genuinely open-ended, name-addressable registry rather than a fixed, hardcoded list is the real mechanism underneath file 17's own closing claim. Every capability — whether it's `commerce.product.search`, wired in Phase 2, or `agent.goal.execute`, wired in Phase 6 — reaches the exact same `CapabilityHandlerRegistry`, gets the exact same authenticate → rate-limit → authorize → execute treatment inside `AbstractMCPGatewayController`, and shows up in the exact same `GET /mcp/v1/capabilities` response shape. The Gateway never had to learn what an 'Agent Orchestrator' even was to serve its capabilities correctly — from the Gateway's own point of view, `agent.goal.execute` is structurally indistinguishable from any Commerce capability registered four phases earlier. This is exactly what question 6 of this file's own dual-surface pattern depends on, and exactly what file 17 (question 9) means by the Orchestrator depending on Core's own general-purpose mechanisms rather than needing anything Core-specific built for it: the protocol itself was already general enough, from Phase 1 onward, that an entirely new *kind* of module — one that plans and executes rather than owning a business domain — could register into it as just another named capability, using infrastructure that had no idea, and needed no idea, that AI orchestration was ever coming."

🔁 **Likely follow-ups:**
1. "Is there a limit to what kind of module this registry-based openness could accommodate?" → Nothing this platform's own history has hit yet — the same open registration shape that absorbed 10 business modules and a fundamentally different orchestration layer with zero Gateway changes gives no structural reason to expect a materially different future module would need one.
2. "Does this mean the Gateway's own code has grown more complex over time to accommodate more kinds of capabilities?" → No, and that's the actual point — `AbstractMCPGatewayController`'s own five-step pipeline is identical today to what it was before Phase 6 began; the growth happened entirely in the number of registered capabilities, never in the mechanism registering and serving them.

🚩 **Red flags:**
Answering with "good planning" or "solid architecture" in the abstract — the strong answer names the actual mechanism (a name-addressable capability registry the Gateway treats uniformly) that made the abstract claim concretely true, not just restates that it was true.

---

← [AI Agents & the Orchestrator](17-ai-agents-orchestrator.md) | Next: [Tradeoffs & Failures](19-tradeoffs-failures.md) →
