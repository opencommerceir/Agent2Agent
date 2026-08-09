← [Laravel & Design Patterns](03-laravel-and-design-patterns.md) | Next: [API Design](05-api-design.md) →

# 04. Database & Performance

If the [pre-tutorial](../pre-tutorial/02-databases-and-performance.md) covered the foundational concepts, this file is interview-level — where you need to walk through a real schema decision or a real performance bug in full detail.

---

### Q1: What does this project's schema design look like? Why does every module own its own tables?

🎯 **What the interviewer is REALLY testing:**
Does the database design actually follow the code's architecture, or is it a completely separate, unrelated layer?

✅ **Model answer:**
"The schema directly mirrors the code's modular boundaries — every module owns its own tables (`products`, `tickets`, `invoices`, ...), and there's almost never a real, direct foreign key between two different modules' tables; the relationship is carried through plain `id`s (with no database-level constraint), exactly the way modules only connect through interfaces at the code level. There's one documented, deliberate exception: when Shipping needed to know which Shipment fulfills which Order, instead of building a join table, three nullable columns (`shipping_method_id`, `shipment_id`, `shipping_cost`) were added directly to the `orders` table — the first time a later module ever altered an earlier module's own table, and that decision is fully documented in its own migration's docblock, not an accident."

🔁 **Likely follow-ups:**
1. "Why not use real FKs between modules?" → Because a database-level FK couples one module to another at the schema level — exactly what we prevent at the code level through Repository interfaces.
2. "Doesn't that mean orphaned data is possible?" → Yes, at the database level; integrity is enforced through Application logic instead (e.g. checking an Order actually exists before creating a Shipment), not a DB constraint.

🚩 **Red flags:**
Assuming "every table must have real FKs" with no understanding of the trade-off between referential integrity and module independence.

---

### Q2: How is multi-tenancy implemented at the schema level? Why this approach, instead of a separate database per Tenant?

