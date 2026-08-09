← [Security](12-security.md) | Next: [Commerce Core](14-commerce-core.md) →

# 13. Payments & Fintech

The Connector pattern's fourth application (file 03, question 5), idempotency on `ConfirmRedirectPaymentAction` (file 05, question 8), the two-tier HTTP mocking discipline for Zibal/Stripe (file 06, question 3), verifying the `FinalizeSuccessfulPaymentAction` refactor (file 06, question 10), webhook HMAC testing (file 06, question 12), the Anticorruption Layer both gateways implement (file 08, question 4), and why `findByIdUnscoped()` is a safe exception to tenant scoping (file 12, question 8) were all already covered from other angles. This file is where the payment/fintech domain itself gets the depth an interviewer will actually probe: why money is never a float, the real architectural fork real gateways forced, and a few honestly-named gaps this project names rather than hides.

---

### Q1: Why is money always represented as an integer — the smallest currency unit — never a float? What would actually go wrong if it were a float?

🎯 **What the interviewer is REALLY testing:**
A foundational fintech correctness rule — does the candidate understand *why*, not just recite "never use floats for money" as a slogan.

✅ **Model answer:**
"Two compounding problems. First, binary floating-point simply can't represent most decimal fractions exactly — `0.1 + 0.2` famously doesn't equal `0.3` in IEEE 754 arithmetic, and money math (splitting a total across tax/discount/line items) does exactly this kind of repeated addition constantly. Second, and more specific to this platform: JSON itself has no distinct integer/float type at the wire-format level — a value that looks like a whole number in one language's JSON encoder can silently round-trip as a float with rounding error by the time another language's decoder reads it back, which matters enormously for a platform whose entire public surface is JSON over MCP. The fix is structural, not a convention hoped for: every Money-shaped field in this codebase — `priceAmount`, `taxAmount`, `discountAmount`, `totalAmount`, `amount` on `Payment` — is an **integer**, always the smallest currency unit (cents for USD), never a float type anywhere in the Domain, DTOs, or wire format."

🔁 **Likely follow-ups:**
1. "Isn't this just the standard 'store money as cents' advice?" → Yes, but the JSON-specific half of the reasoning is the part worth having ready — most 'store as cents' advice is about decimal-arithmetic precision alone, not about a wire format with no distinct float type at all.
2. "Does this rule have a real exception?" → Yes — question 2 of this file covers it directly.

🚩 **Red flags:**
Only citing "floating-point rounding errors" without mentioning the JSON angle — a fine answer for a generic fintech interview, but an incomplete one for this specific platform's own documented reasoning (HANDOFF's own gotcha #4).

---

### Q2: The "smallest currency unit" rule has a real, documented exception. What is it, and how does this project actually handle it?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate knows a clean rule can still have a real, honestly-named edge case — and can explain the concrete failure mode, not just gesture at "currencies are complicated."

✅ **Model answer:**
"Zero-decimal currencies — Iranian Rial (IRR), Japanese Yen (JPY), Korean Won (KRW) — have no minor unit at all, so 'the smallest currency unit' *is* the whole unit. Zibal's own `amount` field is literally whole Rials; there's no `/100` division to apply the way `Money`'s own convention assumes everywhere else. This surfaced for real once Zibal shipped: every existing Dashboard view's `number_format($x / 100, 2)` pattern would silently show an IRR amount **100 times too small** if applied blindly. It wasn't fixed platform-wide — that's a real, separate change touching many unrelated Dashboard/Analytics/Reporting files — it's handled explicitly only where a real buyer actually looks at an amount, `resources/views/payments/confirmed.blade.php`, with the gap itself flagged directly on `Money`'s own docblock and in `docs/payment-gateways.md`'s 'Known gaps' section. This is the exact honest-gap discipline this project applies everywhere: fix the one place a real user is affected today, name the rest as a real, tracked limitation rather than silently leaving it wrong."

🔁 **Likely follow-ups:**
1. "How would you fix this properly?" → A real `displayAmount()` method on `Money` itself that knows which ISO currencies have no minor unit, so every call site gets the correct behavior for free instead of each view needing its own currency-aware branch.
2. "Why not fix it everywhere immediately, since it's a real bug?" → Because the primary Zibal/IRR use case (a real buyer confirming a real payment) was fixed first — the same 'fix what actually affects someone today, name the rest honestly' prioritization this project applies to every debt item, not unique to money.

