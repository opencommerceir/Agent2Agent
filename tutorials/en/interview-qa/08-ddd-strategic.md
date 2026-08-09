← [DDD Tactical](07-ddd-tactical.md) | Next: [Event-Driven & Messaging](09-event-driven-messaging.md) →

# 08. DDD Strategic

The last file went inside one module (Entity, Aggregate). This file zooms out: why the boundaries between modules are drawn exactly where they are, not somewhere else — questions usually only asked in Architect-level interviews.

---

### Q1: What exactly is a Bounded Context? Is every module in this project a Bounded Context?

🎯 **What the interviewer is REALLY testing:**
Understanding that a Bounded Context is a *conceptual/linguistic* boundary, not just a physical folder boundary.

✅ **Model answer:**
"A Bounded Context is a boundary inside which one model and one Ubiquitous Language have a fixed, unambiguous meaning — outside that boundary, the same term can mean something completely different. In this project, every module (`app/Modules/*`) is exactly one Bounded Context — not just an organizational folder, but a real linguistic boundary: the word 'Discount' in Commerce means a price reduction on an order; no other module uses that same word with that same meaning, and if it did, that itself would be a sign of boundary leakage. The physical boundaries (each module's four layers, file 02 of this handbook) come directly from these conceptual boundaries, not the other way around."

🔁 **Likely follow-ups:**
1. "Are all modules the same size as Bounded Contexts?" → No — Commerce, with its own 67 capabilities, is a much bigger Bounded Context than CRM (5 capabilities); a Context's size depends on the real complexity of the domain, not a rule of equal division.
2. "Is Core a Bounded Context too?" → A different kind — Core is an 'infrastructure' Context (identity, permissions), not a business Context; file 02 of this handbook covered exactly this distinction.

🚩 **Red flags:**
Defining a Bounded Context only as "a folder" — this conceptual/linguistic distinction is exactly what separates strategic DDD from simple technical partitioning.

---

### Q2: Why do Commerce and Finance both have a "Money" concept but count as two separate Bounded Contexts, not one?

🎯 **What the interviewer is REALLY testing:**
Understanding that sharing a term alone doesn't mean sharing a Context.

✅ **Model answer:**
"Because 'Money' has a different operational meaning in each — in Commerce, Money is always attached to an `Order`/`Cart` and follows pricing rules (`PricingService`); in Finance, Money is attached to an `Invoice`/`TaxRate` and follows completely different tax/accounting rules. This is exactly the reason file 07 of this handbook (question 9) gave for `Money`'s deliberate duplication — but here, from the strategic angle: that duplication isn't just a technical decision, it reflects the fact that 'money' is two different Domain concepts with different rules in these two Contexts, even if their PHP implementations look similar."

