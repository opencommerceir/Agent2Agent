← [Payments & Fintech](13-payments-fintech.md) | Next: [CRM & Loyalty](15-crm-loyalty.md) →

# 14. Commerce Core

Commerce has already supplied examples across nearly every earlier file — the Product Variants stock-column decision (file 01), the Order→Shipping foreign-key exception (file 04), Immutable Order Items and Domain Event dispatch timing (file 07), the Discount/AppliedDiscount/DiscountRule naming nuance (file 08), `OrderWasPlaced`'s dependency direction (file 09), the checkout Preview-vs-Apply split (file 10), and the real payment-gateway architecture fork (file 13). This file is where the Commerce module itself — the actual business flow and domain rules across catalog, cart, checkout, order, shipping, notifications, and every Phase 5 advanced-commerce feature (variants, warehouses, bulk operations, discount rules, subscriptions) — gets walked through directly, not as a supporting example for some other pattern.

---

### Q1: Walk me through one real customer journey — catalog to a delivered, notified order. Where does each module actually plug in?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can trace a real, multi-module business flow end to end, not just describe one module in isolation.

✅ **Model answer:**
"`commerce.product.search` returns active catalog Products. `commerce.cart.add` calls `AddToCartAction`, which soft-reserves stock through `Inventory::reserve()`. `commerce.checkout.calculate` is a pure preview through `CalculatePricingAction` — subtotal, tax, discount, no side effects (question 2 of this file has the exact formula). `commerce.checkout.process` is the real thing: `ProcessPaymentAction` charges through `PaymentGatewayInterface`, and only on success does `PlaceOrderAction` run — converting the Cart's soft reservation into a hard `Inventory::commit()`, persisting the Order, recording the Payment, and applying any Coupon. `PlaceOrderAction` dispatches `OrderWasPlaced` — Loyalty's `OrderPlacedListener` earns points, Notifications' own Listener sends an order-confirmation message, all without Commerce knowing either module exists (file 09 of this handbook). Later, `shipping.shipment.create` weighs the Order's Products (via `attributes['weight_grams']`, question 11 of this file), prices the shipment through `ShippingRateCalculator`, and writes the assignment back onto the Order via `Order::assignShipping()` (file 04, question 1's own documented exception). Every status change on that Shipment dispatches `ShipmentStatusChanged`, which Notifications' `ShipmentStatusChangedListener` turns into a real tracking-update message to the customer."

🔁 **Likely follow-ups:**
1. "How many modules does one order touch without Commerce ever importing any of them?" → At least three — Loyalty, Notifications, and (if a real tax rate is configured) Finance — every one reached only through a Domain Event or a published Interface Commerce itself defines.
2. "Where's the one place a *later* module writes back onto Commerce's own data?" → `Order::assignShipping()` — file 04 (question 1) covers why that's the one deliberate exception to 'a module only ever reads another module's data through its Repository Interface.'

🚩 **Red flags:**
Describing this as one linear, single-module flow — missing that the real value of this trace is how many *separate* modules participate without Commerce ever coupling to any of them directly.

---

### Q2: What's the exact pricing formula at checkout, and why is tax always computed on the subtotal, never on the discounted amount?

🎯 **What the interviewer is REALLY testing:**
A precise, real business rule — not "tax and discount get applied somehow," but the exact order of operations and why it's fixed that way.

✅ **Model answer:**
"`PricingService`'s formula is exactly `Total = Subtotal + Tax − Discount`, with tax always computed on the full subtotal, never on the subtotal after a discount has already been subtracted from it. This is a deliberate, single-owner rule — one formula, one class, never duplicated or re-derived elsewhere in the checkout path. The reasoning is a real tax-compliance one, not just a coding convenience: in most real tax jurisdictions, sales tax is owed on the value of the goods sold, and a seller-funded discount doesn't reduce the taxable value the same way a price reduction from the manufacturer would — computing tax on the pre-discount subtotal is the safer, more broadly correct default rather than assuming every discount also reduces the tax base."

🔁 **Likely follow-ups:**
1. "Is this formula ever bypassed?" → Never — both `commerce.checkout.calculate` (preview) and `commerce.checkout.process` (the real charge) call the exact same `PricingService`, the same Preview-vs-Apply split file 10 (question 2) already covers, just with this file's own concrete formula behind it.
2. "Does shipping cost factor into this formula?" → No — a real, documented gap (HANDOFF §8.36): `shipping_cost` is only populated *after* an Order is placed and shipped, never part of the upfront checkout total a customer actually pays.