🚩 **Red flags:**
Not knowing zero-decimal currencies exist at all — a real, common fintech gap that trips up implementations that assume "divide by 100" universally.

---

### Q3: Why couldn't `PaymentGatewayInterface::charge()` — the original, synchronous contract — just be extended to support Zibal and Stripe? What's the real architectural fork?

🎯 **What the interviewer is REALLY testing:**
Recognizing a genuine, structural incompatibility between two request/response shapes, not just "we needed a new class."

✅ **Model answer:**
"`charge()`'s whole contract assumes the caller already has card details in hand and gets an immediate result — `ProcessPaymentAction` charges, places the Order, and records the Payment inside one database transaction, one call. Real gateways don't work that way at all. Zibal: request a `trackId`, redirect the buyer to Zibal's own hosted page, the buyer pays *there* — never on this platform's server — then Zibal calls back, and this platform **must** independently `verify()` server-side, since Zibal's own docs explicitly warn never to trust the callback query string alone. Stripe Checkout Sessions mirror this almost exactly: create a Session, redirect to `session.url`, the buyer pays on Stripe's own page, and only a signature-verified webhook or a server-side Session retrieval (`payment_status === 'paid'`) is ever trusted. Both are async, redirect-based, 'never trust the caller, always re-verify server-side' flows — structurally incompatible with `charge()`'s 'immediate result' shape. The resolution was a **new, parallel** interface, `RedirectPaymentGatewayInterface`, alongside the completely untouched original — `MockPaymentGateway`/`commerce.checkout.process` behave identically to before, proven by the full pre-existing Payment/Checkout test suite passing unmodified."

🔁 **Likely follow-ups:**
1. "Is this the same shape as any other architecture fork in this project?" → Yes — Product Variants extending `Inventory` instead of a second stock column, and Discount Rules reusing `Discount` instead of a second table, are the same 'the existing contract genuinely doesn't fit, build a real parallel path, don't force it' shape, just for a Repository/Entity instead of a payment interface.
2. "How many real implementations does the new interface have?" → Three, registered by name in a Registry — `ZibalPaymentGateway`, `StripePaymentGateway` (both real, Guzzle-backed), and `MockRedirectPaymentGateway` (the safe default) — file 03 (question 5) already covers this Connector-pattern mechanics in full.

🚩 **Red flags:**
Proposing to add an `async: true` flag to the existing `charge()` method — that papers over a genuine shape mismatch instead of admitting the contract itself doesn't fit, the same mistake CQRS's own "never a `$dryRun` parameter" rule (file 10, question 2) already rejects in a different context.

---

### Q4: What is `PaymentSession`, and why does it exist as its own Entity instead of just widening `Payment` itself?

🎯 **What the interviewer is REALLY testing:**
Understanding a real, deliberate modeling decision — introducing a new aggregate specifically to protect an existing invariant, not just "more entities are always fine."

✅ **Model answer:**
"`PaymentSession` bridges 'a redirect-based charge was started' and 'the gateway confirmed it succeeded' — a real gap in time and trust that simply doesn't exist in the old synchronous flow. The reason it can't just be a wider `Payment`: `payments.order_id` is a real, non-nullable foreign key, an existing invariant this project deliberately refused to weaken just to accommodate a new flow — a `Payment` row structurally cannot exist before a real Order does, and an Order can't exist before a charge is confirmed. `PaymentSession` lives entirely in that gap instead — its own `total`/`tax`/`discount` are the pricing **frozen** at `initiate()` time (computed once through a composed call to `CalculatePricingAction`, never recomputed at confirm time), the same 'compute once, apply durably later' principle `Order.tax`/`discount`/`total` already establish. It's a small, real state machine — `Pending -> Completed|Failed|Cancelled`, no path back — with `id`/`providerReference` as one-time mutators, mirroring the shape several other entities in this codebase already use for 'assigned exactly once, right after real persistence.'"

