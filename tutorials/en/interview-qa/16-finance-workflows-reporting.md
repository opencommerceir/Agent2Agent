← [CRM & Loyalty](15-crm-loyalty.md) | Next: [AI Agents & the Orchestrator](17-ai-agents-orchestrator.md) →

# 16. Finance, Workflows & Reporting

`PointTransaction`/`WorkflowLog`'s own Ledger-vs-Event-Sourcing distinction (file 09, question 9), the Customer-Supplier direction behind `TaxRateProviderInterface` and its `NullTaxRateProvider` fallback (file 08, question 6), the exact `ServiceProvider` boot-order mechanics that let Finance rebind that same interface (file 02, question 8), `Money`'s own deliberate duplication across Commerce/Finance/Shipping (file 07, question 9; file 08, question 2), `InventoryLowListener`'s own cross-module dependency shape (file 09, question 11), `WorkflowRule`'s own no-independent-id shape (file 07, question 7), and Reporting's entire CQRS/read-model architecture — `Report`/`ReportResult`, the five Generators, KPI caching, the `expires_at` gap, Analytics' own Conformist chain (file 10, questions 3 through 9) — were all already covered from other angles, several of them in real depth. This file is where Finance and Workflows — the two Phase-3 Domain Modules never walked through directly as their own subject — finally get that treatment: a real, three-way tax-fallback story, an honestly modeled-but-unreachable Invoice status, the platform's first real cross-module Domain Event Listener, and a genuine pre-existing bug Workflows' own end-to-end test was the first thing to actually surface.

---

### Q1: Walk me through `CreateInvoiceAction` — from a real Order to a persisted Invoice. How many different places in this platform can decide a tax rate, and why aren't they unified into one?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can trace a real cross-module flow end to end, and specifically whether they know this project has **three** deliberately separate tax-fallback chains rather than one shared mechanism — a detail HANDOFF itself warns not to conflate.

✅ **Model answer:**
"`CreateInvoiceAction` takes an `order_id`, resolves the real `Order` through Commerce's own published `OrderRepositoryInterface`/`ProductRepositoryInterface` — never Commerce's Model — and freezes one `InvoiceItem` per real `OrderItem`, the same Immutable-Items shape `Order`/`OrderItem` already established (file 07, question 2). The genuinely interesting part is tax: this platform has **three** independent, deliberately non-unified fallback chains, not one. `CommerceTaxRateProvider::getRatePercent()` — the one Commerce's own checkout calls through `TaxRateProviderInterface` — tries the given region, then `TaxRegion::default()`, then returns null, which Commerce's own Actions interpret as 'fall back to the old hardcoded 9%.' It never throws. `CreateInvoiceAction`'s own inline fallback tries the same two steps but lands on **zero tax**, never 9%, because that hardcoded percentage is Commerce's own pricing policy, not something Finance's invoicing logic owns or should silently borrow. And `CalculateTaxAction`, behind `finance.tax.calculate`, has no fallback at all — an unconfigured region is a real `TaxRateNotFoundException`, a 404, full stop, for a caller that named a specific region and wants a real answer or an explicit failure. Three genuinely different callers, three genuinely different acceptable failure behaviors — unifying them would mean picking one policy for callers that actually need three different ones."

🔁 **Likely follow-ups:**
1. "Why does `CreateInvoiceAction`'s own fallback land on zero instead of 9%?" → Because 9% is a Commerce pricing decision baked into `CalculatePricingAction`/`ProcessPaymentAction` specifically — Finance silently reusing it would mean one module's pricing policy leaking into another module's invoicing policy through a shared magic number, not a real, deliberate decision either module actually made.
2. "Could a future stage unify all three?" → Nothing structurally prevents it, but HANDOFF explicitly flags `finance.tax.calculate`'s strict behavior and `CommerceTaxRateProvider`'s graceful one as 'easy to conflate' — a warning aimed at exactly that temptation, not an invitation to act on it without a real reason.

🚩 **Red flags:**
Assuming there's one shared tax-fallback mechanism reused everywhere — missing that this project deliberately keeps three, each tuned to what its own specific caller actually needs when a rate is missing.

