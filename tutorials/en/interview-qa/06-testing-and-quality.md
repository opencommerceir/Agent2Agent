← [API Design](05-api-design.md) | Next: [DDD Tactical](07-ddd-tactical.md) →

# 06. Testing & Quality

1,156 tests is just a number; this file shows the real discipline behind that number — and where this project honestly admits something still isn't measured.

---

### Q1: Unit tests vs. feature tests — when do you choose which?

🎯 **What the interviewer is REALLY testing:**
A real decision rule, not just definitions of the two test types.

✅ **Model answer:**
"The rule comes directly from the architectural layering (file 02 of this handbook): if the class under test lives in the `Domain` layer (fully framework-independent), a pure unit test is sufficient and more correct — like `PricingServiceTest`, which never boots Laravel at all. If the logic genuinely spans several layers (Controller → Action → Repository → database), or tests behavior that only makes sense once Laravel is actually booted (like the `Event`/`Log` facade, or `config()`), a feature test is needed. One subtle, real detail: some classes that look like they should be unit tests are actually feature tests in practice — `PlanExecutor` dispatches real Domain events through the `Event` facade, and `CapabilityToolInvoker` needs the real permission system; even though these test just one class each (not a full end-to-end scenario), they're feature tests because they need real booted Laravel infrastructure, not because they're testing a complete flow."

🔁 **Likely follow-ups:**
1. "So does a feature test always mean end-to-end?" → No — that exact example above shows otherwise; a feature test can test just one class, purely because that class needs booted infrastructure.
2. "Which runs faster?" → Unit tests are noticeably faster, since no container/database boots at all — one more practical reason to keep the Domain layer fully independent.

🚩 **Red flags:**
"I always use Feature tests because they're more realistic" — ignoring the real speed cost and the value of isolated unit tests.

---

### Q2: How do you test a DDD Aggregate (like Order) without touching the database at all?

🎯 **What the interviewer is REALLY testing:**
Practical understanding of why Clean Architecture (file 02) genuinely makes testing easier, not just an architectural slogan.

✅ **Model answer:**
"Because `Order` (and every other Entity) is completely framework-independent, you can build one directly with `new Order(...)` and test its behavior — e.g. call `$order->cancel()` on an order in `Pending` status and assert it moved to `Cancelled`, or call it on a `Shipped` order and assert it throws `InvalidOrderStateException` — all with zero database involved. A full state-machine test of an Aggregate is written exactly this way: every allowed and every disallowed transition, individually, against plain PHP instances. The only place the database enters is testing the Repository itself (does `toEntity()` reconstruct correctly?) — a completely separate, distinct layer from testing the Entity itself."

🔁 **Likely follow-ups:**
1. "Give me a real example of a complete state-machine test." → `SubscriptionTest` fully covers the entire allowed/disallowed transition matrix for `Subscription` (Trial → Active → PastDue → Cancelled and similar), completely in isolation.
2. "Are Value Objects tested the same way?" → Yes, even more simply — e.g. `SKUTest` just asserts an invalid string throws `InvalidArgumentException` in the constructor, no Entity or database involved at all.

🚩 **Red flags:**
Thinking testing an Entity always needs a database Factory/Seeder — exactly the opposite of tactical DDD's core value.

---

### Q3: How do you simulate an external service (like Stripe) in tests? Why does no test ever actually touch the network?

🎯 **What the interviewer is REALLY testing:**
Understanding test doubles at the HTTP level, not just mocking a plain PHP class.

✅ **Model answer:**
"There are two levels of mocking, for two different purposes. First, a fully fake class (like `MockPaymentGateway`) that implements the whole interface with simple, controllable behavior (`simulate_failure`) — for testing business logic that only cares about 'did it succeed or not.' Second, and more subtle, for the *real* gateways (Zibal/Stripe) that need to be tested themselves: a `MockHandler` is injected on the HTTP library (Guzzle) itself — meaning `StripePaymentGateway`'s real code actually runs (building the request, parsing the response, converting errors), only the HTTP response comes from a pre-written file/array instead of a real server. This distinction matters: the first kind only tests the contract; the second kind also tests the client's real parse/serialize logic, without ever touching real internet."