🔁 **Likely follow-ups:**
1. "Could pricing change between `initiate()` and `confirm()`?" → Not for this session — that's exactly why it's frozen at initiate time, not re-derived at confirm; a coupon expiring or a price changing mid-flow doesn't retroactively change what the buyer is actually charged.
2. "What happens to `Payment`/`Order` if the gateway reports failure?" → Neither is ever created — the `PaymentSession` alone transitions to `Failed`, and `payments.order_id`'s own non-nullable invariant is never at risk of a null/orphaned row.

🚩 **Red flags:**
Suggesting `Payment` itself just get a `status: pending` state to cover this gap — that's exactly the invariant-weakening this project explicitly declined to do, since it would let a `Payment` (and transitively code that assumes any `Payment` row means a real, completed charge) exist without a confirmed Order behind it.

---

### Q5: Every capability that touches a redirect-based payment uses a `tracking_reference`, never a gateway's own `trackId`/session id. Why does that specific choice matter?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate sees this as the concrete mechanism that keeps the public API gateway-agnostic, not just an arbitrary naming choice.

✅ **Model answer:**
"`tracking_reference` **is** `PaymentSession`'s own id — a platform-owned identifier, never a gateway's raw `trackId` (Zibal) or session id (Stripe). This is the exact mechanism, not just a naming convention, that keeps `commerce.payment.initiate`/`.confirm`/`.inquiry` gateway-agnostic: a caller never needs to know or care which gateway actually processed a given payment, because the identifier it holds means the same thing regardless. This is the same Anticorruption Layer discipline file 08 (question 4) already covers for `WooCommerceProductConnector` — never let an external system's own raw identifiers or format leak past the boundary that talks to it — applied here to payment gateways instead of a product connector. It also means adding a fourth gateway later needs zero changes to any existing capability's own contract; the new gateway just has to accept and honor the same platform-issued reference the others already do."

🔁 **Likely follow-ups:**
1. "Does a caller ever see Zibal's `trackId` or Stripe's session id at all?" → No, never through the public MCP surface — those stay entirely internal to `ZibalPaymentGateway`/`StripePaymentGateway`'s own implementation, exactly the boundary an Anticorruption Layer is meant to hold.
2. "Is there a security angle to this too?" → A secondary one — file 12 (question 8) covers the real security reasoning for the public callback/webhook routes' own unscoped lookup, which this same platform-owned reference also makes possible.

🚩 **Red flags:**
Describing `tracking_reference` as "just a friendlier name for the session id" — missing that the entire point is it's a genuinely different, platform-controlled identifier, not a renamed passthrough of whatever the gateway happens to call it.

---

### Q6: `FinalizeSuccessfulPaymentAction` was extracted from `ProcessPaymentAction`'s own previously-inline logic. What pre-existing technical-debt item does this extraction actually resolve?

🎯 **What the interviewer is REALLY testing:**
Connecting a concrete refactor to a real, previously-documented architectural concern — showing the fix wasn't incidental, it closed a named gap.

✅ **Model answer:**
"This project's own technical-debt list had already named the exact problem: 'a real Payment Gateway integration needs a transaction-boundary change... a real gateway should charge outside the transaction and only wrap the subsequent DB writes.' Extracting the common 'a charge is now confirmed successful' tail — place the Order, record the Payment, dispatch `PaymentWasProcessed`, apply a Coupon if one was used — into its own Action resolves this naturally, not as a separate fix. `FinalizeSuccessfulPaymentAction` wraps its **own** `DB::transaction()`, which nests safely as a real database savepoint inside `ProcessPaymentAction`'s own wider transaction (zero behavior change there, confirmed by file 06's own refactor-verification question). The real payoff shows up in the new path: `ConfirmRedirectPaymentAction` has **no outer transaction of its own at all**, since the real gateway `verify()` network call it makes first must never hold a database lock open — and it still gets the exact same atomic 'Order + Payment + Coupon apply, all or nothing' guarantee, purely from `FinalizeSuccessfulPaymentAction`'s own inner transaction."

