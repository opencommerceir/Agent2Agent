← [CRM & Loyalty](15-crm-loyalty.md) | Next: [AI Agents & the Orchestrator](17-ai-agents-orchestrator.md) →

# 16. Finance, Workflows & Reporting

`PointTransaction`/`WorkflowLog`'s own Ledger-vs-Event-Sourcing distinction (file 09, question 9), `WorkflowRule`'s own no-independent-id shape (file 07, question 7), the Customer-Supplier direction behind `TaxRateProviderInterface` and its `NullTaxRateProvider` fallback (file 08, question 6), the exact `ServiceProvider` boot-order mechanics that let Finance rebind that same interface (file 02, question 8), `Money`'s own deliberate duplication across Commerce/Finance/Shipping (file 07, question 9; file 08, question 2), `InventoryLowListener`'s own cross-module dependency shape (file 09, question 11), and Reporting's entire CQRS/read-model architecture — `Report`/`ReportResult`, the five Generators, KPI caching, the `expires_at` gap, Analytics' own Conformist chain (file 10, questions 3 through 9) — were all already covered from other angles, several of them in real depth. This file is where Finance and Workflows — the two Phase-4 Domain Modules never walked through directly as their own subject — finally get that treatment, and where Reporting gets one last pass, specifically at the seams where these three modules actually touch each other.

---

### Q1: Walk me through `GenerateInvoiceAction` — from a real Order to a persisted Invoice. Where does the tax amount on that Invoice actually come from?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can trace a real cross-module flow end to end, and specifically whether they notice a subtle, deliberate decision about *not* recomputing a number that already exists.

✅ **Model answer:**
"`GenerateInvoiceAction` takes an `order_id`, resolves the real `Order` through Commerce's own published `OrderRepositoryInterface` — never Commerce's Model, the same rule file 15 (question 2) already established for CRM — and builds a fresh `Invoice` in `Draft` status. One `InvoiceItem` gets frozen per real `OrderItem`, the identical 'items frozen at creation, no independent id' shape file 07 (questions 2 and 7) already covers for `OrderItem` itself. The subtle part is the tax figure: `GenerateInvoiceAction` never asks `TaxRateProviderInterface` to compute anything a second time — it just carries `Order.tax` forward onto the Invoice unchanged. That number was already decided once, for real, at checkout time, when Commerce actually called `TaxRateProviderInterface` (file 08, question 6) to price the Order. Recomputing it again at invoice time would risk a second, possibly different answer for the same historical sale — if a `TaxRegion`'s own rate changed in the days between purchase and invoicing, a re-computed Invoice would silently disagree with the Order the customer actually paid. Carrying the frozen number forward instead of asking twice is the same 'compute once, apply durably later' principle `PaymentSession`'s own frozen pricing already established (file 13, question 4)."

🔁 **Likely follow-ups:**
1. "What if Finance isn't even installed when an Order is placed?" → `Order.tax` is already correct regardless — Commerce always has a real number there, either from `CommerceTaxRateProvider` (if Finance exists) or `NullTaxRateProvider`'s flat 9% fallback (file 08, question 6) — `GenerateInvoiceAction` never has to know or care which one produced it.
2. "Does `InvoiceRepositoryInterface` persist `InvoiceItem` separately?" → No — file 07 (question 10) already covers this: one Repository persists the whole `Invoice` Aggregate, `InvoiceItem` included, in one coherent operation.

🚩 **Red flags:**
Assuming `GenerateInvoiceAction` re-runs tax calculation — missing the real, deliberate reason a historical document should carry forward an already-decided number rather than ask the same question twice and risk two different answers.

---

### Q2: A `TaxRegion` can be scoped to a whole country or narrowed to a specific state/province within it. When a Customer's address matches both, which one wins — and what happens when nothing matches at all?

🎯 **What the interviewer is REALLY testing:**
A real specificity-resolution rule, and recognizing that this project already has two independent layers of fallback stacked on top of each other, not one.