🚩 **Red flags:**
Guessing tax is computed on the post-discount amount "because that's what the customer actually pays" — a common, reasonable-sounding assumption that's exactly backwards from this project's own documented, deliberate rule.

---

### Q3: Why does Inventory need two separate phases — a soft "reserved" hold and a hard "committed" sale — instead of just decrementing stock the moment something's added to a Cart?

🎯 **What the interviewer is REALLY testing:**
Understanding the *business* reason behind the two-phase lifecycle, distinct from file 04's own coverage of the concurrency bug this mechanism is also involved in.

✅ **Model answer:**
"Because 'someone is browsing with this in their cart' and 'someone actually bought this' are genuinely different business facts, and conflating them would break real customer expectations on both sides. If adding to a Cart immediately decremented real stock, an abandoned Cart (which this platform explicitly detects and marks, `CartWasAbandoned`) would have permanently removed inventory from sale for no reason — stock a browsing-but-never-buying customer effectively locked away from every other real buyer. `reserve()`/`release()` is the soft hold that solves exactly this: it makes stock unavailable to *new* reservations without actually reducing what's really on hand, and it can be lifted cleanly if the Cart is abandoned or the item removed. `commit()`/`restore()` is the hard, permanent transition — an actual sale really did happen, stock really did leave the building. Two phases exist because 'might buy' and 'did buy' are different enough business realities that collapsing them into one number would make either browsing or real fulfillment lie."

🔁 **Likely follow-ups:**
1. "Where does the real concurrency risk in this mechanism come from?" → File 04 (question 8) covers that directly — a real race condition in `AddToCartAction` that the Tech Debt Sprint fixed with row-level locking; this question is about the *business* shape, that one's about the *concurrency* bug inside it.
2. "Could a third phase ever be needed?" → Nothing in this platform's own history has needed one — every stage since Phase 2 has fit cleanly into 'held in a cart' vs. 'actually sold,' including Warehouse Transfers (question 7 of this file), which reuse these same two verbs rather than inventing a third state.

🚩 **Red flags:**
Suggesting stock should just decrement immediately on add-to-cart "to keep things simple" — missing the real customer-facing cost (permanently locked stock from an abandoned cart) that simplicity would create.

---

### Q4: `commerce.order.place` still never applies tax or discount, even though the machinery to do so exists via `commerce.checkout.process`. Isn't that a real inconsistency?

🎯 **What the interviewer is REALLY testing:**
Honesty about a real, self-named gap — can the candidate explain *why* an obviously-inconsistent-looking situation was left that way on purpose.

✅ **Model answer:**
"Yes, honestly — and this project names it directly as a real, undecided piece of technical debt rather than hiding it. `commerce.order.place` shipped back in Stage 3, before real pricing (tax/discount, Stage 5) existed at all; today it still places an Order with no tax or discount applied, while `commerce.checkout.process` computes the real formula (question 2 of this file). This wasn't an oversight discovered later — it's a deliberate choice: retroactively changing `commerce.order.place`'s own behavior wasn't asked for by any stage, and doing it anyway would have been a silent behavior change to a capability real callers may already depend on behaving the old way. The honest cost: there are genuinely two ways to place an Order today with meaningfully different pricing outcomes, and this project's own `HANDOFF.md` flags this explicitly as something worth deciding on purpose — either deprecating the older capability, unifying the two, or leaving them both as intentionally different tools — rather than something that already has a resolved answer."

🔁 **Likely follow-ups:**
1. "Which one would you recommend fixing, and how?" → Most likely deprecating `commerce.order.place` in favor of `commerce.checkout.process` for any caller that cares about real pricing, while leaving the old one available for whatever narrow use case never needed pricing to begin with — but this project deliberately hasn't made that call yet.
2. "Is this the same shape as any other honestly-named gap?" → Yes — the same 'name the inconsistency rather than silently pick a side' discipline file 13 (several questions) already applies to payment/fintech gaps.

🚩 **Red flags:**
Claiming this must be a bug rather than a documented, deliberate decision — missing that this project explicitly distinguishes "an accepted, named gap" from "a bug nobody noticed," and this is clearly the former.

