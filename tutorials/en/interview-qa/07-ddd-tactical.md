← [Testing & Quality](06-testing-and-quality.md) | Next: [DDD Strategic](08-ddd-strategic.md) →

# 07. DDD Tactical

The [pre-tutorial](../pre-tutorial/04-domain-driven-design.md) and [file 02 of this handbook](02-overall-architecture.md) covered the basics of Entity/Value Object/Aggregate/Domain Event. This file goes deeper: what these look like in this project's real code, and where a subtle decision (like "exactly where should an event be dispatched") genuinely mattered.

---

### Q1: Distinguish Entity from Value Object with a real example from this codebase.

🎯 **What the interviewer is REALLY testing:**
Practical recognition, not just the textbook "Entity has identity, VO doesn't."

✅ **Model answer:**
"`Order` is an Entity — two `Order`s with the exact same amount and the exact same items, if they have different `id`s, are two completely different things; its identity is independent of its value and stays stable over time (as its status moves from `Pending` to `Shipped`). `Money` is a Value Object — two `Money` instances with the same amount and the same currency are semantically **the same** thing, neither one having an `id` at all; if you want to 'change' it, you build a new instance (`add()`), you never mutate the original. The practical code difference: `Order` has an `id()` method set exactly once; `Money` has no `id()` method at all, only `equals()`, which compares value to value, not identity to identity."

🔁 **Likely follow-ups:**
1. "Name a few more Value Objects." → `SKU`, `Email`, `CouponCode`, `Address` — all of them enforce their own validation rules right in their constructor.
2. "Can an Entity have no behavior at all, just data?" → If so, that's a warning sign for an 'anemic model' — question 6 of this file covers exactly that.

🚩 **Red flags:**
"An Entity is anything that gets stored in the database" — Value Objects get stored in the database too (usually as several columns); the real distinction is identity, not storage location.

---

### Q2: What's an Aggregate Root? Why is Order an Aggregate Root but OrderItem isn't?

🎯 **What the interviewer is REALLY testing:**
Understanding the consistency boundary, not just a fancy name for "the main Entity."

✅ **Model answer:**
"An Aggregate Root is the single entry point for changing a whole cluster of related Entities — no outside code ever directly builds or mutates an `OrderItem`; it's always through `Order` (e.g. `Order::addItem()`). The reason: `Order` needs to be able to guarantee rules that depend on several items at once — e.g. 'the sum of item prices must match the recorded subtotal.' If `OrderItem` were independently mutable, nothing would guarantee that consistency is kept. This project's 'Immutable Order Items' pattern takes it one step further: an order's items, once placed, are never mutable at all — a deliberate decision so a placed order stays an untouched historical record, not something that quietly changes later."

🔁 **Likely follow-ups:**
1. "Does OrderItem have its own separate Repository?" → No — `OrderRepositoryInterface` owns persisting `OrderItem` too; no independent Repository is ever built for a child Entity with no independent identity (question 7 of this file).
2. "What other Aggregates share this pattern?" → `Invoice`/`InvoiceItem`, `WarehouseTransfer`/`WarehouseTransferItem` — both repeat the exact same 'items frozen at creation' shape.

🚩 **Red flags:**
Suggesting an independent Repository for `OrderItem` "in case it's needed later" — that's exactly what breaks the Aggregate boundary this whole pattern was designed to protect.

---

### Q3: How does an Entity prevent itself from being constructed in an invalid state?

🎯 **What the interviewer is REALLY testing:**
Understanding that "always valid" has to be guaranteed in the constructor, not through a separate `validate()` method that might get forgotten.

✅ **Model answer:**
"Every Entity's/Value Object's constructor enforces every rule required for validity right at that moment, throwing an exception on any violation — meaning the pattern is 'check, then build,' not 'build, then check.' A concrete example: `SKU`'s constructor runs a regex pattern against the input string; if it doesn't match, `InvalidSKUException` is thrown right there — an invalid `SKU` object **cannot** exist in memory at all. This means anywhere else in the code holding a `SKU` never has to re-check its validity — the type itself is the guarantee."

🔁 **Likely follow-ups:**
1. "Does this apply to rules depending on several fields at once too?" → Yes, exactly question 11 of this file — `DateRange`'s own constructor takes both `start` and `end` and rejects it right there if `end < start`.
2. "What's the difference between this and MCP-level validation (file 05)?" → Two completely separate layers: MCP validation asks 'is the JSON shape correct' (the outer layer); the Entity constructor asks 'is this value valid according to business rules' (the inner layer) — an input can have the right shape but still be Domain-invalid.