✅ **Model answer:**
"The more specific `TaxRegion` wins — a country+state region (like `US-CA`) is preferred over a country-wide one (`US`) whenever a Customer's address matches both, the same 'most specific match wins' shape `DiscountPriority` already uses for Commerce's own Stackability resolution (file 14, question 5), just applied to geography instead of Discount rules. If genuinely nothing matches — a country with no configured `TaxRegion` at all — `TaxRegion::default()` is the real, final fallback: a single, always-present flat rate Finance itself owns (file 08, question 2's own 'Default' example). What's easy to miss is that this makes **two** independent fallback layers, not one: `NullTaxRateProvider`'s flat 9% only ever fires when Finance isn't installed at all (file 08, question 6); `TaxRegion::default()` only ever fires when Finance *is* installed but this specific Customer's geography genuinely has no configured region. A checkout can hit either fallback, or neither, but never both at once — they answer two completely different questions ('does a tax module exist?' vs. 'does this address have a configured rate?')."

🔁 **Likely follow-ups:**
1. "Could a Customer's address match three or more TaxRegions at once?" → Not in this project's own model — a `TaxRegion` is uniquely keyed by country, or by country+state, so at most one region of each specificity level can ever match; the ordering resolves the tie, never a real ambiguity between two equally specific regions.
2. "Is `TaxRegion::default()` per-tenant?" → Yes — like every other Finance record, it's tenant-scoped; two tenants can configure two completely different fallback rates without either affecting the other.

🚩 **Red flags:**
Treating `NullTaxRateProvider`'s fallback and `TaxRegion::default()`'s fallback as the same mechanism — missing that they sit at two different layers (module-missing vs. module-present-but-unconfigured) and can never both apply to the same checkout.

---

### Q3: `GenerateInvoiceAction` throws Finance's own `OrderNotFoundException` on an unknown `order_id`, never Commerce's. Is this the same rule file 15 already covered for CRM, or something new?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate recognizes a rule repeating for a third time, independently, as stronger evidence of a real architectural discipline — not weaker, redundant coverage.

✅ **Model answer:**
"The exact same rule, independently applied a further time. `GenerateInvoiceAction` injects Commerce's `OrderRepositoryInterface` to validate the `order_id` exists, and on a miss throws Finance's **own** `OrderNotFoundException` — a separate class from Commerce's own version, mapped to the identical 404, never Commerce's concrete exception propagating through. File 15 (question 2) already named this exact class as one of the rule's own examples when covering CRM's `CustomerNotFoundException`. What's worth noticing here isn't a new mechanism — it's that a completely different team-of-one, building a completely different module, reached for the identical shape without needing to re-derive it, because file 15 (question 12) already covers *why*: the pattern was proven and documented by CRM/Loyalty first, so every module built after just followed it."

🔁 **Likely follow-ups:**
1. "Does Finance ever import Commerce's `Order` Eloquent Model directly, the way CRM never touches Commerce's Model either?" → Never — `InvoiceRepositoryInterface`'s own Eloquent implementation has no relation to Commerce's Order Model at all, the identical Infrastructure-layer discipline file 15 (question 3) already covers for CRM's Tag pivot.
2. "Is this rule ever violated inside Finance itself?" → No documented instance — every Finance Action that needs to confirm another module's entity exists follows this same shape without exception.

🚩 **Red flags:**
Describing this as "the same example already covered" and stopping there — missing that the real interview-worthy point is *independent repetition*, not just recollection of a fact already stated once elsewhere.

---

### Q4: `Invoice` has its own state machine — `Draft → Issued → Paid`, with `Void` reachable from `Draft` or `Issued` but not from `Paid`. Why not, and what's the real, honest gap that leaves?

🎯 **What the interviewer is REALLY testing:**
Recognizing this as a third real state machine in the same family as `Order`/`Ticket` (file 07, question 8; file 15, question 1), each with a business-driven strictness level — and being honest about what a missing transition actually costs.

✅ **Model answer:**
"`Void` reachable from `Draft`/`Issued` but not `Paid` is a real accounting rule, not an arbitrary restriction: voiding an unpaid Invoice is just admitting it should never have existed, but voiding a Paid one would make a real, already-recorded payment vanish from the books with no trace — the same category of problem `Order::changeStatus()`'s own refusal to leave a terminal status protects against (file 07, question 8), applied to money that's already changed hands. The honest gap this leaves: there's no `CreditNote` or reversing-Invoice concept in this project at all — if a Paid Invoice genuinely needs correcting (a real refund, a billing error discovered later), there's currently no modeled path for it. This is a real, named limitation, the same 'modeled state machine, deliberately missing a transition, rather than a silently wrong one' honesty file 14 (question 7) already applied to `TransferStatus::InTransit`."

