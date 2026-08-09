← [Commerce Core](14-commerce-core.md) | Next: [Finance, Workflows & Reporting](16-finance-workflows-reporting.md) →

# 15. CRM & Loyalty

`PointTransaction`'s Ledger-vs-Event-Sourcing distinction (file 09, question 9), `Shipment`'s own state-machine shape (file 07, question 8), the module-to-module Dependency Inversion principle generally (files 02, 08), the `TicketComment`/`CustomerNote` no-independent-id gap (file 07, question 7), and the 404-not-403 lookup pattern using `crm.ticket.get` as its own worked example (file 11, question 5) were all already covered from other angles. This file is where CRM and Loyalty — Phase 3's first two Domain Modules, built immediately after Commerce, and the two that established several cross-module rules nearly every later module has relied on since — get walked through directly: customer lifecycle management and points/rewards domain modeling.

---

### Q1: `Ticket::changeStatus()` is even stricter than `Order::changeStatus()` (file 07's own state-machine coverage). What's the real difference, and why does a support ticket need to be stricter than an order?

🎯 **What the interviewer is REALLY testing:**
Recognizing that two state machines in the same codebase can enforce genuinely different strictness levels, and understanding the real business reason, not just spotting the difference.

✅ **Model answer:**
"`Order::changeStatus()` refuses to *leave* a terminal status and refuses to *target* `Cancelled`/`Refunded` directly, but it tolerates a same-status no-op transition inside its own fulfillment pipeline. `Ticket::changeStatus()` is stricter on both fronts: a fixed `SEQUENCE` array (`Open → InProgress → Resolved → Closed`) means only a strictly-forward index move is ever legal — no going backward, and, unlike Order, re-targeting the *current* status is rejected too, not tolerated as a no-op. The real reason is a support-desk one: a support team's own reporting relies on a Ticket's status history meaning something precise — 'reopened' is a real, meaningful business event a support lead wants visibility into, not something that should be able to happen silently as a side effect of a sideways or accidental status update. Order's own tolerance exists because its fulfillment pipeline genuinely can re-touch the same status harmlessly; Ticket's own workflow has no equivalent harmless case, so nothing is allowed to look like one."

🔁 **Likely follow-ups:**
1. "Does a real 'reopen' operation exist for a Ticket?" → Not as a distinct, modeled transition — reopening a `Resolved`/`Closed` Ticket isn't reachable through `UpdateTicketAction` today, a real, honest gap rather than a hidden capability.
2. "What actually throws when an illegal transition is attempted?" → A real `InvalidTicketStatusException`, mapped to a 422 — never a raw, unhandled failure.

🚩 **Red flags:**
Assuming every state machine in this codebase enforces identical strictness — missing that the *right* amount of strictness is a real business judgment call made per Entity, not a copy-pasted rule.

---

### Q2: CRM depends on Commerce's `CustomerRepositoryInterface` to validate a `customer_id` — but throws its *own* `CustomerNotFoundException`, never Commerce's. Walk through why, and where this could have gone wrong.

🎯 **What the interviewer is REALLY testing:**
A precise, named architectural rule — not just "modules talk through interfaces," but the specific, easy-to-miss detail about which module's own exception type gets thrown.

✅ **Model answer:**
"`CreateTicketAction`, `AddNoteToCustomerAction`, and `AssignTagToCustomerAction` all inject Commerce's `Domain\Repositories\CustomerRepositoryInterface` directly — a published contract from another module's Domain layer, which is fine, the same one-directional Dependency Inversion every cross-module integration in this codebase uses. Where it could easily have gone wrong: when that lookup fails, the tempting shortcut is to just let Commerce's own `CustomerNotFoundException` propagate straight through CRM's own Action. That would be a real, if subtle, coupling violation — CRM would now be importing and depending on a *concrete class* from another module, not just its published Interface, the exact distinction file 02's own module-boundary rule draws. Instead, CRM throws its **own**, separately-defined `CustomerNotFoundException` — a class that happens to map to the identical 404 as Commerce's own version (both implement the same Core marker interface), but is CRM's to own, change, or remove without ever touching Commerce's code, and without Commerce's own exception class ever appearing in a CRM stack trace or `catch` block."