🚩 **Red flags:**
Suggesting an `isValid()` method called after construction — this pattern lets an invalid object exist in memory for a while, exactly what strict constructors prevent.

---

### Q4: Why are Value Objects immutable? Give me an example of how "changing" a VO actually works.

🎯 **What the interviewer is REALLY testing:**
Practical understanding of immutability, not just repeating "because it's better."

✅ **Model answer:**
"If `Money` were mutable, two pieces of code both holding a reference to the same `Money` instance could unknowingly mutate the value out from under each other — a classic aliasing bug. Instead, `Money::add(Money $other): Money` returns a **completely new instance**; the original stays untouched. At the Entity level, this means when `PricingService` computes the final price, every step (`Subtotal.add(Tax).subtract(Discount)`) builds a fresh `Money`, and you can safely reuse an intermediate `Money` elsewhere without any fear a later calculation silently mutates it."

🔁 **Likely follow-ups:**
1. "Doesn't this have a memory cost?" → A small, acceptable cost (more temporary instances built) in exchange for eliminating an entire class of aliasing bugs.
2. "Are Entities immutable too?" → No — an Entity is deliberately mutable (since its identity stays stable but its state must be able to change, like `Order::cancel()`); immutability is only a Value Object rule, not an Entity rule.

🚩 **Red flags:**
"Immutability means you can never change the value" — you actually can, just by building a new instance, never by mutating the existing one.

---

### Q5: Where exactly does a Domain Event get dispatched — inside the Entity, or inside the Action? Why does it matter?

🎯 **What the interviewer is REALLY testing:**
A subtle architectural detail that's only answerable by having actually read the real code.

✅ **Model answer:**
"Inside the Action, not inside the Entity. `PlaceOrderAction`, after successfully saving `Order` through its Repository, explicitly calls `event(new OrderWasPlaced($order))` itself — the `Order` Entity itself never has access to the `Event` facade at all (since an Entity is completely framework-independent, file 02 of this handbook). The more important reason for this ordering: if the event were dispatched inside the Entity and ran before the database save actually succeeded, a listener (like `OrderPlacedListener` in Loyalty) could award points for an order that isn't actually persisted yet — a real inconsistency if the save later fails. The Action explicitly dispatches the event *after* confirming the save succeeded."

🔁 **Likely follow-ups:**
1. "Does that mean an event is always dispatched after a transaction commits?" → Usually, yes, but precisely 'after a successful save,' not necessarily after the entire HTTP transaction ends — a subtle distinction covered in more depth in file 09 of this handbook.
2. "Does an event carry the full data, or just an identifier?" → It depends — `OrderWasPlaced` carries the full `Order` (since its real listeners need the full detail), but other events like `InventoryWasCommitted` deliberately carry only identifiers, and the listener re-reads from the Repository itself — this distinction is also covered in file 09.

🚩 **Red flags:**
"I dispatch the event inside the Entity itself so it's available everywhere" — this exactly breaks the Domain layer's framework independence.

---

### Q6: What's an anemic domain model, and how has this project avoided it?

🎯 **What the interviewer is REALLY testing:**
Understanding the difference between "a real Entity with behavior" and "a data bag with all logic living outside it" — a real, valid critique of a lot of self-described DDD implementations.

✅ **Model answer:**
"An anemic model means Entities are just getters/setters and all the real logic (rules, validation, state transitions) lives in an external Service — which effectively means DDD is followed in name only, not in practice. This project deliberately does the opposite: `Order::cancel()` itself checks whether the current status even allows this transition, and if not, throws the exception itself — that logic isn't in `PlaceOrderAction` or some external `OrderService`, it's inside `Order` itself. The same goes for `Coupon::calculateDiscount()`, `Subscription::changePlan()`, `WarehouseTransfer`'s own state machine — all real behavior on the Entity itself, not around it."

🔁 **Likely follow-ups:**
1. "So does an Action have no logic at all?" → It does, but a different kind — coordinating several steps (e.g. 'calculate the price, then decrease stock, then save the order'), not the *rules* of a single Domain decision itself.
2. "Give me an example of a decision that nearly ended up anemic but was fixed." → The exact Product Variants example (file 01 of this handbook) — if a separate, behavior-less stock column had been built on `product_variants`, the inventory logic would've had to be duplicated somewhere else (likely scattered across several Actions); extending the existing `Inventory` kept the behavior in one place.

