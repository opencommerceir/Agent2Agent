# Real Payment Gateways — Zibal + Stripe

> Added in §7.37 in `HANDOFF.md` (after §7.36, not a Phase Stage). This
> is the how-to-use guide; `HANDOFF.md` §7.37 carries the full build
> narrative, including the architecture fork this stage resolved (the
> pre-existing `PaymentGatewayInterface::charge()` is synchronous —
> real gateways are not) and the Stripe API research this stage verified
> live rather than from memory.

## What it is

Two real, redirect-based payment gateways — Zibal (Iranian IPG) and
Stripe (via Checkout Sessions) — alongside the platform's existing
`PaymentGatewayInterface`/`MockPaymentGateway` (the synchronous,
`commerce.checkout.process` path, completely unchanged by this stage).
Both gateways implement one new, shared contract:
`RedirectPaymentGatewayInterface` (`getName()`/`initiate()`/`verify()`/
`inquiry()`), resolved by name through `PaymentGatewayRegistry` — the
same Connector Pattern `ConnectorRegistry`/`ShippingProviderRegistry`
already establish (HANDOFF §3 pattern #15).

**Why a separate interface, not an extension of the existing one:**
`PaymentGatewayInterface::charge()` assumes the caller already has card
details in hand and gets an immediate result back. Real gateways don't
work that way — Zibal and Stripe are both "redirect the buyer to a
hosted page they pay on, then confirm server-to-server afterward" flows.
Forcing that shape into `charge()` would have meant either a fake
synchronous wrapper around an inherently async process, or rewriting
`ProcessPaymentAction` (one of the most heavily-tested Actions in this
codebase) for a case it was never designed for. See `HANDOFF.md` §7.37
for the full reasoning.

## The flow

1. **Initiate** (`commerce.payment.initiate`) — computes real pricing
   (composes `CalculatePricingAction`, so it's the exact same numbers
   `commerce.checkout.calculate` would show), creates a `Pending`
   `PaymentSession` (this platform's own record of "a charge was
   started"), asks the named gateway to start a real charge, and returns
   a `redirect_url` plus an opaque `tracking_reference` — the
   `PaymentSession`'s own id, **never** a gateway-specific trackId/
   session id. A caller never needs to know which gateway is actually
   behind it.
2. **The buyer pays** on the gateway's own hosted page — this platform
   never sees a card number.
3. **Confirm** — happens automatically via `routes/payments.php`'s two
   public routes (`GET /payments/{gateway}/callback`, and, for Stripe
   specifically, `POST /payments/stripe/webhook`) — or explicitly via
   `commerce.payment.confirm` if a caller wants to poll/retry. **Never
   trusts anything the callback/webhook payload merely claims** — always
   re-asks the gateway itself (`verify()`) before an Order/Payment is
   ever created. Idempotent: confirming an already-`Completed` session
   just returns the same Order/Payment again.
4. **Inquiry** (`commerce.payment.inquiry`) — a read-only status check,
   never mutates anything.

## Configuration

```env
PAYMENT_GATEWAY=mock          # mock | zibal | stripe — the default when a caller omits `gateway`

ZIBAL_MERCHANT=zibal          # Zibal's own public test account — real, safe, no live contract needed
ZIBAL_BASE_URL=https://gateway.zibal.ir
ZIBAL_TIMEOUT=15

STRIPE_SECRET_KEY=            # empty by default — get a free test key from dashboard.stripe.com
STRIPE_WEBHOOK_SECRET=        # from Stripe's own Webhooks dashboard once an endpoint is registered
STRIPE_BASE_URL=https://api.stripe.com
STRIPE_TIMEOUT=15
```

`PAYMENT_GATEWAY` stays `mock` by default — the same "safe default,
explicit opt-in for real infra" reasoning `PLANNER_TYPE=deterministic`
already establishes. A caller can still opt into `zibal`/`stripe` per
call via `commerce.payment.initiate`'s own `gateway` input regardless of
this default.

## Using it

```json
POST /mcp/v1/execute
{"capability": "commerce.payment.initiate", "input": {"cart_id": 42, "gateway": "zibal"}}
```

```json
{"redirect_url": "https://gateway.zibal.ir/start/159664422333", "tracking_reference": 7, "gateway": "zibal"}
```

Send the buyer to `redirect_url`. Once they pay, Zibal's own callback (or
Stripe's own webhook) confirms automatically — nothing else to call. To
check in manually:

```json
POST /mcp/v1/execute
{"capability": "commerce.payment.confirm", "input": {"tracking_reference": 7}}
```

## Adding a new gateway

This is the one thing this stage was explicitly designed to make cheap
— Iranian or foreign, it's the same three steps, and nothing else in this
codebase needs to change:

1. Implement `RedirectPaymentGatewayInterface`
   (`app/Modules/Commerce/Application/Services/`) — `getName()`,
   `initiate()`, `verify()`, `inquiry()`. Follow `ZibalPaymentGateway`/
   `StripePaymentGateway`'s own Guzzle `base_uri` convention exactly
   (ends with `/`, request paths never start with `/` — see either
   class's own docblock for why this matters more than it looks like it
   should).
2. Add a small `*Config` class (`fromConfig()` reading
   `config('payment_gateways.{name}.*')`) and its own block in
   `config/payment_gateways.php` + `.env.example`.
3. Register it in `CommerceServiceProvider::boot()`:
   `$gateways->register('newgateway', new NewGateway(NewGatewayConfig::fromConfig()));`

No new capability, no new route, no new Controller. The shared
`GET /payments/{gateway}/callback` route already works for any gateway
whose own flow relies on a browser redirect landing back on this
platform. Only add a dedicated webhook route (mirroring
`POST /payments/stripe/webhook`) if the new gateway has its own
separate, authoritative async signal beyond that redirect — most Iranian
IPGs (Zibal included) don't; Stripe does.

## Known gaps (documented, not silently missing)

- **No customer-facing checkout page** — deliberately out of scope; this
  platform has no storefront, only MCP/API + the callback/webhook routes.
  A future frontend consumes `redirect_url` however it wants.
- **`RefundPaymentAction` still never calls a real gateway API** — a
  pre-existing gap this stage didn't touch (it didn't call
  `PaymentGatewayInterface` either, before this stage existed).
- **Zibal's `verify`/`inquiry` response field names** (`amount`/
  `status`/`cardNumber`/`paidAt`/`refNumber`) are implemented from
  Zibal's well-known public API shape — the result/status **codes**
  this stage's own implementation switches on are taken verbatim from
  Zibal's own documentation tables, but the exact response body field
  names for those two endpoints specifically weren't in the docs this
  stage was given (collapsed sections) — confirmed as an acceptable,
  flagged gap before building, not silently guessed.