---

### Q5: How does `commerce.discount.apply`'s automatic rule resolution actually decide which DiscountRules combine? And does that resolved set ever reach the real checkout total automatically?

🎯 **What the interviewer is REALLY testing:**
A real, non-trivial business rule (Stackability resolution) plus a deliberate, honestly-scoped integration boundary — not just "discounts get combined somehow."

✅ **Model answer:**
"`DiscountRuleEvaluator::selectApplicableRules()` resolves it by `DiscountPriority` and `Stackability`: the highest-priority eligible rule anchors the selection, and only rules sharing that *same* Stackability value can join it — `Stackable` combines only with other `Stackable` rules, `Exclusive` combines only with other `Exclusive` rules, and the two Stackability values never mix with each other. `CouponOnly` rules are filtered out before automatic selection even starts, since they're only ever meant to apply through an explicit, linked Coupon code, never picked automatically. Here's the deliberate scope boundary, though: this resolved winning set from `commerce.discount.apply` is a self-contained Cart preview/browsing surface — it **never automatically reaches** `commerce.checkout.calculate`/`.process`'s own real total. The only checkout-integrated path is a Coupon explicitly linked to a DiscountRule, reached through the coupon code a caller already has to supply. This kept the checkout Actions' own change small and additive rather than a rewrite of their core formula, and it's recorded as a real, honest scope boundary — not a silent gap — since folding the two together is real, tracked future work."

🔁 **Likely follow-ups:**
1. "Why filter `CouponOnly` out before selection instead of during it?" → Because it's not really competing for the same 'automatic, no explicit action from the customer' slot the other two Stackability values fight over — it's a fundamentally different trigger mechanism, so it doesn't belong in that comparison at all.
2. "Is `Discount`/`AppliedDiscount`/`DiscountRule`'s own naming already covered somewhere?" → Yes — file 08 (question 2) covers the Bounded Context naming nuance between them; this question is about the actual stacking business rule, not the naming.

🚩 **Red flags:**
Assuming a resolved Cart-level discount automatically shows up at checkout — exactly the scope boundary this project deliberately drew and documented, not an accidental gap to paper over in an answer.

---

### Q6: Two different Actions create Product Variants — `CreateProductVariantAction` and `GenerateVariantCombinationsAction`. Why do they enforce two different levels of strictness?

🎯 **What the interviewer is REALLY testing:**
Recognizing that two entry points to the "same" operation can have deliberately different guarantees, and knowing exactly why.

✅ **Model answer:**
"`CreateProductVariantAction`'s own `attributes` input (a plain `array<string, string>`, like `['Color' => 'Red', 'Size' => 'L']`) is taken at face value — nothing checks that 'Color'/'Red' actually match a real, registered `VariantAttribute`/`VariantAttributeValue` row. That's a deliberate looseness, the exact same shape `Product.attributes['weight_grams']` already has (question 11 of this file) — a free-form bag, not a validated one. `GenerateVariantCombinationsAction` is the strict, registry-driven counterpart: every attribute and value it uses comes from a real, tenant-owned row, ordered by each attribute's own `displayOrder`, and it computes the full Cartesian product of combinations automatically. It's also idempotent by composition — it calls `CreateProductVariantAction` once per computed combination and silently catches `DuplicateVariantException`, so re-running it after a Product gains a new attribute value only ever creates the genuinely new combinations, never duplicates or errors on the ones that already exist."

🔁 **Likely follow-ups:**
1. "Why not make the direct path strict too?" → Because it's meant for a caller who already knows exactly what they want to create (an ad-hoc, one-off variant), where forcing a registry lookup first would add friction with no real safety benefit; the registry-driven path exists specifically for the 'generate every combination automatically' use case where consistency actually matters.
2. "What stops `GenerateVariantCombinationsAction` from creating duplicates on a second run?" → Nothing has to stop it explicitly — it relies on the same real database-level uniqueness `CreateProductVariantAction` already enforces, catching the resulting exception rather than pre-checking for it.

🚩 **Red flags:**
Calling the free-form path "a bug" because it doesn't validate against the registry — missing that this is the same deliberate, documented looseness the Product `attributes` bag already has elsewhere, not an oversight specific to variants.

---

### Q7: Walk me through a Warehouse Transfer, in terms of what actually happens to real stock at each stage.

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can trace a real, multi-step business operation's actual inventory side effects, not just name the three Action classes involved.