---

### Q2: `TaxRegion::default()` is a real, first-class region, not a magic string. And Commerce has its own `TaxRate`, completely unrelated to Finance's. Walk me through both.

🎯 **What the interviewer is REALLY testing:**
Two related but genuinely distinct "same name, different thing" facts — can the candidate keep a documented region fallback and a documented naming coincidence straight, rather than blending them into one vague answer.

✅ **Model answer:**
"`TaxRegion::default()` is the literal string `\"DEFAULT\"` — a real, tenant-registered region like any other, not a hardcoded special case in code. A tenant calls `finance.tax.create` with `region: \"DEFAULT\"` to register their own fallback rate exactly the same way they'd register `US-CA`; the only thing special about it is that every fallback chain in this file's own question 1 checks for it by name when a more specific region doesn't match. Separately, and easy to confuse with it: Commerce has its **own** `TaxRate` — a transient, 0-100 float calculation input to `PricingService`, never persisted — while Finance's `TaxRate` is a real, tenant-scoped, persisted Entity (`TaxRegion` + an integer `ratePercentage`, stored as percentage×100). The two share a name purely by coincidence, exactly the same kind of cross-module name clash file 08 (question 2) already covers for `Default`/`TaxRegion::default()` itself — never interchangeable, never the same class, and confusing the two is a real, named risk HANDOFF calls out directly."

🔁 **Likely follow-ups:**
1. "Why store `ratePercentage` as an integer, percentage×100, instead of a float?" → The same 'never let money-or-rate-shaped precision live in a float' discipline file 13 (question 1) already covers for currency amounts, applied here to a rate instead of an amount.
2. "Does `TaxRegion::default()` ever get created automatically?" → No — it's only ever a real row because some tenant explicitly registered it through `finance.tax.create`; a tenant that never configures a `DEFAULT` region simply has no matching fallback to find.

🚩 **Red flags:**
Treating Commerce's `TaxRate` and Finance's `TaxRate` as the same concept just because they share a name — missing that one is a transient calculation input and the other is a real, persisted, tenant-owned record, with zero code path connecting them.

---

### Q3: `CreateInvoiceAction` throws Finance's own `OrderNotFoundException` on an unknown `order_id`, never Commerce's. Is this the same rule file 15 already covered for CRM?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate recognizes a rule repeating for a further, independent time as stronger evidence of a real architectural discipline — not weaker, redundant coverage.

✅ **Model answer:**
"The exact same rule, independently applied again. `CreateInvoiceAction` injects Commerce's `OrderRepositoryInterface` to validate the `order_id` exists, and on a miss throws Finance's **own** `OrderNotFoundException` — a separate class from Commerce's own version, mapped to the identical 404, never Commerce's concrete exception propagating through. It wasn't in the original request either — added unprompted for the same reason CRM's `TagNotFoundException` was (file 15, question 11): a real 404 for a missing cross-module id is an established convention every other lookup in this codebase already follows, so skipping it here would mean either breaking that convention or letting a raw foreign-key-shaped failure surface ugly. What's worth noticing isn't a new mechanism — it's that a completely different module, built independently, reached for the identical shape without needing to re-derive it, because file 15 (question 12) already covers *why*: the pattern was proven and documented by CRM/Loyalty first."

🔁 **Likely follow-ups:**
1. "Does Finance ever import Commerce's `Order` Eloquent Model directly, the way CRM never touches Commerce's Model either?" → Never — `InvoiceRepositoryInterface`'s own Eloquent implementation has no relation to Commerce's Order Model at all, the identical Infrastructure-layer discipline file 15 (question 3) already covers for CRM's Tag pivot.
2. "Is this rule ever violated inside Finance itself?" → No documented instance — every Finance Action that needs to confirm another module's entity exists follows this same shape without exception.

🚩 **Red flags:**
Describing this as "the same example already covered" and stopping there — missing that the real interview-worthy point is *independent repetition*, not just recollection of a fact already stated once elsewhere.

---

### Q4: `InvoiceStatus` models `draft`/`issued`/`paid`/`cancelled` — but only `Draft → Issued` is actually reachable. Is that a bug?

