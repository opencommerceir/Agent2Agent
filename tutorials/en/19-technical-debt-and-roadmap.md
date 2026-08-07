← [Install, Run and Test](18-install-run-and-test.md) | Back to: [Table of Contents](00-table-of-contents.md)

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

## Category three: real infrastructure only simulated in dev

- Real Redis isn't installed in this environment; `predis/predis` is a real dependency, but `CACHE_STORE=database` is currently active.
- No live run against OpenAI/Claude/OpenRouter exists — every test uses simulated HTTP.
- Real test coverage hasn't been measured yet — this dev environment has neither PCOV nor Xdebug (both needed to measure coverage); only a real CI run can produce that number.

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

## How to use this tutorial going forward

If you're going to work on this project, here's the suggested approach:

1. Read files 01 through 17 at least once, in order, to build a complete mental map.
2. Actually run file 18's steps so you see the live demo with your own hands.
3. Review file 17's checklist before making any code change.
4. If a big architectural decision comes up, follow the same method this project always used: **ask first, then build.**

---

This is the end of the English tutorial. For deeper, more technical detail on any point, `HANDOFF.md` at the project root is always the final authority.

The Persian version of this same tutorial lives in `tutorials/fa/`.

← [Install, Run and Test](18-install-run-and-test.md) | Back to: [Table of Contents](00-table-of-contents.md)