✅ **Model answer:**
"`RequestWarehouseTransferAction` only opens the record — status `Pending`, zero Inventory side effect yet, since a transfer someone merely *proposed* shouldn't already be locking real stock. `ApproveWarehouseTransferAction` is where the first real effect happens: it row-locks the source Warehouse's Inventory (the same `findByProductForUpdate()` concurrency-safety mechanism `AddToCartAction` already uses) and calls `reserve()` — a soft hold, exactly the same verb and exactly the same meaning question 3 of this file already covers for a Cart. `CompleteWarehouseTransferAction` is the hard, final transition: it `commit()`s the source Warehouse's reservation (identical to how a placed Order turns a Cart's hold into a real, permanent sale) and calls `receiveStock()` at the destination — constructing a brand-new, zero-on-hand `Inventory` row first if the destination has never stocked this Product before. `receiveStock()` is deliberately **not** the same method as `restore()`, even though both simply add to `quantityOnHand` — `restore()` means 'reverse a prior `commit()`,' stock returning to exactly where it always was after a cancelled Order; `receiveStock()` means 'stock that was never here before has genuinely just arrived,' a completely different real-world origin story that would make `restore()`'s own 'exact inverse of `commit()`' guarantee false if the two were merged."

🔁 **Likely follow-ups:**
1. "What if the source Warehouse doesn't have enough stock to cover the Approve step?" → A real 409 (`InsufficientWarehouseStockException`) — the same caught-and-translated exception shape a Cart reservation failure already uses, never a raw, lower-level exception leaking through.
2. "Is there a state between Approved and Completed for stock actually in transit?" → `TransferStatus::InTransit` is modeled on the enum but unreached by any Action today — a real, honestly-named 'modeled but not all reachable yet' gap, the same shape several other enums in this codebase carry.

🚩 **Red flags:**
Describing `receiveStock()` and `restore()` as interchangeable "since they both just add stock" — missing the real, documented reason keeping them separate: they answer a genuinely different business question about *where the stock came from*.

---

### Q8: Why does `commerce.bulk.import_products` upsert existing Products rather than failing when one already exists?

🎯 **What the interviewer is REALLY testing:**
Understanding that a bulk operation needs fundamentally different error-handling semantics than a single-record CRUD Action — and recognizing the precedent this project already had for it.

✅ **Model answer:**
"Because a bulk import processing hundreds of rows can't afford to be built on `CreateProductAction`/`UpdateProductAction` — those two throw on exactly the cases a bulk import runs into constantly ('already exists' / 'doesn't exist'), which is the wrong control flow for an operation that has to keep going and report per-row outcomes, not abort the whole file on the first already-existing SKU. `ImportProductsAction` upserts by SKU instead, mirroring a shape this project had already solved once before: `SyncWooCommerceProductsAction`'s own upsert-by-SKU logic from the WooCommerce Connector (file 08's own Anticorruption Layer question touches this same class). Both produce a structured result — success count, failed count, and per-row errors — rather than a single pass/fail outcome, the same 'keep going, report what happened' shape `BulkOperation`/`BulkOperationItem` formalize for every bulk operation in this stage."

🔁 **Likely follow-ups:**
1. "Does every Bulk Operation follow this exact chunking/transaction discipline?" → Yes — file 09 (question 10) already covers the real chunk-transaction/per-row-catch shape every Bulk Operation Job in this codebase uses.
2. "What does 'upsert' key on for Customers instead of Products?" → Email, the same natural, already-unique identifier `ImportCustomersAction` upserts by, mirroring the Product/SKU shape one aggregate over.

🚩 **Red flags:**
Suggesting a bulk import should just call `CreateProductAction` in a loop and catch exceptions per row — technically similar in outcome, but missing that reusing the *already-correct* `SyncWooCommerceProductsAction` upsert shape was a real, precedented choice, not an ad-hoc one invented fresh for this stage.

---

### Q9: Subscriptions bill directly through `PaymentGatewayInterface`, never through a real Cart → Order → Payment pipeline. Why not reuse the existing checkout flow?

🎯 **What the interviewer is REALLY testing:**
Recognizing a real, deliberate architectural boundary — and its honest, named cost — rather than assuming every payment in this platform must flow through the same pipeline.

