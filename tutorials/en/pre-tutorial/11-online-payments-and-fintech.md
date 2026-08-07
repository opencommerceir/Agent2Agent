← [The MCP Protocol & Agent Ecosystem](10-the-mcp-protocol-and-agent-ecosystem.md) | Next: [Professional Engineering & Business Concepts](12-professional-engineering-and-business-concepts.md) →

# 11. Online Payments & Fintech

Moving real money is one of the most sensitive things software can do — a bug here means either a customer pays twice, or the merchant never gets paid at all. This chapter unpacks the concepts this platform needed for real payments (Zibal/Stripe).

## Payment Gateway

**Simple definition:** a specialized external service (like Zibal or Stripe) that actually takes card/bank details and moves the money — so the store itself never directly sees or stores sensitive card information.

**Why it matters:** directly holding card data yourself is both a real security risk and usually requires strict legal certification (like PCI DSS) — outsourcing this to a specialist removes that burden from the store.

📍 **In this project:** `PaymentGatewayRegistry` (chapter 5 of this pre-tutorial, the Connector pattern) has three implementations: one fake one for testing, and two fully real gateways — Zibal (Iranian) and Stripe (international).

## The Redirect-Based Payment Flow

**Simple definition:** unlike a "synchronous, instant" payment (where your system directly takes card details and gets an immediate answer), in this model: (1) you tell the gateway "I want to charge this amount," (2) the gateway returns a URL, (3) you redirect the buyer to that URL (the gateway's own page), (4) the buyer pays **there**, on the gateway's server, not on your site, (5) the gateway tells you the payment succeeded.

**Why it matters:** this is exactly the model almost every real payment gateway in the world (not just Zibal/Stripe) uses — because it lets you never directly see card details at all.

📍 **In this project:** this was exactly the big architectural decision that separated real payments from the old fake one (`MockPaymentGateway`, which was synchronous and instant) — a whole new interface (`RedirectPaymentGatewayInterface`) was needed, because this flow simply doesn't fit the "charge and get an immediate answer" shape at all (`HANDOFF.md` §7.37).

## Inquiry vs. Verify/Confirm

**Simple definition:** "Inquiry" means just asking "what's this payment's status right now?" — with zero side effects on the system. "Confirm" means officially and permanently finalizing the payment (actually placing the order for real).

**Why it matters:** these two must never be conflated — checking a status should never have side effects.

📍 **In this project:** `commerce.payment.inquiry` only reads, never changes anything; `commerce.payment.confirm` actually finalizes the order — and this second one is **Idempotent** (explained below).

## Webhook

**Simple definition:** unlike the usual "client requests, server responds" model, with a Webhook it's the external service itself (like Stripe) that, when something happens (e.g. a payment completes), sends an HTTP request to *your* address to notify you — without you ever having asked it to.

**Why it matters:** this is the only reliable way to be sure that even if a buyer closes their browser and never returns to your site, you still find out about the final payment outcome.

📍 **In this project:** `POST /payments/stripe/webhook` is exactly this — Stripe notifies this address regardless of whether the buyer ever returns to the site or not.

## HMAC Signatures and Webhook Verification

**Simple definition:** since a Webhook is just an ordinary HTTP request, technically anyone could pretend to be the real Stripe. To prevent this, Stripe attaches a "signature" (using a shared secret key, via HMAC) to every webhook; your server recomputes that same signature with the same key and compares them — if they don't match, the request is rejected.

**Why it matters:** without this check, anyone could send your site a fake "payment succeeded" request.

📍 **In this project:** `StripeWebhookVerifier` performs exactly this HMAC-SHA256 computation, following Stripe's own official documentation exactly (verified live during this session, not assumed from memory) — and it even accounts for a subtler detail: Stripe sometimes sends several signatures at once (for a key-rotation window), and any one of them matching should be accepted, not only the first.

## Idempotency

**Simple definition:** means if an operation (especially a sensitive one like "finalize this order") is run **twice** with the exact same input, the result is exactly the same as if it ran once — not two orders, not stock decreased twice.

**Why it matters:** a Webhook can genuinely be sent more than once for the same event due to network conditions (Stripe itself officially says so) — or the buyer's browser could return at the same time the webhook arrives. Without idempotency, this means the order gets processed twice.

📍 **In this project:** `ConfirmRedirectPaymentAction` deliberately never re-processes a PaymentSession that's already completed — it just returns the same earlier result again.

## The Smallest Currency Unit

**Simple definition:** to avoid decimal-number rounding errors (which can be dangerous in financial math), money amounts are usually stored as a whole integer, in the smallest unit of that currency — e.g. a dollar amount is stored as `1250` cents instead of `12.50`.

**Why it matters:** adding/subtracting whole integers is always exact; doing the same with decimal numbers sometimes isn't.

📍 **In this project:** an honest, important note: this convention means something different for a currency like the Iranian Rial, which has no smaller decimal unit at all — this project honestly documents this as a known, flagged detail (not a hidden bug) in its own docs.

## Refund

**Simple definition:** returning money from a previously successful payment, usually through the same payment gateway the money was originally taken through.

📍 **In this project:** another honest, flagged detail: the internal mechanism for a refund exists, but it isn't actually wired up to either real gateway's own refund API (neither Zibal's nor Stripe's) yet — exactly the kind of "documented technical debt" chapter 8 of this pre-tutorial talked about.

---

← [The MCP Protocol & Agent Ecosystem](10-the-mcp-protocol-and-agent-ecosystem.md) | Next: [Professional Engineering & Business Concepts](12-professional-engineering-and-business-concepts.md) →