🔁 **Likely follow-ups:**
1. "Isn't that just duplicated code — two exception classes that mean the same thing?" → A small, deliberate duplication in exchange for real module independence — the same trade-off Finance's own separate `Money` VO (question 2, file 07) already makes for the identical reason.
2. "Is this rule specific to CRM, or does it repeat elsewhere?" → It repeats everywhere a module validates another module's own entity exists — Finance's `OrderNotFoundException`, Loyalty's `CustomerNotFoundException` (question 8 of this file's own related pattern), Shipping's `OrderNotFoundException` — all the same rule, applied independently each time a new module needed it.

🚩 **Red flags:**
Saying "it doesn't matter which exception type gets thrown as long as the HTTP status is right" — missing that the *coupling* the wrong choice introduces (a concrete cross-module dependency) is the real problem, independent of what status code the caller eventually sees.

---

### Q3: The `customer_tag` pivot table has no Eloquent `belongsToMany` relation to Commerce's `Customer` Model at all — CRM writes to it with a plain query-builder insert instead. Why go that far?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate understands module decoupling as a discipline that extends into the Infrastructure/ORM layer, not just the Domain layer where it's more commonly discussed.

✅ **Model answer:**
"Because an Eloquent `belongsToMany` relation on `Tag`'s own Model pointing at Commerce's `Customer` Model would be a real, direct Infrastructure-layer coupling between two modules — even though the *Domain* layer never imports Commerce's classes, a relation like that would make the ORM layer secretly depend on Commerce's own Model existing with a specific class name and table shape, defeating the whole point of keeping modules independent. `EloquentTagRepository::assignToCustomer()` instead writes the pivot row with a plain query-builder insert — an explicit exists-check first, so a double-assignment is a silent no-op rather than a duplicate-key database error. This keeps CRM decoupled from Commerce's Model classes at *every* layer, not just the Domain layer where that rule usually gets discussed."

🔁 **Likely follow-ups:**
1. "Does this cost anything in query convenience?" → A little — no automatic eager-loading of a Customer's own Tags through a relation; a real, accepted trade-off for the decoupling.
2. "Is this the same reasoning as CRM never importing Commerce's concrete `CustomerNotFoundException` (question 2)?" → Exactly the same principle, one layer lower — Domain-layer coupling and Infrastructure-layer coupling are both real, and this project closes both, not just the more commonly-discussed one.

🚩 **Red flags:**
Assuming "the Domain layer is independent, so the database layer doesn't matter" — missing that an Eloquent relation is itself a real, silent coupling most developers don't think to guard against.

---

### Q4: A real, non-obvious gotcha this stage hit: `ticket_comments.agent_id`/`customer_notes.agent_id` are real, non-nullable foreign keys. What breaks if a test forgets that, and why is it so easy to forget?

🎯 **What the interviewer is REALLY testing:**
A concrete, "I've actually hit this" gotcha story — the kind of detail that separates someone who's read the code from someone who's only skimmed it.

✅ **Model answer:**
"It's easy to forget because not every similar-looking column in this codebase is a real foreign key — `carts.owner_id` is a plain `unsignedBigInteger` with no FK at all, a deliberately loose type since Cart ownership doesn't need referential integrity. `ticket_comments.agent_id`/`customer_notes.agent_id`, though, are real, non-nullable foreign keys to `agents` — the same shape `orders.agent_id` already has. A test that passes a bare integer like `1` instead of a real, registered Agent id gets a `FOREIGN KEY constraint failed` SQLite error deep inside the Repository's own save call — a failure that has nothing to do with whatever the test was actually trying to verify, and one that a CRM test itself genuinely wrote before catching and fixing it. The lesson this codebase draws from it directly: never assume a foreign-key-shaped column *is* one just because a sibling column with a similar name isn't."