🔁 **Likely follow-ups:**
1. "How would you actually add reversal support?" → A new `CreditNote` Aggregate referencing the original `Invoice`, never a backward transition on `Invoice` itself — the same 'a genuinely new business fact deserves a new Entity, not a bent existing one' judgment `PaymentSession` (file 13, question 4) already made.
2. "Is `Invoice`'s transition map a class constant, the same shape as `Shipment`'s?" → Yes — the identical `ALLOWED_TRANSITIONS` pattern file 07 (question 8) covers, just with `Invoice`'s own, stricter set of legal moves.

🚩 **Red flags:**
Calling the missing `Void`-from-`Paid` path "an oversight" — missing that it's a deliberate refusal protecting real financial-record integrity, with the actual, honest cost being a genuinely separate, not-yet-built feature (reversal), not a bug in the state machine itself.

---

### Q5: How does a `WorkflowRule` actually get matched against a real, live Domain Event? Walk me through the mechanics.

🎯 **What the interviewer is REALLY testing:**
The real "Workflow state machine" this file's own title promises — can the candidate trace a genuine rules-engine mechanism, not just describe Workflows as "if this then that" in the abstract.

✅ **Model answer:**
"`WorkflowEngine` is a single, generic Listener subscribed to every `EventType` this platform dispatches — never one Listener per event, which would mean a new class every time a new automation trigger was added. When any subscribed event fires, `WorkflowEngine::handle()` looks up every `Workflow` that's both `Active` and tenant-scoped to the event's own tenant, filters to the ones whose own `WorkflowRule`s declare a matching `EventType`, and evaluates each matching rule's condition (question 7 of this file) against the event's own payload. A rule whose condition passes has its configured action actually executed — send a notification, apply a tag, whatever that `WorkflowRule` declares — and, win or lose, a `WorkflowLog` row gets appended recording the outcome, the same append-only ledger shape file 09 (question 9) already covers. `Workflow`'s own current `active`/`inactive` state lives directly on the Aggregate, never derived from that log — exactly the same 'ledger for history, independent field for current state' split `PointTransaction`/`LoyaltyAccount.current_balance` already established (file 09, question 9)."

🔁 **Likely follow-ups:**
1. "Why one generic Listener instead of one per EventType?" → Because a new automation trigger should only ever mean a new `EventType` case and new `WorkflowRule` data, never a new Listener class — the same 'widen the existing mechanism, don't build a parallel one' principle file 14 (question 12) already names as this project's single most recurring move.
2. "Does WorkflowEngine inject Commerce's Model directly to read event data?" → No — it only ever reads the event's own payload (an identifier or an already-attached Entity, file 09 question 5's own distinction), and any Action needing more injects the relevant module's Repository interface, the same rule `InventoryLowListener` already follows (file 09, question 11).

🚩 **Red flags:**
Describing Workflows as "a big if/else chain somewhere in Commerce" — missing that it's a real, decoupled, tenant-scoped, event-subscribed rules engine sitting completely outside every other module, reachable only through Domain Events.

---

### Q6: The `EventType` enum has more cases than `WorkflowEngine` actually reacts to. Which ones are real, wired triggers today, and which are modeled but unreachable?

🎯 **What the interviewer is REALLY testing:**
Recognizing the same "modeled but not all reachable yet" shape this codebase carries in several other enums (file 14, question 7; file 15, question 8) — and knowing this file's own instance of it specifically.

✅ **Model answer:**
"`CartAbandoned` and `OrderHighValue` are both real, live triggers — `CartAbandoned` fires from the same scheduled abandoned-cart sweep Commerce already runs (file 09, question 3's own eventual-consistency example), and `OrderHighValue` fires directly off `OrderWasPlaced` once the Order's own total crosses a configurable threshold (question 11 of this file covers a real bug that once hid inside exactly this comparison). `InventoryLow` is the honest exception: it's a real, modeled `EventType` case, but nothing in `WorkflowEngine` currently subscribes to `InventoryWasCommitted` and translates a low-stock condition into this specific `EventType` the way `InventoryLowListener` (file 09, question 11) already reacts to the same underlying event for its own, separate purpose. An Agent can create a `WorkflowRule` targeting `InventoryLow` today — it'll just never fire, the identical honest, non-silent gap `RewardType::FreeProduct` (file 15, question 8) already carries for Loyalty."