🎯 **What the interviewer is REALLY testing:**
Real understanding of the trade-offs between multi-tenancy isolation models (this gets covered in more depth in file 11; here it's just the schema angle).

✅ **Model answer:**
"The 'shared database, `tenant_id` on every table' model — the simplest to build and maintain at early scale, and more importantly, this Phase-1 decision deliberately kept the 'database-per-tenant' migration path open: because every Repository always filters explicitly by `tenant_id` (never a generic, unfiltered query that gets patched later), physically splitting one Tenant's data into its own separate database is an Infrastructure change, not a business-logic rewrite."

🔁 **Likely follow-ups:**
1. "How do you make sure a Repository never forgets to filter by `tenant_id`?" → An honest answer: today, through code review and a fixed discipline ('every Repository method's first parameter is `tenant_id`') — exactly why question 3 of this file explains how dangerous forgetting it would be.
2. "When would it make sense to migrate to separate databases?" → When a single Tenant alone puts meaningful load on the shared database, or a real regulatory requirement demands genuine physical isolation.

🚩 **Red flags:**
Not knowing this was a deliberate decision with a pre-planned migration path, and only saying "because it was simpler."

---

### Q3: What's the biggest security/performance risk of the "shared database with `tenant_id`" model?

🎯 **What the interviewer is REALLY testing:**
Deep understanding of cross-tenant data leak risk, not just a surface-level answer.

✅ **Model answer:**
"A query that forgets to filter by `tenant_id` can expose one Tenant's data to another — a silent bug, no runtime error, just wrong data. This project actually had a real instance of exactly this class of bug — not in a Repository query itself, but in caching: when `CacheService` was first turned on for products, the initial cache key was just `commerce:product:{id}` — meaning a product with `id=5` from Tenant A could get served back to Tenant B too (which happens to have the same numeric `id=5`, since IDs are a single global sequence). The fix was adding `tenant_id` into the cache key itself (`commerce:product:{tenantId}:{id}:v1`) — and a direct regression test proves exactly this scenario (two different Tenants with the same numeric product id)."

🔁 **Likely follow-ups:**
1. "How was this bug found?" → During an architecture audit before Cache was ever turned on for the first time (main series file 10) — another instance of the 'audit before building' pattern.
2. "Where else could this same risk show up?" → Anywhere a global (not tenant-scoped) identifier is used as the sole component of a cache key or an external identifier — a general rule this bug itself taught the team.

🚩 **Red flags:**
Saying "you just always filter by `tenant_id`" without a real example of a place where that rule almost got broken.

---

### Q4: How was indexing decided in this project? Give me an example of a wrong proposed index that got rejected.

🎯 **What the interviewer is REALLY testing:**
Was indexing a thought-out process, or just "index every column that shows up in a WHERE clause"?

✅ **Model answer:**
"During the Performance Optimization stage, a requested list of indexes was audited against the real schema — and two of them didn't even reference columns that actually existed: one wanted to index `kpi_values.type`, when `type` actually lives on the parent `kpis` table, not `kpi_values`; another wanted `member_roles.tenant_id`, when that table has no `tenant_id` column at all (only a polymorphic `member_type`/`member_id` pair). The final migration added only the 8 genuinely new, schema-correct indexes — the rest either already existed or weren't even valid. This shows that an indexing request, like any other request, has to be checked against the real schema, not blindly executed."

🔁 **Likely follow-ups:**
1. "How did you determine which indexes were actually needed?" → By examining the real query patterns of each capability (e.g. `crm.ticket.list`, which filters simultaneously by `status`+`customer_id`, meaning a compound three-column index is actually needed).
2. "Can too many indexes be a problem too?" → Yes — every index has a write cost (slower INSERT/UPDATE) and disk overhead; that's exactly why duplicate or invalid indexes were deliberately not added.

🚩 **Red flags:**
"Just index every column that might ever be used" — it signals no understanding of the write-cost vs. read-speed trade-off.

---

### Q5: Walk me through the N+1 problem in this project — give me a real instance that was found and fixed.

🎯 **What the interviewer is REALLY testing:**
A real performance-debugging experience, not just a textbook definition of N+1.

✅ **Model answer:**
"A full code audit found exactly 4 real N+1 cases — all in methods reading a `hasMany` relation (like `items`, `rules`, `actions`) without eager-loading it. The most important one was `EloquentWorkflowRepository::findActiveByEventType()` — not because it was a high-traffic page, but because this method runs on **every single** domain event dispatch ('stock got low,' 'cart was abandoned'), meaning the N+1 cost was being paid continuously during normal operation. The fix was a plain `->with(['rules', 'actions'])` — a change to query *count* only, never to the *result* — confirmed by a brand-new regression test (`OrderRepositoryEagerLoadingTest`, for a similar case): the query count between 1 order and 4 orders must stay flat, not grow linearly."

🔁 **Likely follow-ups:**
1. "How did you detect N+1 in the first place? What was the initial heuristic, and why was it changed?" → The initial proposal was 'a fast query is probably N+1,' which is actually a wrong heuristic (a fast query can be perfectly normal, and a slow N+1 on a large table can also be slow for other reasons); it was replaced with the real, standard heuristic: the same query shape repeated several times within a short window, independent of how fast it is.
2. "Is this detection automated or manual?" → `performance:check-lazy-loading` today runs that exact correct logic as an ongoing regression guard so a future N+1 doesn't get reintroduced unnoticed.

🚩 **Red flags:**
Defining N+1 only as "slowness" without pointing to the precise cause (query *count*, not query speed).

---

### Q6: How is Cache used in this project? Why only on one module, not everywhere?

🎯 **What the interviewer is REALLY testing:**
Do you apply Cache everywhere thoughtlessly, or do you know where it's actually worth it and where the risk outweighs the benefit?

✅ **Model answer:**
"`CacheService` (tag-aware, with Hit/Miss tracking through `PerformanceMonitor`) is wired into exactly one real path — reading a product (`GetProductAction`) — not all 10 modules. This was deliberately 'a real, tested mechanism plus one complete example, not a half-finished blanket rollout.' Invalidation happens on exactly the same computed key too — `UpdateProductAction`/`DeleteProductAction` both call `CacheService::forget()` using the same key formula `GetProductAction::cacheKey()` generates, so the two can never drift apart."

🔁 **Likely follow-ups:**
1. "Why isn't real Redis installed?" → This dev environment has no real Redis; `CACHE_STORE=database` is active, but `predis/predis` was added as a real, installable dependency, and the recommended production value is documented in `.env.example` — 'real infrastructure assumed in production, honestly not verified in this environment.'
2. "Why is `ArrayStore` (the test cache) good enough for testing tag-aware behavior?" → Because it was confirmed that `ArrayStore` extends `TaggableStore`, meaning the tag-based tests exercise real behavior, not a Redis-only path this environment couldn't actually run.

🚩 **Red flags:**
Claiming "we cache everything" when the reality is one reference path was wired — honesty about a feature's actual scope matters more than an impressive-sounding overstatement.

---

### Q7: Why use Eloquent aggregates (SUM/COUNT) instead of summing in a PHP loop? Give me an example.

🎯 **What the interviewer is REALLY testing:**
Real understanding of the performance difference between letting the database compute something vs. pulling every row into the application.

✅ **Model answer:**
"This is exactly why the Reporting module needed a deliberate architectural exception (question 6, file 02 of this handbook) — computing 'total revenue this month' through a Repository means fetching thousands of full `Order` Entities and manually summing them in a PHP loop, which both uses a lot of memory and does something the database itself can do in milliseconds with a `SUM()`. `RevenueQueryBuilder` runs directly against the Eloquent Model and uses `whereExists` (not a JOIN) to count orders with at least one `completed` payment — deliberately `whereExists` instead of a JOIN, so an order with more than one successful payment row for any reason never gets double-counted."

🔁 **Likely follow-ups:**
1. "Why is this exception scoped only to Reporting/Analytics, not everywhere?" → Because this exception is only safe for pure read operations (SELECT-only); nowhere in this project uses this pattern for writes.
2. "Why is a JOIN dangerous here instead of whereExists?" → Because a JOIN against the `payments` table could repeat the same order multiple times in the result set if it has several successful payment rows, incorrectly inflating its summed amount.

🚩 **Red flags:**
Not knowing the difference between `whereExists` and a JOIN in terms of double-counting risk; or saying "I always just use an Eloquent Collection and `->sum()`," which is the exact same PHP-loop anti-pattern, just hidden behind a method call.

---

### Q8: Where did real concurrency locking actually become necessary in this project? Tell me about a real bug that happened without it.

🎯 **What the interviewer is REALLY testing:**
Deep understanding of race conditions, not just the generic "locking prevents simultaneous access" definition.

✅ **Model answer:**
"During a technical debt review, a real concurrency race scenario was found: `AddToCartAction` used to read the available stock (`available()`), decide whether there was enough to reserve, and then write the reservation — with no transaction or lock between those two steps. If two Agents did this at exactly the same moment for the last unit of stock, both could see the same 'sufficient' number and both reserve it — overselling past the real available inventory. The fix was adding `findByProductForUpdate()` to `InventoryRepositoryInterface` (a query with a real `lockForUpdate()`), and wrapping the whole reservation step in a `DB::transaction()` — meaning the second request genuinely waits for the first to fully finish, instead of both seeing a stale snapshot of the same stock."

🔁 **Likely follow-ups:**
1. "Is this pessimistic or optimistic locking?" → Pessimistic — it assumes real contention will happen, so the lock is taken right at that moment, rather than checking a version/timestamp afterward.
2. "What's the cost of pessimistic locking?" → Concurrent requests against the same row serialize (they wait) — for a high-traffic single row (like a very popular product), this can become a real bottleneck; a real scalability discussion covered further in file 20 of this handbook.

🚩 **Red flags:**
Suggesting "just wrap it in a try/catch" for a race-condition problem — it shows the person doesn't understand the difference between a logic error and a real timing race.

---

### Q9: Why are `variant_id`/`warehouse_id` on `inventories` both nullable? How does the unique constraint actually behave because of that?

🎯 **What the interviewer is REALLY testing:**
A subtle, real SQL detail that only someone who actually wrote this migration would know.

✅ **Model answer:**
"When product variants were added, `Inventory` gained an optional `variantId` (instead of a separate table), and the unique constraint widened from `unique(tenant_id, product_id)` to `unique(tenant_id, product_id, variant_id)`. The subtle, documented detail: in MySQL/SQLite, every `NULL` value in a unique index is treated as 'distinct' from every other `NULL` — meaning that constraint alone doesn't actually stop two inventory rows for the same parent-level product (both with `variant_id = NULL`). The real safeguard is at the Application level: `EloquentInventoryRepository::save()` always runs a full lookup on the tuple (`tenant_id`, `product_id`, `variant_id`) before inserting — that find-or-new logic, not the DB constraint itself, is the real guarantee."

🔁 **Likely follow-ups:**
1. "Why document this as an accepted limitation instead of treating it as a bug?" → Because fixing it (e.g. using a sentinel value instead of NULL) would add more complexity than the problem the Application-level find-or-new already solves.
2. "Do you see this same pattern anywhere else?" → Yes — when `warehouse_id` was added later, the exact same widening (`unique(tenant_id, product_id, variant_id, warehouse_id)`) and the same NULL-distinctness caveat were deliberately repeated.

🚩 **Red flags:**
Not knowing this specific NULL behavior in unique indexes — a classic SQL detail a lot of developers don't know until they've personally hit it.

---

### Q10: How are complex queries (like a sales report) designed for performance? Where did you explicitly push back on an unrealistic request?

🎯 **What the interviewer is REALLY testing:**
Can you reject an unrealistic request with sound technical reasoning?

✅ **Model answer:**
"A performance request asked for an 'Order Creation' benchmark that would actually create 50 real fake orders every time it ran, to measure timing. It was rejected — because a benchmark tool a real operator might run against a production database must never *write* fake data: fake stock gets decremented, fake revenue leaks into real reports, and there's no safe cleanup path. Instead, the benchmark focused on two naturally read-only paths — product search and KPI calculation — exactly the same two paths Cache/N+1 work (previous questions) had already touched, without mutating anything in the database at all."

🔁 **Likely follow-ups:**
1. "How did you explain this limitation to whoever requested it?" → With direct reasoning: 'a benchmark has to be safe to run against production, or nobody will ever actually use it' — not a baseless rejection, a specific technical argument.
2. "So how do you actually measure order-placement performance?" → Through real Feature tests (which create orders in a test environment, not production) and real production response-time monitoring (`RecordPerformanceMetrics` middleware), not a manual benchmark tool that writes data.

🚩 **Red flags:**
Accepting a dangerous request ("sure, I'll build a benchmark that creates real orders") just because it was asked for, with no thought to the production consequences.

---

← [Laravel & Design Patterns](03-laravel-and-design-patterns.md) | Next: [API Design](05-api-design.md) →
