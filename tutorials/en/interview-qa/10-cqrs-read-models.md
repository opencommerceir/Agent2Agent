← [Event-Driven & Messaging](09-event-driven-messaging.md) | Next: [Multi-Tenancy](11-multi-tenancy.md) →

# 10. CQRS & Read Models

File 02 of this handbook (question 6) saw the Reporting module's CQRS exception from the angle of module boundaries. This file zooms into the pattern itself — why this project has a *light* version of CQRS, not the full textbook version, and exactly where that decision plays out inside Reporting/Analytics.

---

### Q1: What exactly is CQRS? Does this project have the full form (separate read/write databases) or a lighter version?

🎯 **What the interviewer is REALLY testing:**
Understanding that CQRS is a spectrum, not an on/off switch — and whether the candidate knows exactly where this project sits on that spectrum.

✅ **Model answer:**
"CQRS (Command Query Responsibility Segregation) means the 'change data' path (Command) and the 'read data' path (Query) have two fully separate models — in the full version, usually even two separate physical databases, with a sync mechanism between them. This project has a **lighter** version: one shared physical database, but the read path (Reporting's Query Builders, file 02 question 6) is completely separate from the write path (each module's own Repository/Entity), reading the Eloquent model directly for fast aggregates, never going through a full Entity. This means we get CQRS's main benefit (an optimized read model, with no Entity-reconstruction overhead) without the operational cost of the full version (two databases, sync latency)."

🔁 **Likely follow-ups:**
1. "Why didn't you go with the full form of CQRS?" → Question 10 of this file covers exactly that trade-off.
2. "Does this lighter version have a real limitation?" → Yes — since it's a shared database, Reporting's heavy reads can affect Commerce's write performance; in the full form, these would be completely isolated.

🚩 **Red flags:**
Saying "this project doesn't have CQRS because it's not two databases" — that shows the person only knows CQRS's extreme version, not as a genuine spectrum of solutions.

---

### Q2: Why is every Action either a Command or a Query, never both? Give me an example of an attempt to break this rule.

🎯 **What the interviewer is REALLY testing:**
Understanding that CQRS isn't just one big architectural decision, it's enforced at the discipline of every single Action too.

✅ **Model answer:**
"This project has an explicit, repeated pattern: 'preview vs. durable apply' (pattern #4 in `HANDOFF.md`). `CalculatePricingAction` is a pure Query — it computes and returns the final price, changing nothing in the database. `ProcessPaymentAction` is a Command — it actually charges the payment, creates the order, decreases stock. These are deliberately two fully separate Actions, not one Action with a boolean `$dryRun` parameter. The reason: if one Action could both preview and actually apply, every call site would always have to remember which mode it wants — a real, common source of bugs. Fully separating the two, at the cost of a bit of code duplication, eliminates that entire bug class."

🔁 **Likely follow-ups:**
1. "Where else does this pattern repeat?" → `commerce.checkout.calculate` (Query) vs. `commerce.checkout.process` (Command); `ShippingRateCalculator` (Query) vs. actually creating a Shipment (Command) — the same pattern, every time.
2. "Does that mean a Query never writes to the database?" → Exactly — even when a Query has a heavy computation (like a KPI), its result is just returned; if it also needs to be cached (question 6 of this file), that write is handled deliberately, separate from the Query logic itself.

🚩 **Red flags:**
Suggesting a `$dryRun`/`$commit` parameter on one shared Action instead of two separate Actions — exactly the pattern this project deliberately rejected.

---

### Q3: Why are Report and ReportResult two fully separate Entities?

🎯 **What the interviewer is REALLY testing:**
A concrete example of separating "the definition of a query" from "the result of one specific run of it" — a core CQRS idea.

✅ **Model answer:**
"`Report` is the definition of one report run — the report type, the date range, the filters, which Agent requested it; a fully immutable record with no update method. `ReportResult` is the actual computed number from that specific run, deliberately kept separate so the same `Report` can be re-run without erasing its own history — exactly the same separation of 'definition' from 'result history' that `Workflow`/`WorkflowLog` also have. If these were one single Entity, every time a report ran again, its previous number would be lost forever — a real cost for auditing ('what number did this report show last month?')."

🔁 **Likely follow-ups:**
1. "So does every report run always get saved?" → Yes, it always creates a fresh `Report`+`ReportResult` — exactly why question 8 of this file explains that `CalculateKPIAction` deliberately never calls this same path for ordinary KPI reads.
2. "Where else does this pattern repeat?" → `ExecutionMemoryRepositoryInterface` is similar — an `Execution` (the definition/result of one goal run) with its own complete step history, never overwritten.

