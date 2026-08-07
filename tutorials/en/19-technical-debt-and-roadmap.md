← [Install, Run and Test](18-install-run-and-test.md) | Next: [Integration and Usage Paths](20-integration-and-usage-paths.md) →

# 19. Technical Debt and the Road Ahead

No real project is ever "100% finished" — and being honest about what's still unfinished is itself a mark of engineering quality. This file organizes the most important known, documented items (the full, numbered list lives in `HANDOFF.md`, section §8 — over 90 entries).

## Why this list matters

Every item here was **deliberately** recorded, not a forgotten bug. That distinction matters: documented technical debt means the team knows exactly where a boundary was drawn and why — and anyone continuing the work later doesn't have to rediscover it from scratch.

## Category one: capabilities built but not wired to MCP

Around 18 Actions across every module are fully built and tested, but have no MCP capability wired to them — simply because nothing has requested one yet. Examples: removing a cart item, cancelling an order (via MCP), updating a ticket status, removing a tag. Each is roughly a 10-line addition (one capability definition + one handler) whenever it's actually needed.

## Category two: deliberate scope boundaries

- `commerce.order.place` still doesn't apply tax/discounts — only `commerce.checkout.process` computes real pricing. That means there are two order-placement paths with different pricing behavior today.
- Automatic Cart-level discounts (file 11) still don't reach the real Checkout total — only through an explicit coupon.
- Subscription revenue doesn't yet reach Reporting/Analytics reports, since `SubscriptionInvoice.orderId` is always null.
- There's still no "subscription expiring soon" notification — only payment failure has a wired notification.
- `RefundPaymentAction` still never calls a real gateway's own refund API (neither Zibal's nor Stripe's) — a pre-existing gap the real payment gateways work didn't change.
- This platform still has no customer-facing checkout page — only MCP/API + the Admin Dashboard. The `redirect_url` that `commerce.payment.initiate` returns is waiting on a future frontend to actually put it in front of a real buyer.
- `Money`'s "amount is always the smallest currency unit" convention doesn't hold for zero-decimal currencies (Rial, Yen, Won) — handled explicitly in exactly one place (`resources/views/payments/confirmed.blade.php`), not across the whole Admin Dashboard/Analytics/Reporting.

## Category three: real infrastructure only simulated in dev

- Real Redis isn't installed in this environment; `predis/predis` is a real dependency, but `CACHE_STORE=database` is currently active.
- A live run against a real OpenRouter model has actually happened and is verified (with real credentials — it even caught and fixed a real `base_uri`-construction bug), but there's still no live run against real OpenAI/Claude — those two are still tested against simulated HTTP only.
- Real test coverage hasn't been measured yet — this dev environment has neither PCOV nor Xdebug (both needed to measure coverage); only a real CI run can produce that number.
- A live attempt against Zibal from this dev environment timed out (confirmed to be this environment's own network being unable to reach `gateway.zibal.ir`, not a code bug — Stripe and google.com both connected fine from the same environment at the same time); Stripe was actually reached with a deliberately invalid test key and got back a real 401 from `api.stripe.com` — proof the request shape is correct, just not yet a complete transaction with a real key.

## Category four: no Admin Dashboard pages for the newer modules

None of Phase 5's resources (warehouses, product variants, bulk operations, discount rules, subscriptions) and **none of Phase 6's five stages** (goals/executions, agent profiles, memory & learning, multi-agent collaboration, reasoning) have a dedicated Admin Dashboard page — every Action is ready and tested, only the Blade page and controller are missing. Documented recommendation: a single unified `/dashboard/agents` page covering all five Phase 6 areas is more valuable than five separate small ones.

## Category five: honest simplifications

These aren't bugs — they're deliberate decisions that are simple but genuinely correct:

- `CustomerRetentionRate`/`CustomerLifetimeValue` are a simple formula, not a real cohort model.
- `PatternExtractor` (learning) is just a substring check over 5 fixed keywords, not real semantic similarity (embeddings).
- `ExpirePointsAction` is a simple FIFO, not a precise per-lot ledger.

## A few security/operational notes before any real deployment

- The default Admin Dashboard user (`admin@opencommerce.test`) must be changed or removed.
- Persistent database connections (`DB_PERSISTENT_CONNECTIONS`) deliberately default to `false` — enabling it in a multi-tenant app carries a real data-leak risk between requests; only enable it after careful measurement.
- WooCommerce credentials are per-deployment, not per-tenant — a real SaaS needs per-tenant connector credential management.

## Suggested next steps (a few real options, not an exhaustive list)

Straight from this project's own experience:

1. **Feed reasoning's Alternatives back into planning** — today they're only recorded and displayed, never read.
2. **A unified `/dashboard/agents` page** for all five Phase 6 areas.
3. **Add a real `delegate` step to more profiles** — today only `ceo.php` has one.
4. **Stand up a real Redis instance and measure real CI coverage.**
5. **Sync subscription revenue into reports/KPIs.**
6. **Implement a real USPS/FedEx/DHL shipping connector** — `MockShippingProviderAdapter` is already a template for this.
7. **Retry the live Zibal round-trip from a network that can actually reach it**, and get a real Stripe test key to complete one full Checkout Session end to end — both are the cheapest next real steps for file 21.
8. **Add `refund()` to `RedirectPaymentGatewayInterface`** for Zibal/Stripe.
9. **Build a real, customer-facing checkout page** — the natural next consumer of the `redirect_url` that `commerce.payment.initiate` already returns today.

## How to use this tutorial going forward

If you're going to work on this project, here's the suggested approach:

1. Read files 01 through 17 at least once, in order, to build a complete mental map.
2. Actually run file 18's steps so you see the live demo with your own hands.
3. Review file 17's checklist before making any code change.
4. If a big architectural decision comes up, follow the same method this project always used: **ask first, then build.**

---

One file remains: now that you've seen the full architecture, every module, the AI layer, and the known technical debt, the next file answers a completely different, practical question — **what is this project actually good for, and how can others make use of it?**

← [Install, Run and Test](18-install-run-and-test.md) | Next: [Integration and Usage Paths](20-integration-and-usage-paths.md) →