🔁 **Likely follow-ups:**
1. "Should `workflow.rule.create` reject `InventoryLow` until it's wired?" → The same legitimate either-way call file 15 (question 8) already discusses for `RewardType` — this project chose to let the data model stay ahead of the trigger wiring, not to block on it.
2. "What would wiring `InventoryLow` actually require?" → A small, additive Listener translating `InventoryWasCommitted` into a dispatched `EventType::InventoryLow` event whenever stock crosses a low-stock threshold — real, scoped future work, not a rebuild of the engine.

🚩 **Red flags:**
Assuming every `EventType` case is a live, working trigger just because it exists in the enum — the exact assumption this codebase's own documentation is careful never to let go unchecked.

---

### Q7: `WorkflowRule` conditions are a single `field`/`operator`/`value` triple — no `AND`/`OR` nesting, no compound conditions at all. Why not build a real rule-engine language?

🎯 **What the interviewer is REALLY testing:**
A real, honestly-scoped trade-off between a full rule-engine DSL and a deliberately minimal one — can the candidate defend "good enough for now" without pretending it's complete.

✅ **Model answer:**
"A single comparison — `field`, one of a small fixed set of operators (`equals`, `greater_than`, `less_than`), and a `value` — was a deliberate scope boundary, not a missing feature nobody thought about. A real compound-condition language (nested `AND`/`OR` trees) is a genuinely bigger, self-contained problem — its own parser, its own validation rules, its own UI for building one — and no stage that's touched Workflows has actually needed more than one comparison per rule yet. Where a real business need looked like it wanted compound logic, the actual solution was simpler: **two separate `WorkflowRule`s on the same `Workflow`**, both needing to independently pass, rather than one rule with an embedded `AND`. This mirrors the same instinct file 14 (question 5) already shows for Discount Stackability — reach for the existing, simple mechanism twice before reaching for a more powerful, more complex one once."

🔁 **Likely follow-ups:**
1. "Does `WorkflowRule` reuse Reporting's own `DateRange`-style value normalization?" → No — its `value` is stored and compared as a plain, un-normalized scalar; question 11 of this file covers a real bug that gap once caused.
2. "What would trigger actually building a compound-condition language?" → A real business request genuinely needing `AND`/`OR` logic Workflow's own two-separate-rules trick can't express (e.g., a true `OR` between two rules on one `Workflow`, which today's 'all rules on a Workflow must pass' semantics can't represent) — real, tracked future work, not something this project has hit yet.

🚩 **Red flags:**
Calling the single-comparison condition shape "obviously too limited" without engaging with the real trade-off it avoided — building and maintaining a genuine rule-engine DSL for a need that hasn't actually materialized yet.

---

### Q8: What happens when a `WorkflowRule`'s own action — say, sending a notification — actually fails when it runs? Does the rest of the Workflow evaluation get affected?

🎯 **What the interviewer is REALLY testing:**
The same synchronous-listener failure-isolation discipline file 09 (question 6) already covers, applied to this file's own, different mechanism — and an honest look at what's genuinely still missing.

✅ **Model answer:**
"`WorkflowEngine` wraps each individual `WorkflowRule`'s own action execution in its own try/catch — the same 'a listener that might hit something unreliable must swallow its own failure' rule file 09 (question 6) already establishes for `SendNotificationAction`. A failed action gets a `Failed`-status `WorkflowLog` row recording exactly what happened, and evaluation moves on to the next matching `WorkflowRule` rather than aborting the whole event's Workflow processing over one rule's own failure. The honest gap: unlike `SendNotificationAction` itself, which has its own real retry-with-backoff logic (file 09, question 6), a failed `WorkflowRule` action is never automatically retried — `WorkflowLog`'s own `Failed` row is the complete, final record of that attempt, with no queued follow-up. An operator has to notice the failure themselves; nothing re-attempts it."

🔁 **Likely follow-ups:**
1. "Could this reuse SendNotificationAction's own retry mechanism directly?" → Only for the specific `WorkflowRule` actions that happen to *be* a notification send — a `WorkflowRule` whose action tags a Customer or updates a record has no equivalent retry-capable mechanism to reuse at all.
2. "Is this the same shape as any other honestly-named 'no automated retry' gap?" → Yes — file 13 (question 9) covers the identical shape for payment reconciliation: the building block for retrying exists in pieces, but no automated sweep actually runs it.

🚩 **Red flags:**
Assuming a failed Workflow action automatically retries "because the platform is event-driven" — missing that event-driven and retry-guaranteed are two separate properties, and this project is explicit about which one it actually has here.

---