🚩 **Red flags:**
Suggesting merging `Report` and `ReportResult` "since they're always used together" — that exactly defeats the whole point of separating them (preserving history).

---

### Q4: Why does Reporting have a separate Generator per report type, instead of one generic Generator?

🎯 **What the interviewer is REALLY testing:**
Understanding that a "read model" doesn't mean one lazy, generic class — it means letting every kind of read be exactly as optimized as it needs to be.

✅ **Model answer:**
"Five separate Generators (`SalesReportGenerator`, `TopProductsReportGenerator`, ...), each sitting on exactly its own matching Query Builder, because every report type needs a genuinely different aggregate shape — 'total sales' is a simple SUM, 'top-selling products' is a GROUP BY+ORDER BY+LIMIT. A generic Generator trying to cover all of these with one unified interface would either have to turn into an abstract, complex query language (which would itself become a second ORM), or fail for the special cases. This project chose to have five simple, pure PHP classes instead, each only combining numbers already pre-aggregated by its own Query Builder (file 02 of this handbook, question 7: 'never re-sum in a PHP loop')."

🔁 **Likely follow-ups:**
1. "Does that mean adding a new report type means a whole new Generator?" → Yes, deliberately — the cost of that repetition is lower than the cost of a wrong abstraction trying to force every aggregate shape into one pattern.
2. "Do Generators have direct database access?" → No — they're completely pure and framework-independent, only combining numbers the Action already gathered through the Query Builder; the same Domain Service/Query Builder distinction file 02 of this handbook showed.

🚩 **Red flags:**
Suggesting one generic Generator with a "report type" parameter — exactly the wrong abstraction this project has deliberately avoided.

---

### Q5: Why hasn't caching for ReportResult been built yet, even though `expires_at` already exists in the schema?

🎯 **What the interviewer is REALLY testing:**
An honest question — does the candidate know a schema-ready column doesn't mean a complete feature.

✅ **Model answer:**
"An honest, documented piece of technical debt: the `expires_at` column already exists on `ReportResult`, but no current code actually checks it — every call to `Generate*ReportAction` always recomputes from scratch and creates a brand-new `ReportResult` row, even if a still-fresh result already exists. This shows exactly the distinction between 'the schema is ready for a feature' and 'the feature is actually implemented' — a ready column, by itself, is no guarantee of behavior."

🔁 **Likely follow-ups:**
1. "How is this different from KPI caching (question 6)?" → Exactly one generation ahead — `CalculateKPIAction` genuinely uses a real caching mechanism (`CacheService`); `ReportResult.expires_at` is only a documented, unimplemented intent, not a real implementation.
2. "Why hasn't this been prioritized yet?" → Because to date, the cost of recomputing these reports (which are explicitly and infrequently requested by an Agent, not loaded on every page view) hasn't grown large enough to justify the work.

🚩 **Red flags:**
Claiming "Reporting caches its own results because it has an `expires_at` column" — exactly the unfounded claim this project always avoids; having a column isn't sufficient grounds for a behavioral claim.

---

### Q6: How is a KPI Value cached? Why only for one hour?

🎯 **What the interviewer is REALLY testing:**
Unlike the previous question (an unfinished feature), this shows a real, complete caching mechanism — a positive example to compare against.

✅ **Model answer:**
"Unlike `ReportResult`, `CalculateKPIAction` genuinely caches — the result is kept in `CacheService` for one hour, and a `KPIValue` row is only persisted on an actual cache miss (never on every call). One hour is a deliberate trade-off: short enough that a KPI (like 'total revenue today') never looks too stale, but long enough that the Admin Dashboard (which reads 6 KPI cards on every page load) doesn't re-run a genuinely heavy aggregate every single time."

🔁 **Likely follow-ups:**
1. "Why isn't this number configurable?" → A real future improvement — today it's a fixed value because no real need has come up for it to differ per KPI.
2. "How is this cache invalidated?" → It isn't explicitly invalidated — it just expires; unlike the product cache (file 04 of this handbook, question 6), which is explicitly invalidated on every edit, a KPI naturally goes stale over time, so a plain expiration is sufficient.

🚩 **Red flags:**
Conflating this mechanism with `ReportResult.expires_at` (question 5) — one is real and complete, the other is a documented, unimplemented intent; this exact distinction is what these two questions, back to back, are testing.

---

### Q7: Why does Analytics call Reporting's own Query Builders directly instead of rebuilding from scratch?

🎯 **What the interviewer is REALLY testing:**
A deeper revisit of this project's single biggest architectural correction (file 02, question 6) from a specifically CQRS angle.