🎯 **What the interviewer is REALLY testing:**
Recognizing a real, honestly documented "modeled but unreachable" gap — the same shape this handbook keeps testing elsewhere — and being able to name the real architectural opportunity it points toward, not just the gap itself.

✅ **Model answer:**
"Not a bug — a real, directly named limitation. `InvoiceStatus` models all four states, but `Invoice::issue()` is the only transition method that exists anywhere in the codebase; no `MarkInvoicePaidAction` or `CancelInvoiceAction` was ever built. `Paid` and `Cancelled` are real enum values with zero code path that can ever produce them — the identical 'modeled but not all reachable yet' shape `RewardType::FreeProduct` (file 15, question 8) and `TransferStatus::InTransit` (file 14, question 7) already carry, just for a status enum instead of a reward type or a transfer state. What makes this one genuinely interesting architecturally: HANDOFF names the real fix directly — an Invoice becoming `Paid` because a Commerce `Payment` actually succeeded has no wiring at all today, and it's flagged as 'a third candidate for the same kind of Interface Commerce's own `TaxRateProviderInterface` demonstrates' (question 1 of this file) — meaning the eventual fix isn't a quick status-flip method, it's a real, consumer-defined Interface connecting payment confirmation to invoice reconciliation, the same architectural shape this file's own closing synthesis names as the platform's one recurring cross-module pattern."

🔁 **Likely follow-ups:**
1. "Why wasn't `MarkInvoicePaidAction` just built directly instead?" → Because a real implementation needs to answer a genuine design question first — which module decides an Invoice is Paid, and through what contract — not just flip an enum value with no real trigger behind it; that's exactly the kind of premature mechanism this project avoids building ahead of a real, decided need.
2. "Does Invoice have any other real, named gap?" → Yes — no PDF/HTML export and no email-delivery concept exist either; it's a real billing *record* (status, amounts, line items) with a real MCP surface, nothing renders or sends it anywhere yet.

🚩 **Red flags:**
Assuming `InvoiceStatus` having four enum values means a working four-state machine exists — missing that only one real transition method was ever built, and that the other three values are honestly documented as unreached, not silently broken.

---

### Q5: How does a `Workflow`'s own rule matching actually work? I've heard this project uses AND logic — what does that mean concretely?

🎯 **What the interviewer is REALLY testing:**
The real "Workflow state machine" mechanics this file's own title promises — can the candidate trace a genuine rules-evaluation mechanism, including the specific, deliberate default this project chose for combining several rules.

✅ **Model answer:**
"`Workflow` is the aggregate root, and — the same Immutable-Items shape `Order`/`Invoice` already establish — its own `WorkflowRule`s and `WorkflowAction`s are frozen at creation time. A `WorkflowRule` (`conditionType`/`field`/`Threshold`, no independent id, the same child-entity shape file 07 question 7 already covers) is a single comparison; a `WorkflowAction` (`actionType`/`parameters`, also no id) is a separate entity describing what actually happens. The real mechanics: a `Workflow`'s rules are **AND-combined** — every single rule has to match, not just one — evaluated by `WorkflowEvaluator`, a pure, framework-free Domain Service with the identical shape `PricingService`/`TaxCalculationService` already have (it only combines what it's given, never decides which rule applies). This is a deliberate, documented default, not an unspecified detail left to chance: an empty rule set is refused outright, both by `CreateWorkflowAction` at creation time and by `WorkflowEvaluator::evaluate()` itself as a second guard — an empty rule set would otherwise trivially match everything, exactly the kind of accidental always-fire behavior a real automation platform can't allow."

🔁 **Likely follow-ups:**
1. "Could a Workflow ever express OR logic between rules?" → Not within one `Workflow` — the only way to get OR-like behavior today is two separate `Workflow`s targeting the same `EventType`, each with its own independent AND-combined rule set, since neither the Domain model nor `WorkflowEvaluator` has an OR concept at all.
2. "Why guard against an empty rule set in two places instead of one?" → Defense in depth for a genuinely dangerous default — `CreateWorkflowAction` catches it at the moment of creation, but `WorkflowEvaluator::evaluate()` guarding it too means even a row that somehow reached the database without a rule can never silently match every single event.