🔁 **Likely follow-ups:**
1. "So you never do live testing at all?" → Not inside `php artisan test` — but this project has one real, documented example of manual live testing (outside the automated suite); question 8 of this file covers exactly that.
2. "Where else do you see this same pattern?" → The exact same `MockHandler` approach is used for `OpenAIClient`/`ClaudeClient`/`OpenRouterClient` too — a consistent pattern for every external HTTP client in this codebase.

🚩 **Red flags:**
"I set an environment variable so tests don't hit the real server" — this project never relies on that approach; mocking is always injected at the code level, never a fragile environment flag.

---

### Q4: Give me an example of a real regression test written for a specific bug.

🎯 **What the interviewer is REALLY testing:**
A complete story: what the bug was, and how the test specifically closes that exact scenario.

✅ **Model answer:**
"`OrderRepositoryEagerLoadingTest` was built for exactly this. The bug (file 04 of this handbook, question 5) was that `listByTenant()` never eager-loaded the `items` relation, so its cost grew linearly with order count. The regression test is written like this: create one order, count the real queries actually executed; create three more orders, count again — and assert those two numbers are **exactly equal**, not just that 'the result is correct.' The key detail is that the test doesn't check the result (which was always correct) — it checks the **query count**, because the bug itself was a performance bug, not a correctness bug."

🔁 **Likely follow-ups:**
1. "How do you actually count real queries in a test?" → Through `DB::listen()` or a similar Laravel mechanism that records every executed query; the test counts that log after a block of code runs.
2. "Do you have this pattern elsewhere?" → `CheckPermissionTest` has its own, earlier N+1 regression test (for `findRolesForMember()`, from the Tech Debt Sprint) that first established this exact style in this codebase.

🚩 **Red flags:**
Writing a test that only checks "the result is correct" for a performance bug — that will never catch the actual bug (query count) coming back.

---

### Q5: What does this project's CI pipeline look like? What's the actual real code coverage number?

🎯 **What the interviewer is REALLY testing:**
Honesty about a real dev-environment limitation, instead of a fabricated number.

✅ **Model answer:**
"`.github/workflows/tests.yml` runs all 1,156 tests on every push, with `coverage: pcov` (a real PHP extension only CI has installed). The honest answer about the real coverage number: this local dev environment has neither PCOV nor Xdebug — meaning the real coverage number has never actually been measured locally, only CI can produce it. The current CI gate (`--min=60`) is a conservative placeholder, not a real measured number — and instead of hiding this, it's explicitly recorded in `HANDOFF.md`, with a note that the threshold should be raised to the real number after an actual CI run and reading its uploaded report."

🔁 **Likely follow-ups:**
1. "Why not just guess or claim a number?" → Because a fabricated number is worse than having no number at all — the same statistical honesty discussed in file 01 (question 5 of this handbook).
2. "What is PCOV, and why does only CI have it?" → A PHP extension (not an installable Composer package) that tracks executed lines; installing it needs system-level access this specific dev environment doesn't have.

🚩 **Red flags:**
Stating a confident coverage number ("we have 85%") without it ever actually being measured — exactly the kind of unfounded claim this project deliberately avoids.

---

### Q6: How do you test that multi-tenancy actually works correctly?

🎯 **What the interviewer is REALLY testing:**
Is Tenant isolation just an architectural claim, or is it actually proven with tests?

✅ **Model answer:**
"Almost every feature test has an explicit isolation scenario: create two real Tenants, create similar data for each (e.g. a product sharing the same numeric `id`, or an identical name), then call a capability with an Agent token belonging to the first Tenant and assert only the first Tenant's data comes back — never the second Tenant's, even if the Agent guesses the second Tenant's exact record id (an attempt to directly access a record with an `id` belonging to a different Tenant must return `404`, never real data). This is exactly the pattern that caught the real cache-leak bug (file 04, question 3 of this handbook)."

🔁 **Likely follow-ups:**
1. "Is this test written per-capability, or is there one generic test?" → Both — one generic test proves the pattern across several high-traffic capabilities, but every new capability also carries this exact scenario in its own feature test, following the pattern from file 05, question 9 of this handbook.
2. "Is guessing an id a real risk?" → Yes — exactly why tests explicitly check 'attempting access with a real id belonging to a different Tenant,' not just 'an empty list comes back.'

🚩 **Red flags:**
Testing isolation with just one Tenant ("make sure `tenant_id` is correct") — this will never catch a real leak between two Tenants.

---

### Q7: Why are some tests framework-free (pure PHPUnit) and some are feature tests (needing booted Laravel)?