✅ **Model answer:**
"Because this is exactly what a good read model should be: reusable by anyone who needs the same aggregate, not rebuilt from scratch per module. Analytics' original design wanted `RevenueCalculator`/`OrderCalculator` to query Commerce's/Loyalty's tables directly themselves — meaning a second, independent read path for exactly the same numbers ('total revenue') `RevenueQueryBuilder` (Reporting) had already solved. This extends the exact same CQRS exception one layer further: instead of Analytics also connecting directly to Commerce's/Loyalty's read model (direct Conformism, file 08 question 9), it connects to Reporting's *already-built* read model — one read model, reused many times, not rebuilt many times."

🔁 **Likely follow-ups:**
1. "So does Analytics have no logic of its own?" → It does — four Calculators (`RevenueCalculator` and its siblings) were built only for KPIs Reporting genuinely has no equivalent for (like conversion rate); those also only combine already-aggregated numbers, never querying directly themselves.
2. "How was this decision confirmed with the user?" → Explicitly — instead of assuming, this project raised the architectural question before writing any code; the same discipline file 01 of this handbook (question 11) described.

🚩 **Red flags:**
Not knowing this correction, or thinking Analytics and Reporting are two fully independent systems with their own separate numbers — exactly the two-sources-of-truth problem this decision was designed to prevent.

---

### Q8: Why does `CalculateKPIAction` never call Reporting's own `Generate*ReportAction`s, only its Query Builders directly?

🎯 **What the interviewer is REALLY testing:**
A subtler distinction than the previous question — why, even inside the CQRS exception itself, one internal layer was chosen over another.

✅ **Model answer:**
"Because Reporting's `Generate*ReportAction`s have a real side effect — every call persists a fresh `Report`+`ReportResult` (question 3 of this file). That's correct behavior for an Agent explicitly requesting a full report; but if `CalculateKPIAction` called that same path for *every* ordinary KPI read (say, every time the Admin Dashboard opens), it would flood the `reports`/`report_results` tables with thousands of meaningless rows. The solution was calling the Query Builders directly (`SalesQueryBuilder` and its siblings) — one layer below `Generate*ReportAction`, getting the exact same numbers with zero write side effect."

🔁 **Likely follow-ups:**
1. "Does that mean Query Builders get called from two different places?" → Yes — both from `Generate*ReportAction` (which also persists the result) and from `CalculateKPIAction` (which only reads) — the Query Builder itself is completely unaware of which caller invoked it.
2. "Isn't this just another version of the Preview/Apply pattern from question 2?" → Exactly the same pattern, one layer up — a pure read (KPI) vs. a read-with-side-effect (a full report), exactly the same Command/Query separation.

🚩 **Red flags:**
Suggesting "why not just reuse `Generate*ReportAction` to avoid duplicating code" — that's exactly ignoring that path's real side effect.

---

### Q9: What exactly is a Read Model? What does `KPIValue.value_currency` as a unit tag have to do with it?

🎯 **What the interviewer is REALLY testing:**
A subtle example of how a read model can take an unusual shape, since it only needs to be useful for reading, not an abstractly "clean" model.

✅ **Model answer:**
"A read model, unlike a writable Entity, only needs one thing: a fast, useful answer to a specific question — not necessarily a 'clean' Domain structure. `KPIValue`, since its schema (`value_amount`/`value_currency`) is inherently Money-shaped but most KPIs aren't actually money (counts, percentages, points), reuses `value_currency` as a plain unit tag instead of adding new columns — `PCT` for percentage, `CNT` for count, `PTS` for points, `LST` for when the real payload lives in `metadata`. This is exactly what a read model allows: staying practical for its consumer, even when a column's 'real' meaning gets bent a bit — a decision that would never be acceptable on a writable Entity, but makes sense on a read model that only needs to be fast and useful."

🔁 **Likely follow-ups:**
1. "So can a read model bypass normal Domain rules?" → Exactly why CQRS has this value — a read model is free to take whatever shape is optimal purely for *reading*, without having to honor the strict rules of a writable Value Object (file 07 of this handbook).
2. "Did a real bug come from this decision?" → Yes — an early version of `LST` was actually 4 characters (`LIST`), and since `Money::fromAmount()` requires exactly 3 characters, it broke every 'top-selling products' calculation; found and fixed before it ever actually shipped.

🚩 **Red flags:**
Assuming a read model must always follow the same strict rules as a writable Entity — exactly the flexibility CQRS's entire purpose allows for.

---

### Q10: Why hasn't this project built a fully separate read/write database? What's the trade-off?