🚩 **Red flags:**
Defining an Entity purely by its fields and getters/setters, with no mention of any real behavioral method — that itself is a live example of exactly the anemic model the question is about.

---

### Q7: Why do some Entities (like OrderItem or WorkflowRule) have no id at all?

🎯 **What the interviewer is REALLY testing:**
A subtle, recurring detail only discoverable by actually reading the code.

✅ **Model answer:**
"Child Entities that are never referenced independently, apart from their Aggregate Root, deliberately have no independent id at all — like `OrderItem` or `WorkflowRule`. The reason: if no code ever needs to 'just find this one item by its own id' (without going through its parent `Order`), giving it an independent identity adds complexity with no real benefit. This pattern repeats so often it's a formal, numbered gotcha in `HANDOFF.md` — and it connects directly to question 2 of this file: an Entity with no independent identity is exactly the sign it should never be its own Aggregate Root."

🔁 **Likely follow-ups:**
1. "What if you later need to edit one specific item directly?" → Exactly one of this project's own documented technical-debt items (`TicketComment`/`CustomerNote` carry the same limitation) — turning it into an independently-identified Entity is a deliberate, real future decision, not a forgotten bug.
2. "Do these Entities have no database identifier at all?" → They do have a numeric `id` on their database row (since Eloquent requires one) — but the Domain Entity itself never exposes or uses that id in a business-meaningful way.

🚩 **Red flags:**
"Every Entity should always have its own independent id" — that's a false universal rule; independent identity needs a real justification, not a habit.

---

### Q8: How is a state machine implemented on an Aggregate? Give me an example with allowed transitions.

🎯 **What the interviewer is REALLY testing:**
A real code example, not just a generic state-machine definition (the pre-tutorial file 05 covered the concept; this is the real implementation).

✅ **Model answer:**
"`Shipment` has an explicit `ALLOWED_TRANSITIONS` map — a fixed array mapping each current status to its list of legal next statuses (e.g. `Pending → InTransit`, `InTransit → Delivered/Returned/Exception`). The `changeStatus()` method checks this map before every transition and throws `InvalidArgumentException` if the requested one isn't in it — and if it is valid, it also handles the relevant side effects itself (like stamping `shipped_at`) alongside the status change. One subtle, deliberate detail: `Exception` (a shipping-problem state) is deliberately **recoverable** — it can go back to `InTransit` or `Returned`, unlike `Delivered`/`Returned`, which really are terminal — because a real shipping issue is usually resolvable, unlike an actual delivery."

🔁 **Likely follow-ups:**
1. "Why is this map a constant on the class itself, not a database table?" → Because it's a fixed business rule and part of the Domain logic, not data that should change at runtime — putting it in the database would move its meaning from Domain to Infrastructure.
2. "What other Aggregates carry this same pattern?" → `WarehouseTransfer`, `DelegationRequest`, `Subscription` — each with its own transition map, with different meaning (e.g. `Subscription` even has a self-transition from `PastDue` back to `PastDue`, a real correction covered in more detail in file 14 of this handbook).

🚩 **Red flags:**
Suggesting a scattered `if/elseif` chain to check transitions instead of one explicit, centralized map — that's exactly what makes it easy to forget a disallowed transition.

---

### Q9: Why does a Value Object with the same name (like Money) exist duplicated across several modules? Isn't that code duplication?