✅ **Model answer:**
"Because a `SubscriptionPlan` genuinely isn't a Product with Inventory — forcing recurring billing through `AddToCartAction`/`PlaceOrderAction` would have meant one of two bad options: inventing a fake catalog Product per Plan just to satisfy a pipeline that expects one, or bypassing Inventory checks awkwardly for something that was never meant to have stock at all. Instead, `SubscriptionInvoice` charges directly through the same `PaymentGatewayInterface` port `ProcessPaymentAction` already uses, skipping the Cart/Order layer entirely — `SubscriptionInvoice.orderId` stays nullable and is always `null`. The honest cost, named directly rather than hidden: subscription revenue never reaches Reporting's or Analytics' own revenue queries today, since those only ever look at real `orders`/`payments` rows. A future stage wanting subscription revenue inside existing dashboards/reports needs either a real `orderId` writer or a second, Subscription-aware data source added to those Query Builders — real, tracked future work, not a silent blind spot nobody noticed."

🔁 **Likely follow-ups:**
1. "Would inventing a fake Product per Plan have been simpler?" → Simpler in the short term, but it would make Inventory (a real, hardened, heavily-tested system) lie about tracking real physical stock for something that was never physical stock at all — a correctness cost worse than the reporting gap this project chose to accept instead.
2. "Does this affect Discount Rules/Coupons applying to Subscriptions?" → It's a related, separate boundary — Subscription billing doesn't route through `CalculatePricingAction`/`ApplyCouponAction` either, so the same discount-stacking machinery question 5 of this file covers doesn't apply here at all.

🚩 **Red flags:**
Assuming Subscriptions must somehow secretly create a Cart/Order behind the scenes — missing that this is a genuinely separate, deliberately simpler billing path, not a hidden reuse of the Commerce checkout pipeline.

---

### Q10: A brand-new Subscription's first declined charge goes straight to `PastDue` with zero retry grace — but a *renewal* failure gets 3 retries. Why the different policy, and what real bug did this difference cause?

🎯 **What the interviewer is REALLY testing:**
A real, subtle business-policy distinction, plus a genuine integration bug story that only surfaced at the seam between two independently-correct pieces of work — strong evidence of real engineering depth.

✅ **Model answer:**
"The policy difference is a real business judgment call: a brand-new Subscription's very first charge failing suggests something is fundamentally wrong right now — a bad card, no real purchasing intent — so there's no reason to extend automatic retry grace before flagging it. A *renewal* failure, on a Subscription that's already been charged successfully before, is more likely something transient — a card that expired mid-cycle, a temporary decline — so it gets a real 3-retry grace window instead. The real bug this difference caused, caught only by a final integration test, not by either half's own unit tests: `Subscription::markPastDue()`, as first written, had no tolerance for being called on a Subscription that was *already* `PastDue` — exactly the state a brand-new Subscription's own strict, no-grace policy can put it in on its very first failed charge. The 3rd, exhausting retry attempt against that already-`PastDue` Subscription then threw an exception from *inside* a database transaction, silently rolling back that entire retry attempt (including its own failure-recording) with nothing visible anywhere to an operator. Fixed with the same self-transition tolerance `Subscription::renew()` already had for `Active → Active` — `markPastDue()` is now a documented no-op when already `PastDue`."

🔁 **Likely follow-ups:**
1. "Why didn't either half's own tests catch this?" → Because neither slice's own tests happened to retry a Subscription that reached `PastDue` specifically *from its own first-charge failure* — only a real, full end-to-end scenario exercising that exact sequence surfaced it, the same class of 'bug only exists at the seam between two correctly-tested pieces' risk file 09's own Bulk Operations coverage touches from a different angle.
2. "Is retry counting itself unambiguous?" → No, honestly — `retryCount` increments on every failed attempt including the first, so 'exhausted' means 3 total failed attempts, not 3 retries *after* an initial failure; documented as a deliberate, chosen reading of an ambiguous request detail, not left silently ambiguous in the code.

🚩 **Red flags:**
Assuming this bug would have been caught by "just writing more unit tests" — missing the actual lesson: it's a genuine seam-integration bug, the specific kind that only a real, full-sequence end-to-end test (not more isolated unit tests) was ever going to catch.

---