🚩 **Red flags:**
Describing Workflow rules as independently evaluated, each firing its own action — missing that a `Workflow`'s rules are AND-combined as one unit, gating that same `Workflow`'s own separate `WorkflowAction`s, not evaluated and acted on individually.

---

### Q6: `InventoryLowListener` is described as "the platform's first real cross-module Domain Event Listener." What does that actually mean, given events had existed since Phase 1?

🎯 **What the interviewer is REALLY testing:**
A precise historical fact — Domain Events existing is not the same claim as anyone actually listening to one — plus the real distinction between two similarly-named Commerce events.

✅ **Model answer:**
"Every event dispatched since Phase 1 — `ProductWasCreated`, `OrderWasPlaced`, `InvoiceWasCreated`, and others — had **zero** registered listeners until this exact stage; `Event::listen()` simply never appeared anywhere in the codebase before `WorkflowsServiceProvider::boot()`. Events were being dispatched correctly the whole time, they just had no real consumer yet. `InventoryLowListener::handle()` reacts to a genuinely **new** Commerce event, `InventoryWasCommitted` — added specifically for this stage, because no existing event represented 'stock actually went down.' The event that already existed, `InventoryReserved`, is the soft-hold side of the two-phase Inventory lifecycle (file 14, question 3) — a Cart reservation, not a real, committed sale. The Listener itself injects Commerce's `InventoryRepositoryInterface`/`ProductRepositoryInterface`, never Commerce's Infrastructure/Model classes — the identical Dependency Inversion direction CRM and Finance already established (file 15, question 2; question 3 of this file), just triggered by an Event instead of a direct Action call for the first time anywhere in this platform."

🔁 **Likely follow-ups:**
1. "Was adding a new event (`InventoryWasCommitted`) a big change to Commerce?" → No — a small, additive Domain Event dispatched from `PlaceOrderAction`'s own commit loop, the same low-risk 'widen, don't rebuild' instinct file 14 (question 12) already names as this project's most recurring move.
2. "Could Workflows have reused `InventoryReserved` instead of adding a new event?" → No — it means something genuinely different (a soft hold, reversible, not yet a real sale); reusing it would have fired Low Stock Alerts for stock that was only ever browsed, not actually sold, a real false-positive risk.

🚩 **Red flags:**
Assuming Domain Events had real listeners since Phase 1 just because they were being dispatched — missing the actual, documented fact that dispatching and listening are two separate capabilities, and this platform only exercised the second one starting here.

---

### Q7: Two more Listeners — `CartAbandonedListener` and `HighValueOrderListener` — were built at the same time as `InventoryLowListener` but deliberately left unwired. What happened to each of them since?

🎯 **What the interviewer is REALLY testing:**
An honest, up-to-date "modeled but unreachable" story with a real, interesting twist — one of the two gaps has since been closed, the other genuinely hasn't, for two completely different reasons.

✅ **Model answer:**
"Both were real, deliberate scaffolding from day one — this stage's own scope named only Low Stock Alert as functional, so neither was registered via `Event::listen()` even though both classes existed and were fully written. `CartAbandonedListener` had a real, structural blocker: cart abandonment is a time-based condition ('idle for 24 hours'), which needs a scheduled job polling Carts, not an event a Cart itself ever fires — and no scheduling mechanism existed anywhere in this codebase yet at that point. `HighValueOrderListener` had **no** technical blocker at all — Commerce's `OrderWasPlaced` already existed and already carried everything the Listener needed; it was unwired purely because this stage's own scope never asked for it. Since then, only one of the two gaps has actually closed: the Tech Debt Sprint added a real scheduling mechanism, and `CartAbandonedListener` was wired for real at that point, reacting to a new event, `CartWasAbandoned`, dispatched by a genuinely scheduled `commerce:check-abandoned-carts` command. `HighValueOrderListener` is, as of today, still exactly as unwired as it was on day one — not because anything blocks it, but because it remains the cheapest available increment nobody's asked for yet."