🔁 **Likely follow-ups:**
1. "How do you avoid this in a new test?" → Register a real Agent first (the same `registerAgentWithPermissions()`-style helper every Feature test in this codebase already uses) rather than hand-typing a plausible-looking integer id.
2. "Why does `carts.owner_id` get to be loosely typed while `agent_id` here doesn't?" → A real, considered per-column decision, not an inconsistency — Cart ownership is intentionally loose (file 04, question 8 covers the concurrency reasoning that column sits near), while comment/note authorship genuinely needs referential integrity to a real Agent record.

🚩 **Red flags:**
Assuming every `_id`-suffixed column in this schema is a real, enforced foreign key (or, the opposite mistake, that none of them are) — the real skill is knowing this project makes that decision deliberately, per column, not uniformly.

---

### Q5: `loyalty.points.earn` silently creates a `LoyaltyAccount` if one doesn't exist yet, but `loyalty.account.get` does a strict, 404-on-missing lookup. Isn't that inconsistent?

🎯 **What the interviewer is REALLY testing:**
Whether an apparent inconsistency between two similar-sounding capabilities is actually a deliberate, reasoned difference — the same judgment file 14 (question 4) already exercised for a different pair of capabilities.

✅ **Model answer:**
"It's a deliberate difference between two genuinely different verbs, not an inconsistency. Earning points for a first-time purchaser who has no `LoyaltyAccount` yet is an entirely normal, expected case — `loyalty.points.earn`'s own input is just a `customer_id`, with no requirement that `loyalty.account.create` was ever called first. `EarnPointsAction` composes `CreateLoyaltyAccountAction` internally (Actions composing Actions, HANDOFF §3 pattern #3) rather than forcing the caller to provision an account explicitly before their very first purchase can earn anything. `loyalty.account.get`, by contrast, is a genuine 'does this exist' question — a caller explicitly asking to read an account should get a real, honest 404 if there isn't one, not a silently auto-created empty account that would make the response ambiguous about whether this customer has any real loyalty history at all."

🔁 **Likely follow-ups:**
1. "What does the points-to-currency conversion actually look like?" → A fixed, simple rule: $1 spent = 100 cents = 1 point, using plain integer division that always rounds down — no fractional points, ever.
2. "Does `loyalty.account.create` ever get called directly, then?" → Yes — an Agent that wants to provision an account ahead of a customer's first purchase (for a promotional signup bonus, for instance) can call it explicitly; it just isn't a prerequisite `.earn` requires.

🚩 **Red flags:**
Proposing that `.get` should also silently create an account "for consistency" — that would make a 200 response ambiguous between "this customer has real history" and "this customer has never earned a point," exactly the ambiguity the strict lookup avoids.

---

### Q6: `loyalty.points.redeem` checks the `points` input against the named Reward's own `points_required` *before* it checks the account's real balance. Why that specific order, and what are the two different error codes involved?

🎯 **What the interviewer is REALLY testing:**
A precise, two-tier validation order and the real reason a seemingly redundant input field (`points` alongside `reward_id`) is actually meaningful.

✅ **Model answer:**
"The capability takes both `reward_id` and `points` — which looks redundant, since the Reward's own `points_required` is already the authoritative cost. It isn't redundant: it's the caller stating the price it *expects* to pay, checked against the real, current price before anything else happens. A mismatch there is `InvalidPointsException` — a 422, meaning 'your expectation of this Reward's cost is stale or wrong,' checked *first*. Only once that's confirmed correct does the Action check whether the account's real balance can actually cover it — `InsufficientPointsException`, a 409, meaning 'the price is right, you just don't have enough points right now.' Checking price-correctness before balance-sufficiency means a caller with a stale cached price gets a loud, specific 'the price changed' failure instead of a confusing 'insufficient funds' error that might make them think their balance is wrong, when really their expectation of the cost was."

🔁 **Likely follow-ups:**
1. "Could this have been a single, generic 'redemption failed' error instead?" → It could have, but it would hide a real, actionable distinction — a client can retry immediately after re-fetching the current cost for a 422, but a 409 genuinely means the customer needs more points, a completely different next action.
2. "Is this the same two-error-code shape as anywhere else in the codebase?" → A related shape — file 13 (several questions) covers Payment/Fintech's own careful error-code distinctions, the same discipline of never collapsing two genuinely different failure reasons into one status code.