🔁 **Likely follow-ups:**
1. "Why must `verify()` never run inside a transaction?" → A network call to an external gateway can be slow or hang; holding a database transaction (and its row locks) open for the duration of an unpredictable external HTTP call is a real, classic way to cause lock contention and timeouts for completely unrelated requests.
2. "How was this refactor actually confirmed safe?" → File 06 (question 10) covers this directly — the complete pre-existing Payment/Checkout test suite had to stay fully green with zero lines changed, proof the extraction changed structure only, never behavior.

🚩 **Red flags:**
Treating this as "just a code cleanup" — missing that it directly closes a specific, previously-named architectural debt item, which is exactly the kind of connection a strong answer draws.

---

### Q7: One shared route, `GET /payments/{gateway}/callback`, handles every registered gateway's own browser redirect. Why not one route per gateway?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate sees the concrete mechanism that makes adding a new gateway cheap, not just "it's simpler to have fewer routes."

✅ **Model answer:**
"Because `InitiatePaymentAction` always hands every gateway's own `initiate()` call this exact same URL (with the real `PaymentSession` id attached as `?session={id}`) as the `$callbackUrl` — regardless of which gateway is actually being used. `ConfirmRedirectPaymentAction` behind that one route is completely gateway-agnostic itself; it resolves the right `RedirectPaymentGatewayInterface` implementation from the Registry using the `{gateway}` URL segment, the same Registry lookup file 03 (question 5) already covers. This is the concrete payoff of the whole design: adding a fourth or fifth gateway later needs exactly three things — implement the interface, add a small config class, register it — and genuinely zero new routes, zero new Controllers, since the shared route and both new capabilities were already built gateway-agnostic from day one."

🔁 **Likely follow-ups:**
1. "Does the same Action back both the route and the MCP capability?" → Yes — `ConfirmRedirectPaymentAction` backs both `commerce.payment.confirm` and this public route, distinguished only by whether a real, authenticated `tenantId` is available at all.
2. "What happens if an unregistered gateway name hits this route?" → A real, clear failure from the Registry's own lookup — the same "throw a Not-Found-marked exception on an unregistered key" shape every `NameRegistry` in this codebase already establishes (§3 pattern #15).

🚩 **Red flags:**
Assuming each gateway needs its own dedicated callback Controller — missing that gateway-specific behavior lives entirely inside each gateway's own `RedirectPaymentGatewayInterface` implementation, never in the routing/Controller layer.

---

### Q8: Why does the Stripe webhook controller always return a fast `200` the instant the signature is valid — even if downstream processing then fails?

🎯 **What the interviewer is REALLY testing:**
Understanding webhook retry semantics from the *receiving* side — a different angle than the signature-verification/replay-defense questions already covered elsewhere.

✅ **Model answer:**
"Per Stripe's own documented best practice: Stripe automatically retries a webhook delivery that doesn't get a `2xx` response back, on an increasing backoff schedule. If this platform returned a `4xx`/`5xx` for a downstream processing failure that a retry can't actually fix (a bug in this platform's own code, for instance), Stripe would just keep resending the exact same event — a pointless retry storm that never resolves anything, while genuinely valid webhook traffic queues up behind it. The controller's own rule is narrower and more precise: only a genuinely **bad signature** is a real `400` — anything that gets past signature verification, even if something later goes wrong while processing it, gets an honest `200` first. This is a different concern from file 12's own signature-replay-defense coverage (question 7) — that's about rejecting a forged or replayed request; this is about not punishing Stripe's own retry mechanism for a problem retrying can't solve."

🔁 **Likely follow-ups:**
1. "Doesn't that risk silently losing a failed webhook?" → A real, honest risk worth naming — this project doesn't currently have a dead-letter/alerting mechanism for a webhook that returned `200` but then failed downstream; question 9 of this file covers the related, broader reconciliation gap.
2. "Is this the same reasoning as any other part of this platform?" → A related shape, not identical — `SendNotificationAction`'s own retry-with-backoff logic (a different module entirely) is about *this platform* retrying an outbound send; this is about correctly signaling to an *external* system's own retry logic instead.

🚩 **Red flags:**
"Any processing failure should return a 5xx so it gets retried" — a common instinct that's exactly backwards here: it turns a bug that retrying can't fix into an infinite retry storm instead of a single, cleanly-acknowledged event.