### Q11: Shipping weight comes from `Product.attributes['weight_grams']` — an untyped, unvalidated free-form field, not a first-class Product column. Was that the wrong call?

🎯 **What the interviewer is REALLY testing:**
Judgment about a real, honestly-named modeling trade-off — can the candidate defend a "good enough for now" decision without pretending it's flawless.

✅ **Model answer:**
"It's a real, deliberate, and honestly-named trade-off, not an oversight. `Product` (Commerce's own aggregate) has no first-class Weight concept at all; Shipping needed one, so `CreateShipmentAction` reads it out of the free-form `attributes` bag Phase 1 already established for exactly this kind of ad-hoc, module-specific data — defaulting to 0 grams when it's missing, which is a legitimate, common case (a digital good, or a Product created before Shipping ever existed), not an error. A first-class `weight` column on `products` was actually considered and rejected — it would mean modifying Commerce's own migration and Domain Entity for a concern only Shipping currently needs, where an existing, already-established extension point already covers it. The honest cost, named directly: nothing stops `weight_grams` from being set to a string, a negative number, or simply left out — there's no `InvalidWeightException` for a bad Product attribute the way there is for an actually-constructed `Weight` Value Object; `CreateShipmentAction` only casts defensively, it doesn't validate strictly."

🔁 **Likely follow-ups:**
1. "When would you actually promote this to a first-class field?" → The moment weight-based logic needs to exist in more than just Shipping — the same 'the mechanism/data becomes genuinely cross-cutting' threshold that would justify moving it out of a module-specific attribute bag into Commerce's own real schema.
2. "Is this the same shape as any other 'free-form attribute, not a validated field' decision?" → Yes — `CreateProductVariantAction`'s own free-form `attributes` input (question 6 of this file) carries the identical deliberate looseness, for the identical reason.

🚩 **Red flags:**
Calling this "obviously bad design" without engaging with the real trade-off it avoided — coupling Commerce's own core schema to a concern only one downstream module currently has.

---

### Q12: Across every Phase 5 feature this file covered — variants, warehouses, bulk operations, discount rules, subscriptions — what's the one architectural move that shows up literally every single time?

🎯 **What the interviewer is REALLY testing:**
A closing, synthesizing answer — can the candidate see the one recurring principle underneath five superficially different features, not just recall each one individually.

✅ **Model answer:**
"Extend the existing aggregate or mechanism with something additive — an optional trailing parameter, a new nullable column, a new field on an existing Entity — rather than build a second, parallel mechanism next to it. `Inventory` gained `variantId`, then `warehouseId`, instead of two separate stock-tracking tables (file 01, file 04 question 9). `Discount` gained an optional `discountRuleId` instead of a second, competing `AppliedDiscount`-for-Orders table (file 08, question 2). `Coupon` gained the same field for the identical reason. `BulkOperation` reused the exact upsert shape `SyncWooCommerceProductsAction` had already proven, rather than inventing new error-handling semantics from scratch (question 8 of this file). Even Subscriptions, which genuinely *couldn't* reuse the Cart/Order pipeline (question 9), still reused the existing `PaymentGatewayInterface` port rather than inventing a new payment abstraction. The single underlying reason this keeps happening: every one of these features touches code that's *already* heavily tested and depended-upon, and this project has repeatedly found that widening a proven, hardened mechanism — verified by the fact that the complete pre-existing test suite kept passing unmodified, every single time — is safer than standing up a second, independently-drifting one next to it."

🔁 **Likely follow-ups:**
1. "Is there a name for this pattern in `HANDOFF.md` itself?" → Yes — §3 patterns #5 (the two-phase Inventory lifecycle itself) and #6 ('widen a signature with optional trailing parameters rather than branching or duplicating an Action') are the two formal, numbered versions of exactly this principle.
2. "Has this principle ever been wrong to apply?" → Yes, once, deliberately — file 13 (question 3) covers the one real counter-example: `RedirectPaymentGatewayInterface` genuinely couldn't extend the existing synchronous `charge()` contract, because the two shapes were structurally incompatible, not just superficially different.

🚩 **Red flags:**
Listing the five features individually without naming the one underlying principle connecting them — exactly the kind of surface-level recall a synthesizing closing question is designed to distinguish from real, structural understanding.

---

← [Payments & Fintech](13-payments-fintech.md) | Next: [CRM & Loyalty](15-crm-loyalty.md) →