🎯 **What the interviewer is REALLY testing:**
A clear, defensible rule, not an arbitrary choice.

✅ **Model answer:**
"The rule is direct: if the class under test has no dependency on anything genuinely 'booted' (no `Event` facade, no `config()`, no database), it runs framework-free — faster, more isolated. `PlanExecutorTest`/`CapabilityToolInvokerTest` are an interesting exception to this rule: even though each tests just one class (not a complete end-to-end scenario), because `PlanExecutor` genuinely dispatches Domain events through the `Event` facade and `CapabilityToolInvoker` needs the real permission system, these are feature tests — the exact same reason `DeprecationNotifierTest`/`MCPRateLimitTest` are also feature tests: needing a booted container, not being a full multi-step scenario."

🔁 **Likely follow-ups:**
1. "Does that mean feature tests are always slower?" → Measurably slower, yes — but this project prefers real correctness over test speed whenever genuinely booted infrastructure is needed.
2. "How do you spot a class that unexpectedly picked up a booted dependency?" → When a framework-free test suddenly fails with 'Facade root has not been set' — the error itself reveals that hidden dependency.

🚩 **Red flags:**
"I make everything a feature test to be safer" — ignoring the real speed cost and the value of fast, isolated unit tests for immediate feedback.

---

### Q8: Tell me about a real bug that was only found through live testing, not `php artisan test`.

🎯 **What the interviewer is REALLY testing:**
Understanding the real limits of automated testing — things no mock will ever reveal.

✅ **Model answer:**
"A complete, documented example: the first time `OpenRouterClient` was tested with real credentials (not a `MockHandler`), the very first real call failed with `403`. The cause was a real, subtle Guzzle behavior — when `base_uri` has a path (like `https://openrouter.ai/api/v1`) and the request path starts with `/`, per RFC 3986, Guzzle **replaces** the base URI's path instead of appending to it — meaning `/api/v1` was silently dropped. This bug was never visible in the 7 existing unit tests, all of which injected an already-built Guzzle client (exactly the pattern from question 3 of this file) — because none of them ever exercised the real 'automatically build `base_uri`' constructor branch. The fix was switching to the standard Guzzle convention (`base_uri` ending with `/`, request path never starting with one), plus a new regression test that re-exercises that exact branch through reflection, with no real network needed."

🔁 **Likely follow-ups:**
1. "Why wasn't this bug caught earlier?" → Because until that moment, not a single test had ever run that client against live credentials — a real, honestly documented gap between 'tested against a mock' and 'tested against reality.'
2. "Did similar classes have the same bug?" → Yes — the exact same fix was applied preemptively to the new real payment gateways (Zibal/Stripe) from day one, without needing to be rediscovered.

🚩 **Red flags:**
Claiming "full mocking means we never need live testing" — this exact bug is direct proof of the opposite.

---

### Q9: How are N+1/query-count tests written? Why isn't checking the result alone enough?

🎯 **What the interviewer is REALLY testing:**
A specific performance-testing pattern, not just correctness testing.

✅ **Model answer:**
"The pattern is fixed, exactly as seen in question 4 of this file: a real (not simulated) query counter is engaged before and after an operation, and the assertion is that the query count stays **flat** as data grows (e.g. from 1 order to 4 orders), not linear. This is fundamentally different from an ordinary 'the result is correct' test, because an incomplete eager-load can still return a perfectly correct result — just at several times the query cost — and an ordinary test will never see that at all."

🔁 **Likely follow-ups:**
1. "How brittle are these tests?" → A real risk — if a future architectural change deliberately adds a meaningful extra query, this test needs to be deliberately updated; these tests are a regression guard, not an immutable law.
2. "Why hasn't this pattern been applied to every Repository?" → The exact same honest limitation from file 04, question 6 of this handbook — a full audit found and fixed 4 real cases; this tool (`performance:check-lazy-loading`) runs today as an ongoing guard, not as proof that every Repository was manually checked once and for all.

🚩 **Red flags:**
Assuming "if the test is green, performance is guaranteed too" — an ordinary test never sees the query cost, only the result.

---

### Q10: How do you make sure a big refactor (like extracting `FinalizeSuccessfulPaymentAction`) didn't break anything?

🎯 **What the interviewer is REALLY testing:**
Trusting a refactor through tests, not through manually re-reading the code.