🔁 **Likely follow-ups:**
1. "So should a shared term never be merged?" → Only when it's genuinely the same Domain concept, with the same rules, in both Contexts — never just because the name matches.
2. "Give me another example of this 'same name, different meaning' situation." → `TaxRegion::default()` in Finance is just a default tax rate fallback; 'Default' elsewhere in this project (like a Config's own default values) means something entirely unrelated.

🚩 **Red flags:**
Suggesting "since they both have Money, let's merge them" — that's exactly ignoring that a shared term alone isn't sufficient reason to merge two Contexts.

---

### Q3: Which Context Mapping patterns has this project actually used?

🎯 **What the interviewer is REALLY testing:**
Familiarity with the standard strategic DDD vocabulary (Shared Kernel, Customer-Supplier, Anticorruption Layer, ...) and the ability to map them to real code, not just memorized names.

✅ **Model answer:**
"Several patterns show up simultaneously. **Customer-Supplier through a published interface**: Commerce (the Customer/consumer) defines a `TaxRateProviderInterface` because it's the one that needs it; Finance (the Supplier) implements it — the dependency direction always flows from the consumer toward the contract, never the reverse. **Anticorruption Layer**: `WooCommerceProductConnector` never lets WooCommerce's external model into the platform directly, always translating to `UCPProduct` first (question 4 of this file). And one deliberately rejected pattern: **Shared Kernel** — this project has never built a shared kernel for something like `Money` (file 07, question 9), because it judged the version-coordination cost higher than the benefit."

🔁 **Likely follow-ups:**
1. "Do you have Conformist too?" → Yes, question 9 of this file covers exactly a real instance.
2. "What about Open Host Service?" → Question 11 of this file — the MCP Gateway itself is exactly this pattern.

🚩 **Red flags:**
Not being able to name at least two real Context Mapping patterns with a specific code example — that's exactly what this question is testing.

---

### Q4: Where is an Anticorruption Layer actually implemented in this project?

🎯 **What the interviewer is REALLY testing:**
A concrete example of a strategic term that comes up often in Architect interviews.

✅ **Model answer:**
"`WooCommerceProductConnector`. WooCommerce's own data model (an external system fully outside this platform's control) never enters this platform's Domain layer directly — this connector is the first and only place that sees WooCommerce's raw format, and it immediately translates it into `UCPProduct` (the internal normalized model, question 5 of this file). If WooCommerce renames a field or changes its structure tomorrow, only this one class needs to change — the rest of the platform, which only ever works with `UCPProduct`, never even notices. This is exactly what an Anticorruption Layer exists for: keeping an external system's model 'corruption' from leaking into a clean internal model."

🔁 **Likely follow-ups:**
1. "Is this pattern only for WooCommerce?" → No — the same principle, at a smaller scale, repeats in every implementation of the Connector pattern (pre-tutorial file 05): `ZibalPaymentGateway`/`StripePaymentGateway` also never let each gateway's raw format leak into the rest of the system.
2. "Why is this layer one-directional (inbound only), not outbound too?" → Because this platform doesn't write data back to WooCommerce yet (only reads) — `OrderConnectorInterface` is modeled but has no real implementation; a bidirectional ACL, once writing is added, would need this same pattern in the opposite direction too.

🚩 **Red flags:**
Giving an abstract definition of an ACL with no mention of `WooCommerceProductConnector` or any other real example — one of the clearest signs of shallow familiarity.

---

### Q5: Why is UCP (Universal Commerce Protocol) designed as a Published Language?

🎯 **What the interviewer is REALLY testing:**
Understanding a less commonly known strategic pattern (Published Language) and recognizing it in real code.

✅ **Model answer:**
"A Published Language means a documented, stable data model/language that several systems/Contexts agree on for exchanging data — not either system's own internal format, an independent contract. UCP is exactly this: `UCPProduct` and its siblings aren't this platform's own raw database format, nor WooCommerce's raw format — a third, normalized language both sides (internal source, external source) translate into. This is exactly why a product coming from WooCommerce, instead of keeping WooCommerce's own format, gets converted into `UCPProduct` right away — and the exact same model is used for this platform's own native products too; the rest of the system never has to know where a product actually came from."

🔁 **Likely follow-ups:**
1. "Is UCP the same as the DTO we saw in file 05?" → Similar in shape (a normalized data structure), but different in role: a DTO is the boundary between Domain and outside one module; UCP is the boundary between several *data sources* for the same Domain concept.
2. "Why is it called a 'protocol,' not just a 'model'?" → Because it's meant to stay stable and documented, exactly like a real protocol — changing it needs the same caution as API versioning (file 05).

🚩 **Red flags:**
Treating UCP and an ordinary output DTO as the same thing — these are closely related but strategically different concepts.

---

### Q6: What's the Customer/Supplier relationship between Commerce and Finance? Which one is upstream, which is downstream?

🎯 **What the interviewer is REALLY testing:**
Precise understanding of this relationship's direction — a lot of people get it backwards.

✅ **Model answer:**
"Commerce is the Customer (downstream); Finance is the Supplier (upstream) — but here's the subtle part: **the contract is defined by the Downstream, not the Upstream**. Commerce, because it has a real need for a tax rate, defines `TaxRateProviderInterface` itself — exactly the way it wants to consume it. Finance just implements this pre-defined contract, rather than imposing its own interface. This is precisely the reverse of a common misconception — a lot of people assume the 'supplier' should define the contract; in the real Customer-Supplier pattern with a consumer-side published interface, it's exactly the opposite."

🔁 **Likely follow-ups:**
1. "What if Finance isn't even installed?" → Commerce works completely standalone — `NullTaxRateProvider` (Commerce's own default) always returns null, which Commerce interprets as a flat 9% fallback rate; the Downstream is never hard-dependent on the Upstream existing.
2. "Is this same pattern elsewhere?" → The exact same shape repeats for the Connector pattern (payments, shipping, notifications) — every time, the consuming module defines the interface, the real implementation comes from outside.

🚩 **Red flags:**
Saying "Finance defined the interface because it's the one supplying the rate" — that's exactly backwards from what actually happens in the code.

---

### Q7: How does the Ubiquitous Language differ between two Contexts? Give me an example of a term with a different meaning in two modules.

🎯 **What the interviewer is REALLY testing:**
A precise example proving you've actually seen this difference in the code, not just know it theoretically.

✅ **Model answer:**
"`Discount` is a good example — in the Commerce module, `Discount` is a frozen, historical record showing what discount was *actually applied* to an order; `AppliedDiscount` is its temporary, cart-side equivalent. But that same word, in the DiscountRule discussion (Phase 5), refers to a *potential* rule that may never actually apply. These meanings were close enough that a real architectural decision was needed — not building a completely separate, parallel `AppliedDiscount` for both concepts (which would create two sources of truth), but extending the existing `Discount` with an optional `discountRuleId` — exactly the same 'Cart/Order Duality' pattern file 07 of this handbook showed (Immutable Order Items), one layer up."

🔁 **Likely follow-ups:**
1. "Does that mean the Ubiquitous Language always has to be fully unique?" → Not necessarily unique across the whole platform — only *within* one Context does it need one single, unambiguous meaning.
2. "Give me another example." → 'Customer' in CRM means an entity with a Ticket/Note history; the same 'Customer' in Commerce means an entity with an order/cart history — both point to the same database record, but each from its own distinct semantic angle.

🚩 **Red flags:**
Thinking a term must have exactly one meaning across the entire platform — that's exactly the opposite of the whole point of a Bounded Context.

---

### Q8: Is the Agent Orchestrator a business Bounded Context? Why is it different from the others?

🎯 **What the interviewer is REALLY testing:**
Recognizing that not every module is the same kind of Context — some are "domains," some are "coordinating layers."

✅ **Model answer:**
"Not in the same way as the other modules. Commerce/CRM/Finance are each a real business domain with their own rules. The Agent Orchestrator has no business logic of its own at all — its job is only to coordinate the capabilities of *other* Contexts. This difference directly shaped its dependency architecture: unlike every other business module, which depends on other modules' Repository interfaces, the Agent Orchestrator depends directly on Core's own Actions (`DiscoverCapabilitiesAction`, `CheckPermissionAction`) — the exact same mechanisms the MCP Gateway itself uses. This is a deliberate, documented architectural exception, precisely because this module isn't a 'Context' in the usual sense — it's an orchestration layer sitting on top of the other Contexts."

🔁 **Likely follow-ups:**
1. "So does it have no Ubiquitous Language of its own?" → It does, but its language is about the planning process itself (Goal, Plan, Capability, Execution), not about a product or an order — a language about 'how work gets done,' not 'what work gets done.'
2. "Does that mean it can't be forked into a new domain?" → The opposite — precisely because it carries no domain-specific logic at all, it stays right alongside the Core in the 'fork into a new industry' model (main series file 22); only the real business modules get replaced."

🚩 **Red flags:**
Treating the Agent Orchestrator as just another ordinary domain module — it signals the real difference between a business Context and an orchestration layer wasn't understood.

---

### Q9: Where is the Conformist pattern seen — where does one module accept another's model unchanged?

🎯 **What the interviewer is REALLY testing:**
A less common Context Mapping pattern a lot of people know by name only, not by example.

✅ **Model answer:**
"The Reporting module is a real, documented, deliberate instance of Conformist — not in a negative sense, a pragmatic decision. Reporting's Query Builders (`SalesQueryBuilder` and its siblings) run directly against Commerce's/Loyalty's own Eloquent models, with zero translation or Anticorruption Layer — meaning Reporting deliberately *conforms* to these two modules' data model, instead of building an independent one. This is the exact opposite of an ACL (question 4): an ACL says 'never accept an external model directly'; Conformist says 'sometimes, when translation costs more than it's worth, accept that same model directly.' Here, the justification was: since these are purely SELECT-only and the goal is just a fast aggregate (SUM/COUNT), building a separate model for it would add complexity with no real benefit."

🔁 **Likely follow-ups:**
1. "Why isn't this dangerous?" → Because this exception is fully documented and scoped to exactly those same few Query Builder classes (file 02 of this handbook, question 6) — a deliberate coupling, not a hidden leak.
2. "What if Commerce's schema changes?" → Those same few Query Builder classes have to change — that's the accepted cost of conforming, exactly the same real, accepted risk a genuine Conformist relationship always carries.

🚩 **Red flags:**
Defining Conformist only as "one module copies another module's code" — the real definition is about *accepting a data model*, not copying code.

---

### Q10: Why did the boundary between modules shift sometimes — like Analytics becoming dependent on Reporting's Query Builders? Isn't that a strategic DDD violation?

🎯 **What the interviewer is REALLY testing:**
Understanding that Bounded Context boundaries aren't set in absolute stone, but any change to them still has to be deliberate and justified.

✅ **Model answer:**
"It's not a violation, it's a deliberate second-order decision. When Analytics was being built, the original request wanted a fully independent module recomputing everything from scratch by querying Commerce's/Loyalty's tables directly — meaning a second, potentially-diverging source for the exact same numbers ('total revenue') Reporting already computed. The final decision was for Analytics to call Reporting's own Query Builders directly instead of building an independent path — meaning the existing Conformist boundary (question 9) extended one layer further, rather than the core Bounded Contexts (Commerce, Loyalty) being touched at all. This follows exactly the same strategic principle: a Domain concept should have only one source of truth; sometimes achieving that means extending an existing exception, not building a brand-new one."

🔁 **Likely follow-ups:**
1. "Why didn't Analytics connect directly to Commerce instead, only to Reporting?" → Because Reporting had already solved these exact computations — Analytics connecting directly to Commerce would recreate the same core problem (two sources of truth), just with one more layer of indirection.
2. "Does that mean boundaries are always negotiable?" → No — the core boundaries (Core independent of any domain, each business module independent of the others) have never been questioned; this was only an already-existing exception (the Reporting CQRS carve-out) getting repeated once more, not a new boundary being broken."

🚩 **Red flags:**
"Boundaries should always be fixed and unchangeable" — strategic DDD is about *deliberate* boundaries, not *sacred and immutable* ones.

---

### Q11: What is an Open Host Service? How does the MCP Gateway demonstrate this pattern?

🎯 **What the interviewer is REALLY testing:**
Connecting a strategic DDD term to what file 02 of this handbook already explained from a general architecture angle — seeing one concept from two different perspectives.

✅ **Model answer:**
"An Open Host Service means a Context, instead of building a separate, dedicated contract for every external consumer, publishes one single, general protocol that everyone connects through. The MCP Gateway is exactly this — instead of every AI agent getting a custom integration with each module, one single protocol (discover + execute, pre-tutorial file 10) makes the entire platform accessible. This is exactly what distinguishes a protocol from a bespoke API — and, as main series file 20 explains, this exact property is what lets all five SDKs connect through this same one protocol with zero server-side custom code."

🔁 **Likely follow-ups:**
1. "What's the difference between Open Host Service and Published Language (question 5)?" → They complement each other: Published Language standardizes the *shape of the data* (UCP); Open Host Service standardizes the *access mechanism* (the MCP Gateway) — one is about data, the other is about protocol.
2. "Does every module have its own Open Host Service?" → Not in that sense — every module's capabilities are published through that same one shared gateway; the Open Host Service exists at the whole-platform level, not per individual module."

🚩 **Red flags:**
Not knowing this term, or being unable to distinguish it from Published Language — these two are frequently confused.

---

### Q12: If you had to add a completely new Bounded Context (not commerce) to this platform, how would you decide its boundaries?

🎯 **What the interviewer is REALLY testing:**
An Architect-level answer that pulls every concept from this file together into one real decision process.

✅ **Model answer:**
"First, I'd extract the new domain's own Ubiquitous Language from real domain experts in that field — not from a pre-made technical template. Then I'd ask the same question every existing boundary in this project already asked: which terms only make sense *inside* this new domain (and should stay inside the boundary), and which concepts genuinely need to be shared with Core (identity, permissions — always consumed through Core's own published interfaces, never directly). Then I'd decide whether this new domain has a real need for any existing *business* domain (not Core) — if so, I'd build a Customer-Supplier relationship through an interface the new domain itself defines (question 6), not a direct dependency. Finally, I'd follow the same seven-step pattern (file 02 of this handbook) — which is itself a direct outcome of this exact strategic DDD exercise, not a separate template from it."

🔁 **Likely follow-ups:**
1. "Have you actually tried this process for real?" → Exactly what the main tutorial series (file 22) describes as the 'fork into a completely new industry' model — the Core and the Orchestrator layer stay unchanged, only the new domain replaces the business modules.
2. "What's the biggest risk of getting these boundaries wrong?" → A Context drawn too large slowly turns into the same boundary erosion file 02 (question 12 of this handbook) already covered; a Context drawn too small splits its own model into meaningless fragments that don't tell a complete story on their own.

🚩 **Red flags:**
Opening the answer with "I'd create a new folder under `app/Modules`" with zero mention of language/conceptual boundary analysis — that answers the question at a purely technical level, not the strategic level the interviewer is actually asking about.

---

← [DDD Tactical](07-ddd-tactical.md) | Next: [Event-Driven & Messaging](09-event-driven-messaging.md) →