- **`Money`'s own "amount is always the smallest unit" convention means
  something different for IRR** (and other zero-decimal currencies) than
  for USD/EUR — no `/100` division applies. Handled explicitly at the
  one place a real buyer actually sees an amount
  (`resources/views/payments/confirmed.blade.php`); not fixed
  platform-wide (a real, separate, pre-existing gap in `Money`'s own
  display convention, touching many unrelated Dashboard views).
- **No live verification against a real Zibal/Stripe API from the
  automated test suite** (every test injects a Guzzle `MockHandler`, the
  same discipline every external Connector in this codebase already
  uses). A manual, one-off live attempt against both real APIs *was*
  made during this stage's own build (outside the test suite,
  `HANDOFF.md` §7.37/§8.100): `StripePaymentGateway` reached the real
  `api.stripe.com` and got a genuine `401` from an intentionally invalid
  key, confirming the request shape/auth/`base_uri` resolution are all
  correct live; `ZibalPaymentGateway` timed out reaching
  `gateway.zibal.ir` from that particular dev environment (confirmed via
  a plain `curl` to the same host also timing out — not an application
  bug) — still genuinely unverified live, a real next step from an
  environment that can reach it.

## Testing

No live credentials/network access assumed from the automated suite —
`ZibalPaymentGatewayTest`/`StripePaymentGatewayTest` both inject a Guzzle
`MockHandler`, plus a reflection-based regression test
(`test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint`)
that reaches the real, un-mocked `base_uri`-building constructor branch
without a network call — the exact branch a real bug already slipped
through once this session, in `OpenRouterClient` (HANDOFF §7.35), before
this convention existed. `StripeWebhookVerifierTest` self-generates every
signature it checks (valid, tampered, expired, wrong secret) rather than
needing a real webhook delivery. `PaymentGatewayCapabilityTest`/
`PaymentCallbackRouteTest` exercise the full flow end to end over real
MCP/HTTP requests using the `mock` gateway.