🚩 **Red flags:**
Not knowing why both `reward_id` and `points` are in the input schema at all — missing that the apparent redundancy is exactly what makes a stale-price failure distinguishable from an insufficient-balance one.

---

### Q7: `ExpirePointsAction` is described in this project's own documentation as "a simplified FIFO, not a true per-lot ledger." What does that actually mean in practice, and who does the simplification favor?

🎯 **What the interviewer is REALLY testing:**
A deep, honest understanding of a real algorithmic simplification's actual consequence — not just "it expires old points first," but exactly what a true implementation would track that this one doesn't, and who wins from the gap.

✅ **Model answer:**
"A true per-lot ledger would track *which specific earn-batch* each Redemption actually drew down, so expiration would only ever touch points a customer genuinely still has unspent from that exact batch. `ExpirePointsAction` doesn't do that — it finds every `earn`/`bonus` `PointTransaction` whose `expires_at` is due and not already processed (recognized by an `expire` transaction's own `reference_id` pointing back at the source row — there's no mutable 'processed' flag on an otherwise-immutable ledger, file 09's own question 9 covers why this ledger stays append-only), and expires them oldest-first, capped by whatever balance genuinely remains. The real consequence: a customer who mostly redeemed from a *recent* earn batch will still see an *older*, unrelated batch expire first — because the algorithm has no way to know which batch their redemption actually drew from. This is deliberately the tenant-favoring, conservative outcome, not the customer-favoring one a true per-lot ledger would give — but crucially, no point this method ever expires was un-redeemed or not yet genuinely due; it's a real simplification in *which* points expire when several batches are in play, never a correctness bug in *whether* they should."