---

### Q9: What does "reconciliation" actually mean for this platform today? Is there a real, automated reconciliation job?

🎯 **What the interviewer is REALLY testing:**
Honesty about the difference between "the building block exists" and "the automated process exists" — a distinction this project draws carefully elsewhere too.

✅ **Model answer:**
"Honestly: no automated reconciliation job exists yet. What exists is the real building block a reconciliation job would be built on — `commerce.payment.inquiry`, a thin, read-only status check against the real gateway (matching Zibal's own documented distinction that 'استعلام'/inquiry is explicitly a status check, never a confirmation). Today it's called on demand, by an Agent explicitly asking 'what's the real status of this payment' — nothing schedules or runs it automatically across every open `PaymentSession` to catch one that silently drifted out of sync with its gateway. This sits alongside a related, deliberately-not-half-built gap: `RefundPaymentAction` still never calls a real gateway's own refund API at all — a pre-existing limitation this stage didn't introduce (it didn't call `PaymentGatewayInterface` for refunds before Zibal/Stripe existed either), left honestly unfinished for *both* new gateways equally rather than solved for just one."

🔁 **Likely follow-ups:**
1. "How would you actually build real reconciliation?" → A scheduled command (the same `Schedule::command()` shape this codebase already uses for `loyalty:expire-points`/`commerce:check-abandoned-carts`) that finds every `PaymentSession` still `Pending` past some age and calls the existing `InquirePaymentAction` on each — reusing the mechanism, not inventing a new one.
2. "Why wasn't refund support built for at least one gateway?" → A deliberate scope boundary, not an oversight — half-building it for one gateway only would leave an inconsistent, surprising gap between two 'real' payment gateways that should behave the same way from a caller's perspective.

🚩 **Red flags:**
Claiming reconciliation "happens automatically via the webhook" — a webhook only reports what the gateway chooses to tell you, when it chooses to; it's not a substitute for an active, periodic reconciliation sweep, and this project is explicit that no such sweep exists yet.

---

### Q10: This session actually attempted live calls against both real gateways. Walk me through what happened with each, and what it actually proved.

🎯 **What the interviewer is REALLY testing:**
A real methodology question — can the candidate tell the difference between "a real bug in the code" and "an environment limitation," and prove which one they're looking at rather than guessing.

✅ **Model answer:**
"Two different, both honest, outcomes. `StripePaymentGateway` was confirmed live against the real `api.stripe.com`, using an intentionally invalid test key (no real charge was ever possible) — the request reached Stripe, and Stripe's own real API responded with a genuine `401 Invalid API Key provided`. That's not a failure — it's proof the request shape, form-encoding, and Basic Auth header are all genuinely correct against the *live* API, short only of a real key to complete an actual Session. `ZibalPaymentGateway::initiate()`, by contrast, was attempted against the real `gateway.zibal.ir` and timed out — but instead of assuming that meant a bug, it was actually verified: a plain `curl` to that exact same host, with zero application code involved at all, timed out identically, while `google.com` and `api.stripe.com` both connected fine from the same environment in the same session. That's real proof this specific sandbox's own outbound network simply can't reach Zibal's servers — not a bug in `ZibalPaymentGateway` at all."

🔁 **Likely follow-ups:**
1. "Why go to the trouble of a raw `curl` test instead of just noting 'Zibal timed out'?" → Because 'it timed out' alone is ambiguous between three real, different causes — a code bug, a network restriction, or the gateway itself being down — and only isolating application code entirely (a bare `curl`) tells you honestly which one you're actually looking at.
2. "Was this the same honesty standard applied to the OpenRouter LLM integration?" → Yes, exactly — the identical 'don't claim it works from reading the code, actually run it against something real, and if it fails, prove *why*' discipline this project applied there too.

🚩 **Red flags:**
Concluding "ZibalPaymentGateway has a bug" from a single timeout without the follow-up `curl` verification — jumping to a code-level conclusion from an ambiguous signal is exactly the kind of unproven claim this project's own live-verification discipline exists to avoid.

---