✅ **Model answer:**
"When the shared 'a payment succeeded' logic was extracted out of `ProcessPaymentAction`'s own previously-inline tail into a brand-new Action (`FinalizeSuccessfulPaymentAction`), so both the old synchronous path and the new redirect-based path could reuse it, the success criterion was: **the complete pre-existing test suite (`ProcessPaymentTest`/`CheckoutCapabilityTest`/`RefundPaymentTest`/`PaymentTest`), with zero lines changed, had to stay fully green.** This wasn't just a claim — it was a real, actually-executed confirmation — because these tests check *observable behavior*, not internal implementation, them staying green unmodified is exactly the proof that the refactor genuinely changed structure only, never behavior."

🔁 **Likely follow-ups:**
1. "What if one of the old tests had broken?" → It would mean the refactor changed behavior too, not just structure — an immediate signal to stop and reconsider, not just to edit the test to make it pass again.
2. "Did you write new tests for the extracted Action itself too?" → Yes — beyond proving 'nothing broke,' `FinalizeSuccessfulPaymentAction` got its own independent tests too, since it's now a real, independently callable unit reused from two different paths.

🚩 **Red flags:**
"I edited the tests a bit after the refactor to make them pass" — if behavioral (not implementation) tests need editing after a pure refactor, that's a warning sign, not a normal step.

---

### Q11: How are this project's official SDKs tested? Why are they independent from `php artisan test`?

🎯 **What the interviewer is REALLY testing:**
Understanding that an SDK must be fully provable completely outside the main application.

✅ **Model answer:**
"All five SDKs (PHP, Laravel, Python, Node.js/TypeScript, Go) have their own completely independent test suites, each using their own language's standard tooling (`phpunit`, `python -m unittest`, `node --test`, `go test`) — not part of `php artisan test`. The reasoning is simple: an SDK has to work completely outside this repository, inside a different developer's own project; if its tests depended on Laravel being booted, that claim would be false on its face. All five SDKs also carry the same 'no test ever touches the network' discipline (question 3) — an injectable `Transport` swappable with a fake, exactly the same `MockHandler` pattern seen for Guzzle, just repeated in four other languages."

🔁 **Likely follow-ups:**
1. "Is the Laravel SDK tested the same way?" → Not exactly — since its whole purpose is connecting to a real Laravel application, it's tested with Orchestra Testbench (a real Laravel container, but no network) — the first package under `packages/` that genuinely needed Laravel booted to test itself.
2. "How were the 71 new multi-language SDK tests actually confirmed?" → By actually running each language's own test tool in the same session, not just writing them and assuming they'd pass — the same 'never claim without actually running it' discipline.

🚩 **Red flags:**
Assuming every test in the project, SDKs included, runs through one single command — this project deliberately keeps them separate.

---

### Q12: How is webhook testing (like for Stripe) done? How do you verify the HMAC signature in a test?

🎯 **What the interviewer is REALLY testing:**
A precise example of testing a real security mechanism, not just an ordinary HTTP route.

✅ **Model answer:**
"`PaymentCallbackRouteTest` generates a **real, self-signed** Stripe signature right inside the test itself — using the exact same real formula Stripe uses (`hash_hmac('sha256', "{timestamp}.{raw_body}", $secret)`), the same formula `StripeWebhookVerifier` uses, just with a test secret instead of a real one. This means the test actually exercises the real signature-validation logic itself — not just 'does the webhook route return 200' — a request with a correct signature must be accepted, a request with a tampered signature must be rejected with `400`. A subtler scenario is covered too: Stripe sometimes sends several `v1` signature values at once (during a key-rotation window); the test proves matching **any one** of them is sufficient, not just the first."

🔁 **Likely follow-ups:**
1. "Why not use a real Stripe key in the test?" → Because no test should ever depend on a real production secret; a test secret fully exercises the exact same mathematical formula without exposing anything real.
2. "Is webhook idempotency tested here too?" → Yes — the same file covers the 'the same webhook arrives twice' scenario, asserting the order/payment is only ever really processed once (question 8, file 05 of this handbook).

🚩 **Red flags:**
Testing a webhook route only by checking "does it return 200" without testing the signature-validation logic itself — that's exactly the gap that would hide a real security vulnerability.

---

← [API Design](05-api-design.md) | Next: [DDD Tactical](07-ddd-tactical.md) →