🔁 **Likely follow-ups:**
1. "So `HighValueOrderListener` could be wired in minutes if someone asked?" → Essentially yes — one `Event::listen()` registration is the entire remaining gap, the same 'small, mechanical addition, not a rebuild' shape file 15 (question 10) already names for CRM's own unwired Actions.
2. "Why fix the scheduling blocker but not the scope gap?" → Because they're genuinely different kinds of blockers — one was a real, structural missing capability (no scheduler existed at all, blocking *any* time-based Listener, not just this one), the other was always just a scope decision nobody had reason to revisit.

🚩 **Red flags:**
Assuming both Listeners are still unwired today, or that both were closed by the same fix — missing that these are two structurally different gaps that happened to be created together but were resolved (or not) completely independently.

---

### Q8: `WorkflowAction`'s `notify_agent` type is supposed to send a real notification. Does it actually deliver one?

🎯 **What the interviewer is REALLY testing:**
A real, still-current gap the candidate should know wasn't automatically closed just because a related system (Notifications) shipped later — a good test of whether "the pieces now exist" gets mistaken for "they were connected."

✅ **Model answer:**
"No — and this is still true even after a real Notification system (`App\Modules\Notifications`, with a genuine `SendNotificationAction`) shipped in a later stage. `notify_agent` currently means rendering the message template and recording it into `WorkflowLog` — nothing is actually sent anywhere, by design at the time it was built, since no notification delivery mechanism existed yet to call. The interesting part is what happened *after* Notifications shipped: `ExecuteWorkflowActionAction`'s own `notify_agent` match arm was never updated to actually call `SendNotificationAction`, even though the exact same one-directional Module-to-Module dependency shape every other cross-module call in this codebase already uses would make that wiring genuinely cheap — Workflows depending on Notifications' own published mechanism, not on anything concrete. This is a real, honestly named gap, explicitly described as a 'genuinely cheap increment' rather than 'build a delivery channel from scratch,' waiting on a real request rather than nobody ever noticing it."

🔁 **Likely follow-ups:**
1. "Why wasn't this wired up automatically the moment Notifications shipped?" → Because Notifications shipping was its own stage with its own scope — connecting an unrelated, earlier module's dormant `notify_agent` arm to it wasn't part of that request, the same discipline that keeps every stage's own scope honest rather than silently expanding into adjacent modules.
2. "Is this the same shape as `HighValueOrderListener` (question 7)?" → A related shape, not identical — that one needs a single Listener registration; this one needs an actual cross-module Action call wired into an existing match arm, a slightly bigger but still small, well-understood piece of work.

🚩 **Red flags:**
Assuming `notify_agent` must work now "since Notifications obviously exists" — exactly the kind of unverified assumption this project's own documentation is careful to catch and correct.

---

### Q9: A real, pre-existing Commerce bug surfaced while writing Workflows' own end-to-end test — not introduced by Workflows at all. What happened?

🎯 **What the interviewer is REALLY testing:**
A concrete, "I've actually hit this" war story about a bug discovered at a module boundary, plus the judgment call about whose responsibility it actually was to fix it.

✅ **Model answer:**
"`CheckInventoryAction`'s own re-check inside `PlaceOrderAction` validates a Cart item's quantity against `Inventory::available()` — which already has *that same Cart's own reservation* subtracted out of it. That means ordering more than half of on-hand stock (say, 7 of 10 units) makes the re-check fail, even though the exact right amount was already correctly reserved earlier by `AddToCartAction` — a real, pre-existing double-subtraction bug that had simply never been exercised, because every existing Commerce/Finance test up to that point happened to order small-enough quantities to stay under that ceiling by coincidence. Workflows' own end-to-end test was the first one to genuinely need a Low Stock Alert to actually fire, which meant deliberately ordering enough to cross a real `<5` threshold — and that's exactly the quantity that first tripped this dormant Commerce bug. It wasn't fixed as part of the Workflows stage — a pre-existing Commerce behavior being wrong is out of scope for a Workflows PR to silently patch — it was documented instead, with `WorkflowsCapabilityTest`'s own docblock recording the exact numbers chosen (6 on hand, order 3) to stay under the re-check ceiling while still genuinely crossing the Low Stock threshold once committed, and flagged as a real debt item for a later, dedicated fix."