### Q11: Zibal's own `verify()`/`inquiry()` methods are built from "best-effort public knowledge," not the complete docs this stage was actually given. Is that a real problem?

🎯 **What the interviewer is REALLY testing:**
Professional judgment about working with incomplete third-party documentation — an extremely common real-world situation, handled honestly rather than either silently guessing or refusing to proceed.

✅ **Model answer:**
"It's a real, named gap, not a silent risk. The pasted Zibal documentation this stage was actually given had its own 'Verify'/'Inquiry' sections collapsed — the exact request/response **field names** weren't available at all. What *was* available in full, and is what actually matters most: the numeric **result codes** (100/102/103/104/105/106/201/202/203) and **transaction status codes** the implementation switches its real decision logic on — those came verbatim from tables the user's own docs did include completely. So the field-name shape (`{merchant, trackId}` in, `amount`/`status`/`cardNumber`/`paidAt`/`refNumber` out) is a reasonable, well-known-public-API-shape best-effort guess, while the actual branching logic that decides success/failure/pending is accurate and sourced directly. This distinction — and the fact it's a real gap at all — was confirmed with the user as an acceptable, explicitly flagged limitation *before* building, and it's documented directly in `docs/payment-gateways.md`, not silently assumed correct."

🔁 **Likely follow-ups:**
1. "What would close this gap for real?" → Either a fuller copy of Zibal's own docs with the Verify/Inquiry sections uncollapsed, or a real live sandbox call (question 10's own methodology) — either one would let the field names be confirmed directly instead of inferred.
2. "Would you have refused to build this without complete docs?" → No — that would block real, useful progress over a gap that's genuinely closeable later and doesn't affect the actual decision logic, which came from a complete source; the honest move is building what's solid and flagging exactly what isn't, not stalling entirely.

🚩 **Red flags:**
Either silently treating a best-effort field-name guess as fully confirmed (never flagging it) or refusing to build anything until perfect documentation exists — both worse than the actual approach: build what's grounded, name what isn't.

---

### Q12: This platform has no customer-facing checkout page at all. Does that mean the payment-gateway work is unfinished, or premature?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can distinguish "honestly out of scope" from "incomplete" — a judgment call this project makes explicitly, not by accident.

✅ **Model answer:**
"Neither — it's a confirmed, deliberate scope boundary, not an oversight. This platform genuinely has no storefront anywhere in this codebase; its entire public surface is MCP/API plus the specific `routes/payments.php` callback/webhook routes needed to *receive* a gateway's own confirmation. Zibal's/Stripe's own `redirect_url` actually reaching a real buyer's browser is a future frontend's job — a Shopify-style storefront, a custom checkout UI, whatever a real implementer builds on top — never something this platform itself provides. This was confirmed as in-scope reasoning with the user before building, the same way every other genuine scope boundary in this project gets raised rather than silently assumed. Combined with the two other honestly-named gaps this file already covered — the IRR display gap (question 2) and no real reconciliation/refund automation (question 9) — the honest picture is: the payment-gateway *integration* itself is real, live-verified where the environment allowed it, and complete for what it promises; a few real, adjacent pieces (a storefront, reconciliation automation, universal currency display) are knowingly, not accidentally, left for whoever builds the next layer on top."

🔁 **Likely follow-ups:**
1. "Would you build a reference checkout page yourself?" → Only if asked — this platform's own stated identity (main series/file 01) is infrastructure between AI agents and business systems, not a storefront; a full checkout UI is exactly the kind of thing a business built *on* this platform would own.
2. "How would a real frontend actually use `redirect_url`?" → Call `commerce.payment.initiate`, receive `redirect_url` + `tracking_reference`, send the buyer's browser to `redirect_url` directly, then later call `commerce.payment.inquiry` (or just wait for the callback to already have resolved it) using the same `tracking_reference` to show the buyer their real order status.

🚩 **Red flags:**
Calling this "an unfinished feature" without checking whether it was actually confirmed as a scope boundary first — exactly the kind of unverified assumption this project's own "audit before concluding something's missing" discipline (seen throughout `HANDOFF.md`) exists to prevent.

---

← [Security](12-security.md) | Next: [Commerce Core](14-commerce-core.md) →