🎯 **What the interviewer is REALLY testing:**
Understanding the deliberate trade-off between DRY (Don't Repeat Yourself) and module independence.

✅ **Model answer:**
"Yes, it's code duplication — and completely deliberate. Commerce, Finance, and Shipping each have their own fully independent `Money` class, with roughly the same ~40 lines of logic. The reason: making Finance depend on `Commerce\Domain\ValueObjects\Money` would be a direct Domain-layer dependency on a different module — exactly the coupling the rule 'no module directly depends on another module's class' (file 02 of this handbook) exists to prevent. This project has never built a 'shared kernel' for something as small and stable as `Money` — the deliberate decision is that duplicating 40 lines is cheaper than creating a shared dependency between modules."

🔁 **Likely follow-ups:**
1. "If a bug shows up in the Money formula tomorrow, do you have to fix it in several places?" → An honest trade-off — yes, in each independent copy; that's the real cost of this decision, accepted against the alternative cost (coupling between modules).
2. "When would a shared kernel actually be justified?" → When a concept is foundational and stable enough that it practically never changes, and the coordination cost across versions outweighs the benefit of independence — something this project hasn't run into yet.

🚩 **Red flags:**
"This duplication must be a mistake, you should merge them" — without hearing the architectural reasoning behind it, that's a hasty, wrong conclusion.

---

### Q10: What exactly does a Repository interface persist — just the Aggregate Root, or the child Entities too?

🎯 **What the interviewer is REALLY testing:**
Understanding that a Repository's boundary is exactly the Aggregate boundary, not a separate boundary of its own.

✅ **Model answer:**
"A Repository is always responsible for persisting the **whole Aggregate**, not just its Root — meaning `InvoiceRepositoryInterface` persists both `Invoice` and its `InvoiceItem`s, in one coherent operation, never two separate Repositories. This follows directly from questions 2 and 7 of this file: since `InvoiceItem` has no independent identity and is meaningless outside `Invoice`, no independent `InvoiceItemRepositoryInterface` is ever built. A more interesting case: `BulkOperation` deliberately departs from the usual 'resave the whole child collection' pattern — because its items can number in the thousands and get appended one at a time by a long-running Job, `saveItem()` appends a single row directly instead of rewriting the whole collection — a documented exception, not an inconsistency."

🔁 **Likely follow-ups:**
1. "Why doesn't BulkOperation follow the usual pattern?" → Because the usual pattern (rewriting all children on every save) makes sense for a Transfer with a handful of fixed items, but becomes a real performance disaster for thousands of items being appended gradually.
2. "Does that mean no child Entity ever has its own Repository?" → Almost always, except where a child Entity genuinely gains its own independent identity and lifetime — something that hasn't happened in this project to date."

🚩 **Red flags:**
Suggesting a separate Repository for every Entity, with no thought to which ones actually have independent identity and which don't.

---

### Q11: How do you enforce a multi-field invariant (one that depends on several values at once) in an Entity's constructor?

🎯 **What the interviewer is REALLY testing:**
A real example of a rule more complex than "this field must be positive."

✅ **Model answer:**
"`DateRange` (in the Reporting module) is exactly this — its constructor takes both `start` and `end`, and before construction completes, checks `end >= start`; if not, `InvalidDateRangeException`. What's interesting is this constructor does more than validate — it also normalizes the values (`start` to start-of-day, `end` to end-of-day), so a 'single day' range never accidentally loses that day's own data. This shows a constructor can handle both validation **and** normalization at once — both are part of guaranteeing 'this object is always in a meaningful state.'"

🔁 **Likely follow-ups:**
1. "Give me another example of a multi-field invariant." → `WarehouseTransfer::create()` rejects the request if the source and destination warehouse are the same — a rule depending on two values at once, not one.
2. "Should this normalization live inside the VO, or outside it?" → Inside is correct, because a non-normalized `DateRange` should never be able to exist in memory at all — the same 'check, then build' principle from question 3.

🚩 **Red flags:**
Suggesting this kind of rule be validated at the Application layer instead ("I'll check end is after start inside the Action") — that means every other call site building this same Value Object has to repeat that same logic again.

---

### Q12: Give me an example of a real time you forgot to enforce an invariant in the Domain, and it was later discovered.

🎯 **What the interviewer is REALLY testing:**
A real, honest story, not a claim of "we always got everything right from the start."

✅ **Model answer:**
"A real, documented example: when product variants were added to `CartItem`, the real rule was that two different variants of the same product should always be two separate cart lines, never merged into one. But the old database-level constraint (`unique(cart_id, product_id)`) only knew about the product, not the product+variant combination — meaning adding a second variant of the same product could fail with a raw database duplicate error, not a meaningful message. This was caught during the planning stage (not after a real Production bug) — through an explicit audit of the real existing database constraints before implementation — and fixed by widening that same constraint to `unique(cart_id, product_id, variant_id)`, aligned with the exact Domain rule that was already supposed to be enforced."

🔁 **Likely follow-ups:**
1. "Why enforce this in the Domain instead of just the database?" → Both — the Domain rule (`Cart::findItem()` looks up by the product+variant combination) is the first layer; the database constraint is a second, defensive layer for when several concurrent requests race to the same outcome.
2. "Does this show a weakness in your planning process?" → The opposite — it shows the pre-implementation audit process (repeated throughout this codebase) exists exactly for this kind of gap, and it worked.

🚩 **Red flags:**
Having no real story for this question at all — a strong signal the person has never actually designed a nontrivial Aggregate from scratch.

---

← [Testing & Quality](06-testing-and-quality.md) | Next: [DDD Strategic](08-ddd-strategic.md) →