### Q9: Does Invoice/tax data from Finance ever reach Reporting's own `SalesQueryBuilder`? Is this the same kind of gap Subscription revenue has (file 14, question 9)?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can correctly distinguish a real gap from a non-issue that merely looks similar on the surface — a sharper form of the honest-gap-naming discipline this handbook keeps testing.

✅ **Model answer:**
"Better news than the Subscription case, and for a specific, traceable reason: `SalesQueryBuilder` reads `orders.total`/`orders.tax` directly off Commerce's own table — and `Order.tax` is already the real, final number, because Commerce called `TaxRateProviderInterface` at checkout time regardless of whether Finance is even installed (question 1 of this file). Reporting's revenue and tax figures are correct **without ever touching Finance's own `invoices` table at all** — a real, structural difference from Subscriptions, whose revenue genuinely never reaches `orders` in the first place (file 14, question 9) because `SubscriptionInvoice.orderId` stays permanently null. There *is* a real, separate, honestly-named gap here, just a narrower one: Finance-specific questions that have no equivalent on `Order` at all — 'how many Invoices are currently unpaid,' 'what's the average time between `Issued` and `Paid`' — have no Reporting Query Builder or Generator built for them today. Reporting knows what was sold and taxed; it knows nothing about Finance's own downstream invoicing-and-collection state."

🔁 **Likely follow-ups:**
1. "Why wasn't an InvoiceQueryBuilder built alongside the existing five?" → No stage's own request has needed Finance-specific reporting yet — the same 'real, tracked future work, not a silent oversight' honesty file 10 (question 5) already applies to `ReportResult.expires_at`.
2. "Would adding one follow the same Conformist shape as the existing five?" → Yes — it would read Finance's own `Invoice` Eloquent Model directly, the identical scoped exception file 08 (question 9) already covers, just one more class added to that same, already-documented list.

🚩 **Red flags:**
Assuming Finance data has the identical blind-spot shape Subscriptions has — missing the real, structural reason Order-level tax already flows into Reporting correctly, and only Finance's own downstream state (paid/unpaid) is the genuinely missing piece.

---

### Q10: Several real, tested Finance and Workflow Actions — like `VoidInvoiceAction` and `DeactivateWorkflowAction` — have no MCP capability wired to them. Is this the same shape file 15 already covered for CRM?

🎯 **What the interviewer is REALLY testing:**
Recognizing this exact scope-boundary pattern recurring for a third module family, and knowing it's the norm across this entire platform, not an exception worth re-explaining from scratch each time.

✅ **Model answer:**
"The identical shape, a third time over — file 15 (question 10) already names this as the norm, not the exception, across nearly every Domain Module in this codebase. `finance.invoice.generate`/`.get`/`.list` and `workflow.create`/`.rule.add`/`.get`/`.list` cover exactly what real stages actually requested; `VoidInvoiceAction` and `DeactivateWorkflowAction` are both completely real, fully covered by their own Feature tests, just never asked to be reachable by an Agent yet. Wiring either one up is the same small, mechanical addition file 15 already described — one capability definition, one handler closure in the relevant `ServiceProvider` — not a rebuild, whenever a future stage actually needs it."