🎯 **What the interviewer is REALLY testing:**
An explicit Architect-level decision, exactly the same shape as the "not microservices yet" pattern from file 02 of this handbook.

✅ **Model answer:**
"The exact same cost/benefit trade-off file 02 (question 1) gave for a modular monolith vs. microservices applies here identically. A fully separate read database needs a real sync mechanism (replication, or genuine async events updating a read store) — exactly the same complexity file 09 of this handbook (Outbox, eventual consistency) covered, which hasn't been needed yet, because this project hasn't reached the scale where Reporting's heavy reads actually, measurably hurt Commerce's write performance. This project takes CQRS's main benefit (an optimized read model) at the lowest cost (no distributed sync) — exactly a 'only as much as today's real need justifies' version, not a preemptive build for a scale it hasn't reached."

🔁 **Likely follow-ups:**
1. "When should this decision change?" → Exactly when Reporting's/Analytics' reads genuinely and measurably start hurting Commerce's own write performance — a real threshold, not a pre-planned schedule.
2. "Is Redis (file 04 of this handbook) a first step toward this?" → A related but different step — Cache is a temporary layer on top of the same database; a separate read database is a fully independent, always-current data source, not a temporary layer.

🚩 **Red flags:**
"You should have built two databases from the start" — ignoring that this cost has to be justified by a real, measured need, not an abstract "best practice."

---

### Q11: How does ChartDataProvider work? Is this also a CQRS example?

🎯 **What the interviewer is REALLY testing:**
A smaller but complementary example — seeing the same pattern repeat at a different scale (a chart instead of a single number).

✅ **Model answer:**
"Yes, exactly the same pattern. `ChartDataProvider` builds the Admin Dashboard's chart data (like a daily sales trend) — and instead of writing a new daily aggregate query from scratch itself, it calls `SalesQueryBuilder`'s own already-existing `byDay()` method directly, plus one new method (`ordersByDay()`) added to that same Query Builder when an order-count chart was needed — not a new class. This shows a read model (`SalesQueryBuilder`) can *grow* over time with new methods, exactly the same way questions 7/8 showed it gets *reused* — the natural growth of a good read model."

🔁 **Likely follow-ups:**
1. "Why was a new method added to the existing Query Builder instead of a new class?" → Because the query shape (a daily aggregate) was exactly the same, only the aggregate function differed (COUNT instead of SUM) — keeping it in the same class keeps related logic together.
2. "Does ChartDataProvider see the database directly?" → No — it just reshapes the already-aggregated numbers returned by the Query Builder into the `{labels, data}` shape Chart.js understands; the same 'Query Builder aggregates, consumer only shapes' layering repeats.

🚩 **Red flags:**
Not connecting this question to Reporting's larger CQRS exception — this is a smaller instance of exactly the same pattern, not a fully separate mechanism.

---

### Q12: If you genuinely had to move this project to full CQRS (a separate database), where would you start?

🎯 **What the interviewer is REALLY testing:**
An Architect-level answer pulling this file's own questions together into a real plan — exactly the same shape as file 02 (question 7), but for CQRS instead of microservices.

✅ **Model answer:**
"First, the Reporting module — since it already works entirely through its own separate, independent Query Builders (file 02, question 6), the code boundary is already drawn; those same Query Builders would just need to point at a Read Replica instead of the writable database — the smallest possible change for the biggest real benefit (since Reporting carries this platform's heaviest aggregates). Then, exactly the same risks from file 09 (question 12) enter the picture: a Read Replica is usually updated with replication lag — meaning today's immediate consistency, for the first time, genuinely becomes eventual consistency, and that has to be explicitly surfaced to the Admin Dashboard user (e.g. 'this number may lag the latest order by a few seconds'). Analytics, since it's already wired to Reporting instead of directly to Commerce (question 7), would automatically get this same benefit with zero extra change."

🔁 **Likely follow-ups:**
1. "Would Commerce need to change too?" → Not in the first step — Commerce would keep working against the primary writable database; this is a gradual, low-risk migration, exactly what file 02 (question 7) recommended for microservices too.
2. "How would you make sure this change doesn't break anything?" → The exact same discipline from file 06 of this handbook (question 10) — the entire existing Reporting/Analytics test suite has to stay fully green with zero lines changed; only the database connection source changes, never observable behavior.

🚩 **Red flags:**
"I'd just split the entire database at once" — ignoring that the natural boundary for this change (Reporting/Analytics) is already drawn by existing architectural decisions; starting from the wrong place adds needless risk.

---

← [Event-Driven & Messaging](09-event-driven-messaging.md) | Next: [Multi-Tenancy](11-multi-tenancy.md) →