🔁 **Likely follow-ups:**
1. "Why not just fix the Commerce bug right there in the same PR?" → Because a Workflows stage silently patching Commerce's own re-check logic would blur exactly which module owns which behavior, and risk a real regression to Commerce's own heavily-tested checkout path for a bug nobody had actually asked to fix yet — documenting and flagging it was the correct-scoped move, not fixing it uninvited.
2. "Is this the same class of discovery as the `HttpExceptionInterface` routing bug (file 17, question 10)?" → The same general shape — a new module exercising an old, dormant code path for the first time is exactly where a pre-existing gap is most likely to actually surface — but a genuinely different bug, in a genuinely different layer.

🚩 **Red flags:**
Assuming this bug was introduced by Workflows because it was discovered during the Workflows stage — missing that it's explicitly documented as a pre-existing Commerce quirk that no earlier test happened to exercise, not a regression this stage caused.

---

### Q10: `UpdateTaxRateAction` and `UpdateWorkflowAction` are both real, tested Actions with no MCP capability wired to them. What can each actually update, and why so little?

🎯 **What the interviewer is REALLY testing:**
Recognizing this exact scope-boundary pattern recurring for a third module family (file 15's own CRM coverage already names it as the norm), plus a specific, honest look at *why* each Action's own editable surface is so narrow.

✅ **Model answer:**
"The identical shape, a further time over — file 15 (question 10) already names this as the norm, not the exception, across nearly every Domain Module in this codebase. Both Actions are real, fully tested, and simply never asked to be MCP-reachable: `UpdateTaxRateAction` (`tests/Feature/Finance/UpdateTaxRateActionTest.php`) only ever updates `ratePercentage`/`isActive` — the region itself is deliberately not updatable through it, the same 'immutable after creation' shape Product's own SKU and Category's own slug already carry. `UpdateWorkflowAction` only ever updates `name`/`description`/`status` — a `Workflow`'s own rules and actions, frozen at creation (question 5 of this file), have **no** 'add a rule' or 'add an action' operation at all; a real workflow-builder UI would need a genuinely new, more deliberate redefinition operation than exists today, not just a capability wired onto the existing Action. Wiring either Action's current, narrow surface up is the same small, mechanical addition file 15 already described — one capability definition, one handler closure — whenever a future stage actually needs it."

🔁 **Likely follow-ups:**
1. "Why is a Workflow's structure frozen at all, rather than editable?" → The same Immutable-Items reasoning `Order`/`Invoice` already establish (question 5 of this file) — a `Workflow` that's already running shouldn't have its own rules silently rewritten underneath whatever's currently evaluating against it.
2. "Which would need more real design work to build — add-a-rule for Workflow, or region-editing for TaxRate?" → Add-a-rule is genuinely bigger — region-editing is a straightforward field update once requested, but redefining a frozen aggregate's own child collection safely (what happens to in-flight evaluations?) is a real design question, not just a missing capability.

🚩 **Red flags:**
Assuming these two Actions are unwired because they're unfinished or buggy — missing that both are fully real and tested, and that their own narrow editable surface is itself a deliberate modeling choice (immutability), not an accident of what got wired to MCP.

---

### Q11: Several capability and permission names in this file's own two modules were rewritten from what the original request asked for. Walk me through the pattern.

🎯 **What the interviewer is REALLY testing:**
Concrete familiarity with this project's own 3-segment capability-naming rule, and recognizing it as a repeating, predictable rename pattern rather than an isolated one-off correction.

✅ **Model answer:**
"`workflow.create`/`.get`/`.list`/`.trigger` were all originally 2 segments — `CapabilityName` requires exactly 3, the same rule that had already forced renames in WooCommerce's and CRM's own capabilities before Workflows ever hit it. The fix followed this project's own standard move: promote one of the implied words into its own real 'resource' — `workflow.create`/`.get`/`.list` became `workflow.definition.create`/`.get`/`.list`, and `workflow.trigger` became `workflow.event.trigger`; `workflow.log.list` was already 3 segments and needed no change at all. The matching permissions hit the identical constraint — `workflows.manage`/`.read`/`.execute` were also 2 segments, becoming `workflow.definitions.manage`/`.read`/`.execute`, keeping the exact same three permission groupings the original request specified, just reshaped to fit the naming rule rather than redesigned around it. Finance needed no renames at all this stage — `finance.invoice.create`/`.issue`/`.get`/`.list` and `finance.tax.calculate`/`.create` were already compliant from the start."

🔁 **Likely follow-ups:**
1. "Is 'promote a word to its own resource' always the fix, or could segments be merged instead?" → Promoting a word is the consistent move throughout this codebase's own history specifically because it preserves the original semantic grouping the request specified — merging two words would usually lose a real distinction (like 'definition' vs. 'event' here) the request actually cared about.
2. "How many modules have hit this exact renaming gotcha?" → Every one so far except Loyalty and Reporting — WooCommerce, CRM, Finance's own earlier stage, Workflows, and Shipping all needed at least one rename, making it one of the most consistently recurring gotchas in this project's own history.

🚩 **Red flags:**
Treating a capability rename as an arbitrary, one-off naming preference — missing that it's a structural, enforced constraint (`CapabilityName` itself validates segment count) that has predictably hit nearly every module built in this codebase.

---

### Q12: Across Commerce, CRM/Loyalty, and now Finance/Workflows, which module actually gets to *define* the interface whenever two business modules need to talk? Is there a single rule underneath all of it?

🎯 **What the interviewer is REALLY testing:**
A closing, Part-E-wide synthesis — pulling files 14, 15, and this file together into the one strategic rule that's been running underneath every cross-module example in the whole business-modules arc.

✅ **Model answer:**
"The consumer always defines the interface — never the supplier. `TaxRateProviderInterface` is this rule's single clearest example, and it's why this file leads with a story built on it (question 1): Commerce, the module with a real, immediate need for a tax rate, defines that interface itself; Finance, the module that actually *has* the answer, only ever implements a contract it didn't get to design, and only through the one class (`CommerceTaxRateProvider`) that references Commerce at all, and only via Commerce's own published Interface. The same direction repeats everywhere a business module needs another one's data: CRM/Loyalty define their own `CustomerNotFoundException` rather than accept Commerce's (file 15, questions 2 and 12); Finance does the identical thing with its own `OrderNotFoundException` (question 3 of this file); `WorkflowEvaluator` only ever consumes what a `Workflow`'s own frozen rules already carry, never a concrete class handed to it by whichever module happens to publish the loudest event. The one underlying reason this keeps recurring: whoever actually *calls* an interface understands its own real shape and needs best — designing it themselves means the contract fits the one place it's actually used, rather than fitting whatever shape felt natural to the module supplying the data. Every module in Part E — Commerce, CRM, Loyalty, Finance, Workflows — independently reached for this same direction, not because it was copied from a template, but because it's the only direction that keeps a Supplier module genuinely free to be removed, rewritten, or reimplemented without a single Consumer module ever needing to change."

🔁 **Likely follow-ups:**
1. "Has a Supplier module ever tried to define the interface instead, and it had to be corrected?" → File 02 (question 5) already names the real near-miss: when Finance first needed to supply Commerce a tax rate, the instinct to let Finance define its own interface was caught and reversed before it shipped, precisely because it would have inverted this rule.
2. "Does this rule extend past business modules, to Core itself?" → Yes, in the same direction — Commerce consumes `AuthContext`/`CheckPermissionAction` exactly as Core designed them (file 02, question 5), because Core is the one Supplier relationship in this platform that's never expected to be replaced, so the usual 'consumer defines the contract' direction naturally relaxes there.

🚩 **Red flags:**
Describing this as "modules talk through interfaces" without naming *which side* gets to design the interface — the entire strategic weight of this rule lives in that direction, not in the mere existence of an interface between two modules.

---

← [CRM & Loyalty](15-crm-loyalty.md) | Next: [AI Agents & the Orchestrator](17-ai-agents-orchestrator.md) →