🔁 **Likely follow-ups:**
1. "Which would you wire up first if asked?" → `DeactivateWorkflowAction` is probably more immediately useful — an Agent that created a `Workflow` has no way today to turn it back off without going around the MCP surface entirely.
2. "Does every Domain Module in this platform carry at least one gap like this?" → Every one covered so far in Part E — Commerce (file 14, question 10's own `commerce.order.place` gap is a related but different shape), CRM (file 15, question 10), and now Finance/Workflows — a genuine, repeated pattern, not a coincidence.

🚩 **Red flags:**
Treating this as a fresh discovery worth re-explaining at length — the stronger answer recognizes it as the third confirmation of an already-established, named pattern and moves straight to the specific gap.

---

### Q11: A real bug once made `OrderHighValue` silently never fire for a tenant, even though their `WorkflowRule`'s threshold looked completely correct in the admin UI. What actually happened?

🎯 **What the interviewer is REALLY testing:**
A concrete, "I've actually hit this" war story connecting this file's own condition-evaluation mechanics (question 7) to the platform-wide 'money is always an integer, never a float or a decorated string' discipline (file 13, question 1).

✅ **Model answer:**
"`WorkflowRule.value` is stored as a plain, un-normalized string (question 7 of this file) — no validation at creation time beyond 'a value was provided.' A tenant configuring an `OrderHighValue` rule through the admin UI typed their threshold as `$100.00` instead of a bare number. `Order.total` is a real integer, cents, exactly like every other Money-shaped field on this platform (file 13, question 1) — comparing an integer like `10000` against the *string* `\"$100.00\"` with PHP's own `>` operator doesn't throw, it just silently evaluates in a way that never matched any real order total, so the rule looked completely valid and simply never fired for that tenant, with nothing in any log flagging it as wrong. The fix was adding real validation to `CreateWorkflowRuleAction` itself: when the declared `field` is a known Money-shaped field, `value` must parse as a genuine integer or the Action throws a new `InvalidWorkflowConditionException` at creation time, catching a malformed threshold before it's ever silently saved, rather than letting it fail invisibly every time the condition is evaluated later."

🔁 **Likely follow-ups:**
1. "Why didn't this surface in a WorkflowEngine unit test?" → Because every existing test constructed a `WorkflowRule` with an already-correct, bare-integer `value` directly in code — the exact same 'a real gap only surfaces at a seam a controlled unit test never actually exercises' shape file 15 (question 4) already covers for hand-typed foreign keys, just here the seam is a human typing into a UI field, not a test author typing into a factory.
2. "Could this same bug exist for a non-Money field, like a status comparison?" → No — string fields were never assumed numeric to begin with; the bug specifically lived in the gap between 'this field is conceptually a Money integer' and 'nothing actually enforced that at input time.'

🚩 **Red flags:**
Assuming PHP would throw a type error on this comparison — missing the actual, more dangerous failure mode: a comparison that succeeds silently and simply produces the wrong, never-true answer, with nothing anywhere signaling that anything went wrong at all.

---

### Q12: Across Commerce, CRM/Loyalty, and now Finance/Workflows/Reporting, which module actually gets to *define* the interface whenever two business modules need to talk? Is there a single rule underneath all of it?

🎯 **What the interviewer is REALLY testing:**
A closing, Part-E-wide synthesis — pulling files 14, 15, and this file together into the one strategic rule that's been running underneath every cross-module example in the whole business-modules arc.

✅ **Model answer:**
"The consumer always defines the interface — never the supplier. `TaxRateProviderInterface` is this rule's single clearest example, and it's why this file leads with it: Commerce, the module with a real, immediate need for a tax rate, defines that interface itself; Finance, the module that actually *has* the answer, only ever implements a contract it didn't get to design (file 08, question 6). The same direction repeats everywhere a business module needs another one's data: CRM/Loyalty define their own `CustomerNotFoundException` rather than accept Commerce's (file 15, questions 2 and 12); Finance does the identical thing with its own `OrderNotFoundException` (question 3 of this file); Workflows' `WorkflowEngine` only ever consumes an event's own payload or a Repository interface it needs, never a concrete class handed to it by whichever module happens to publish the loudest event. The one underlying reason this keeps recurring: whoever actually *calls* an interface understands its own real shape and needs best — designing it themselves means the contract fits the one place it's actually used, rather than fitting whatever shape felt natural to the module supplying the data. Every module in Part E — Commerce, CRM, Loyalty, Finance, Workflows — independently reached for this same direction, not because it was copied from a template, but because it's the only direction that keeps a Supplier module genuinely free to be removed, rewritten, or reimplemented without a single Consumer module ever needing to change."

🔁 **Likely follow-ups:**
1. "Has a Supplier module ever tried to define the interface instead, and it had to be corrected?" → File 02 (question 5) already names the real near-miss: when Finance first needed to supply Commerce a tax rate, the instinct to let Finance define its own interface was caught and reversed before it shipped, precisely because it would have inverted this rule.
2. "Does this rule extend past business modules, to Core itself?" → Yes, in the same direction — Commerce consumes `AuthContext`/`CheckPermissionAction` exactly as Core designed them (file 02, question 5), because Core is the one Supplier relationship in this platform that's never expected to be replaced, so the usual 'consumer defines the contract' direction naturally relaxes there.

🚩 **Red flags:**
Describing this as "modules talk through interfaces" without naming *which side* gets to design the interface — the entire strategic weight of this rule lives in that direction, not in the mere existence of an interface between two modules.

---

← [CRM & Loyalty](15-crm-loyalty.md) | Next: [AI Agents & the Orchestrator](17-ai-agents-orchestrator.md) →