🔁 **Likely follow-ups:**
1. "Would a customer ever notice this in practice?" → Only in an edge case — a customer with multiple earn batches at different expiration dates who's redeemed unevenly across them; most customers with a single, simple earn/spend pattern would never see a difference.
2. "Why wasn't the true per-lot version built instead?" → The same 'real, working, honestly-scoped-down' precedent this codebase applies elsewhere (`CustomerLifetimeValue`'s own simplified formula is a related example) — a precise per-lot ledger is real, tracked future work, not something silently skipped.

🚩 **Red flags:**
Describing this as "FIFO, so it's just oldest-first, no real issue" — missing the actual, specific consequence: which *customer* effectively bears the cost of the simplification, and that it's a deliberate, documented trade-off, not an incidental detail.

---

### Q8: Two of Loyalty's three `RewardType` cases — `FreeProduct`/`FreeShipping` — are modeled but don't actually do anything yet. Is that a real problem for an Agent creating one today?

🎯 **What the interviewer is REALLY testing:**
Recognizing a "modeled but not all reachable yet" gap for what it honestly is — and connecting it to the same pattern seen elsewhere in this codebase, rather than treating it as an isolated surprise.

✅ **Model answer:**
"It's a real, honestly-named limitation, not a silent trap. `loyalty.reward.create` will happily accept `reward_type: free_product` or `free_shipping` — nothing rejects the input — but only `DiscountCoupon` actually does anything with its own `discount_amount` field once a Redemption happens; the other two are modeled enum cases with no fulfillment logic behind them yet. This is the identical 'modeled but not all reachable' shape several other enums in this codebase carry — `TransferStatus::InTransit` (file 14, question 7) and `EventType::CartAbandoned`/`OrderHighValue` before their own Listeners were wired are the same pattern, just for Warehouse Transfers and Workflows instead of Rewards. An Agent creating a `free_product`-typed Reward today gets a real, persisted row — it just doesn't yet trigger any real 'give this customer a free product' fulfillment when redeemed."

🔁 **Likely follow-ups:**
1. "Should `loyalty.reward.create` reject those two reward types until they're implemented?" → A real, legitimate design choice either way — this codebase chose to let the data model stay ahead of the fulfillment logic, the same 'schema-ready, not yet behavior-complete' shape file 10 (question 5) already covers for `ReportResult.expires_at`.
2. "How would you actually implement `FreeProduct` fulfillment?" → It would need a real hook into `AddToCartAction`/`PlaceOrderAction` to actually attach a free line item — real, scoped future work, not built today.

🚩 **Red flags:**
Assuming a `RewardType` enum case existing means the feature is complete — a mistake this codebase's own documentation is specifically careful never to let happen silently.

---

### Q9: Loyalty's `OrderPlacedListener` needs zero dependency on Commerce's Repository interfaces at all — unlike Workflows' `InventoryLowListener`, which genuinely does. Why the difference?

🎯 **What the interviewer is REALLY testing:**
A real design judgment about what a Domain Event's own payload should carry — richer payloads reduce a Listener's own dependencies, at a real cost worth understanding.

✅ **Model answer:**
"It comes down to what each event actually carries. `OrderWasPlaced` carries the full, real `Order` entity itself — its `total()`/`customerId()` are exactly what `OrderPlacedListener` needs to award points, so there's genuinely nothing left to fetch; no Commerce Repository dependency was required at all for this Listener. `InventoryWasCommitted`, the event `InventoryLowListener` reacts to, deliberately carries only identifiers — a Domain Event 'carries only identifiers' is this codebase's own stated convention (§3 pattern #11) precisely because trusting whatever a payload happens to include, rather than re-fetching current state through a real Repository, risks acting on stale data by the time the Listener actually runs. `OrderWasPlaced` is the one deliberate exception to that convention, not a violation of it — an `Order`, once placed, is immutable in the fields that matter here, so there's no staleness risk a re-fetch would actually protect against."

🔁 **Likely follow-ups:**
1. "So is 'carry only identifiers' not really a strict rule?" → It's the strong default, with `OrderWasPlaced` as the one considered, justified exception — the real rule is 'carry only identifiers unless the payload genuinely can't go stale before the Listener runs,' not an absolute never-carry-data mandate.
2. "Does carrying the full Entity ever cause a problem?" → Not one this codebase has hit — the risk would be if `Order` were still mutable after being placed in some field a Listener cared about, which it isn't (Immutable Order Items, file 07).

🚩 **Red flags:**
Assuming every Domain Event in this codebase carries only identifiers as an absolute rule — missing the one real, deliberate exception and the specific reasoning that makes it safe.

---

### Q10: 4 of CRM's 9 Actions — `UpdateTicketAction`, `GetCustomerNotesAction`, `CreateTagAction`, `AssignTagToCustomerAction` — are fully built and tested but have no MCP capability wired to them at all. Is that a real gap?

🎯 **What the interviewer is REALLY testing:**
Distinguishing "unfinished" from "built but deliberately not yet exposed" — a scope judgment this codebase makes explicitly and repeatedly, not something to assume is always a bug.

✅ **Model answer:**
"It's a deliberate, honestly-documented scope boundary, and it's actually the norm, not the exception — nearly every Domain Module in this codebase carries at least one Action in exactly this state. Only the 5 capabilities CRM's own stage actually requested (`crm.ticket.create`/`.get`/`.list`, `crm.comment.create`, `crm.note.create`) got a real `CRMCapabilities` entry and a `CRMServiceProvider` handler closure — the other 4 Actions are completely real, fully covered by `tests/Feature/CRM/*ActionTest.php`, just never asked to be reachable by an Agent. Wiring any one of them up later is a small, mechanical addition (one capability definition, one handler closure) whenever a future stage actually needs it — not a rebuild."

🔁 **Likely follow-ups:**
1. "How would you know which of the 4 to wire up first if asked?" → Whichever one a real workflow actually needs — `AssignTagToCustomerAction` is probably the most immediately useful, since `crm.note.create`/`crm.comment.create` already exist but Tags currently have no MCP surface at all.
2. "Is this the same shape as any capability gap covered elsewhere?" → Yes — file 13 (question 9) covers the identical 'the building block exists, nothing exposes/schedules it yet' shape for payment reconciliation, and file 14 (question 4) covers `commerce.order.place`'s own related, honestly-named gap.

🚩 **Red flags:**
Calling this "unfinished work" without checking whether it was actually a scoped, deliberate choice — the same unverified-assumption mistake file 13's own closing question already warns against.

---

### Q11: `TagNotFoundException` wasn't in CRM's own original request. Why was it added anyway, and what recurring pattern in this codebase does it belong to?

🎯 **What the interviewer is REALLY testing:**
Knowledge of a specific, named, repeated architectural pattern in this project's own documentation — not just "sometimes extra things get added."

✅ **Model answer:**
"`AssignTagToCustomerAction` needed a real, meaningful 404 for an unknown or cross-tenant tag id — without it, the failure would have surfaced as a raw foreign-key violation from `customer_tag`'s own insert, an ugly, unhelpful error for what should be an ordinary, expected case. This is a named, numbered pattern in this project's own `HANDOFF.md` — pattern #12, 'a missing piece the request implies but doesn't literally list gets added unprompted when skipping it would mean either bypassing an established convention or letting a real failure surface ugly.' `TagNotFoundException` is one of several examples the same document names explicitly — alongside Commerce's own `DiscountRepositoryInterface` (added when a request named a `Discount` table and Entity but no Repository), and the identical CRM-and-Loyalty-adjacent examples in this file: Finance's `OrderNotFoundException`, and Loyalty's own `CustomerNotFoundException` (question 2 of this file)."

🔁 **Likely follow-ups:**
1. "How do you decide something qualifies for this pattern versus just building exactly what was asked?" → The test is whether skipping it would mean either violating an existing convention (every other lookup-by-id in this codebase gets a real 404, not a raw DB error) or letting a genuinely bad failure reach a caller — not just 'this seems like a nice addition.'
2. "Does this pattern ever get misused — adding things nobody actually needed?" → Not that this codebase's own documentation records — every named instance is tied to a concrete, real failure mode the addition specifically closes, not a speculative 'might be useful someday' addition.

🚩 **Red flags:**
Treating this as "the AI/team just decided to add extra stuff" — missing that it's a named, disciplined, repeatable pattern with a specific triggering condition, not ad-hoc scope creep.

---

### Q12: CRM and Loyalty were the second and third Domain Modules ever built, right after Commerce. What foundational cross-module rule did they establish that nearly every module built since has relied on?

🎯 **What the interviewer is REALLY testing:**
A closing, synthesizing answer connecting several of this file's own specific examples into the one underlying architectural rule they're all instances of.

✅ **Model answer:**
"That a Module → Module dependency always goes through the *depended-upon* module's own published Repository Interface — never its concrete Model, and never its concrete exceptions. CRM was the first module to actually need this (question 2 of this file: CRM depends on Commerce's `CustomerRepositoryInterface`, but throws its own `CustomerNotFoundException`, never Commerce's), and it held even at the Infrastructure/ORM layer, not just the Domain layer (question 3: no Eloquent relation to Commerce's own Model, either). Loyalty confirmed the same rule a second time, independently — its own `CustomerNotFoundException` follows the identical shape for the identical reason. This one rule is what let every module since — Finance, Workflows, Shipping, Notifications, and onward — depend on an earlier module's data without ever becoming *coupled* to it: a later module can be removed, refactored, or have its internals rewritten entirely, and every dependent module keeps working as long as the published Interface's own contract doesn't change. CRM and Loyalty, being first, are where this project actually proved the rule works in practice, not just stated it as an intention."

🔁 **Likely follow-ups:**
1. "Did every later module get this right on the first try?" → Yes, consistently — because the pattern was already proven and documented by the time Finance/Workflows/Shipping needed it, each one just followed the same established shape rather than re-deriving it from scratch.
2. "Is there a case where this rule was deliberately broken?" → Yes, one real, documented exception — Reporting's own Query Builders (file 02, question 6) read Commerce's/Loyalty's Eloquent Models directly for aggregate performance, a considered CQRS exception, not an accidental violation of what CRM/Loyalty established.

🚩 **Red flags:**
Describing CRM and Loyalty as "just two more modules" without recognizing their specific historical role — they're the modules that turned a stated architectural principle into a proven, repeatable pattern for the first time.

---

← [Commerce Core](14-commerce-core.md) | Next: [Finance, Workflows & Reporting](16-finance-workflows-reporting.md) →
