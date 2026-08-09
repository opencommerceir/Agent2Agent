← [Database & Performance](04-database-and-performance.md) | Next: [Testing & Quality](06-testing-and-quality.md) →

# 05. API Design

This file focuses on API design decisions — REST conventions, versioning, validation, error handling — where a lot of developers have only memorized "general REST rules" without knowing when it's right to deliberately step outside them.

---

### Q1: Why isn't this API fully RESTful? Just one single endpoint to execute everything? Isn't that against REST best practices?

🎯 **What the interviewer is REALLY testing:**
Do you treat REST as a sacred rule, or do you understand REST is a tool for a specific audience, not a goal in itself?

✅ **Model answer:**
"It's deliberately not fully RESTful, and that's a conscious decision, not a limitation. Classic REST assumes the consumer already knows what resources exist and each one's address (`GET /products/5`) — that makes sense for a human developer who's read the docs. This API's primary audience is an AI agent that needs to **dynamically** discover capabilities, without any pre-known, fixed map of addresses. `GET /mcp/v1/capabilities` handles that discovery; `POST /mcp/v1/execute` handles execution — one single address for all 127 capabilities, because the number and names of capabilities grow over time, and an agent needs to be able to follow that growth without any code change of its own. That means we haven't rejected REST — we've layered something else on top of it that works better for this specific audience."

🔁 **Likely follow-ups:**
1. "So is there no real REST anywhere in this project?" → The human Admin Dashboard is exactly the opposite — since its audience is a human with a browser, it uses ordinary, REST-ish Laravel routes; the API style is always chosen based on the audience.
2. "Wouldn't GraphQL have been better?" → An honest, balanced answer: GraphQL solves the 'dynamic discovery' problem through a different mechanism (introspection), but MCP is a specific, emerging industry standard for agent-oriented communication that this platform deliberately followed, not a proprietary invention.

🚩 **Red flags:**
"REST is always the best choice" with no consideration of who the API's actual audience is.

---

### Q2: How is API versioning (v1/v2) designed? Why does the URL always have to win over a header?

🎯 **What the interviewer is REALLY testing:**
A real example of how an API design decision got discovered and corrected from a contradiction inside the request's own spec.

✅ **Model answer:**
"`/mcp/v1/*` and `/mcp/v2/*` exist simultaneously and fully independently — same capabilities, same permissions, only the response envelope differs (`data`/`meta` vs. `result`/`metadata`). Version detection is three-tiered: URL address, then the `Accept` header, then the query string. The interesting part: the original spec for this feature had an internal contradiction — the stated priority was 'URL > Header > Query,' but that same spec's own example test expected an `Accept` header to override an already-explicit v1 URL. If we'd implemented it exactly as written, a v1 integration could suddenly change its response shape with zero code changes on its own end, purely because some network intermediary (like a proxy) happened to attach a default `Accept` header — exactly the kind of hidden, surprising behavior a trustworthy API should never have. The final, confirmed (not assumed) decision: an explicit URL version always wins, no exceptions."

🔁 **Likely follow-ups:**
1. "So is header/query detection actually used at all?" → Fully implemented and tested, but it never actually engages today, because every real route already has an explicit version segment in its own address.
2. "How do you make sure v1 and v2 return exactly the same underlying data?" → A direct test proves it — it calls the same capability through both v1 and v2 and asserts `v1.data === v2.result`.

🚩 **Red flags:**
Not knowing this was a real spec contradiction that was caught — accepting a spec with a contradictory example test without questioning it is exactly what a senior engineer should catch.

---

### Q3: How does input validation work? What exactly is an Input Schema?

🎯 **What the interviewer is REALLY testing:**
A concrete example of a centralized validation layer, instead of validation scattered across every Controller.

✅ **Model answer:**
"Every capability has an `inputSchema` — a simple array mapping field name to type (e.g. `['query' => 'string', 'limit' => 'integer']`), registered alongside the capability's own definition in each module's `*Capabilities.php`. `MCPRequestValidationService` checks the raw JSON input against that same schema before the real handler is ever called — a missing or wrong-typed field gets rejected right there with `422 VALIDATION_ERROR`, before a single line of business logic runs. One real, documented limitation of this system: today it only understands 'required field with a type,' it has no concept of 'optional but typed' — that's exactly why genuinely optional fields (like `notes` on `commerce.order.place`) are deliberately left out of `inputSchema` entirely and read defensively (`$input['notes'] ?? null`) instead of being incorrectly marked required."

🔁 **Likely follow-ups:**
1. "Why didn't you fix that limitation?" → An honest, documented piece of technical debt — fixing it needs a richer schema language (something closer to real JSON Schema), and so far its value hasn't outweighed that complexity.
2. "Is output validated too?" → `outputSchema` is documented (for discovery/documentation purposes) but not automatically enforced today — a deliberate distinction between 'what we promise' and 'what we actually enforce.'

🚩 **Red flags:**
Saying "everything is validated with Laravel Form Requests" — this project deliberately has an independent, centralized validation layer for MCP, separate from the Admin Dashboard's own Form Requests.

---

### Q4: How does error handling work? How does an exception get translated into the right HTTP code?

🎯 **What the interviewer is REALLY testing:**
Understanding a centralized error-handling layer instead of scattered try/catch blocks in every Controller.

✅ **Model answer:**
"`MCPExceptionHandler` is the one and only place that formats every MCP error. Instead of every Controller needing to know which exception maps to which HTTP code, this class checks two marker interfaces: `NotFoundExceptionInterface` (→ `404`) and a second one for conflicts/duplicates (→ `409`) — any domain exception implementing either one gets mapped correctly and automatically, without `MCPExceptionHandler` ever knowing that exception class's name. An exception implementing neither (like `WooCommerceApiException`) falls through to `500 INTERNAL_ERROR` — a deliberate decision, since a genuine external service failure isn't, semantically, something a client should treat like an ordinary 4xx error."

🔁 **Likely follow-ups:**
1. "Why two separate marker interfaces instead of an enum or a numeric code?" → Because an interface lets an exception get the right behavior just by `implements`-ing it, with zero changes to `MCPExceptionHandler` itself — open for extension (the Open/Closed principle, file 02 of this handbook).
2. "Tell me about a real bug this mechanism caught." → A nonexistent route (like an invalid `agentType` in `/api/agents/{agentType}`) used to be incorrectly flattened to `500` instead of its own real `404` — because `MCPExceptionHandler`, before a fix, never checked for `HttpExceptionInterface` (Symfony's own routing-level exception) at all; this bug was found and fixed while adding the Agent Orchestrator module.

🚩 **Red flags:**
Suggesting a separate try/catch in every Controller for error mapping — exactly what this centralized layer exists to prevent.

---

### Q5: Why does an exception deliberately implement neither marker interface? Give me a real example.

🎯 **What the interviewer is REALLY testing:**
Understanding that a "default 500" is sometimes itself the right choice, not a gap in the implementation.

✅ **Model answer:**
"`RateLimitExceededException` deliberately implements neither marker interface. The reason: this error is neither 'something wasn't found' nor 'a data conflict' — it's a genuinely separate condition that deserves its own HTTP code (`429`), and `MCPExceptionHandler` has a dedicated `match` arm for exactly this one exception. The same pattern repeats for `WooCommerceApiException` and `ShippingProviderException` — both represent a real external-service failure whose meaning differs from a 404/409 the client 'should' react to by changing something; here the client just needs to know 'this didn't work right now,' not 'your request was wrong.'"

🔁 **Likely follow-ups:**
1. "How do you decide which category a new exception belongs to?" → A simple question: does the client need to change its own *behavior* (a real 4xx), or does it just need to know 'you didn't get an answer this time, maybe try again later' (a 5xx or a dedicated code)?
2. "Where are these decisions documented?" → Directly in the exception class's own docblock — "implements neither marker interface, same reasoning WooCommerceApiException gives" is a genuinely recurring phrase across this codebase.

🚩 **Red flags:**
Assuming "every exception must map to some specific 4xx code" — some errors genuinely should stay 5xx.

---

### Q6: What's the capability naming convention? Why must it be exactly 3 dot-separated segments?

🎯 **What the interviewer is REALLY testing:**
A small but heavily repeated rule — this question checks whether you're actually familiar with this project's real build process.

✅ **Model answer:**
"Every capability name must be exactly three dot-separated segments: `domain.resource.verb` — like `commerce.product.search`. This is enforced by `CapabilityName` (a Value Object). What's interesting is that almost **every single stage** of this project hit this rule at least once — a natural-sounding request wanted to write `commerce.warehouse.transfer.request` (4 segments), which got rejected and renamed to `commerce.transfer.request` (promoting 'Transfer' into its own resource instead of a sub-item of 'Warehouse'). This repeated so often it's now a formal, numbered pattern in `HANDOFF.md` (Pattern #13) — the standard fix is always one of two: drop a generic verb like 'find'/'get,' or promote one of the nouns into its own top-level resource."

🔁 **Likely follow-ups:**
1. "Why even have this 3-segment restriction?" → A fixed, predictable convention for an agent that reads/discovers these names matters more than arbitrary naming flexibility.
2. "Give me another example of this correction." → `commerce.bulk.import.products` (4 segments) became `commerce.bulk.import_products` — merging verb+resource into one underscored segment instead of dropping a word.

🚩 **Red flags:**
Not knowing this rule, or being unable to give a real example of a rename caused by it — this is one of the most frequently repeated real details in this codebase.

---

### Q7: Why are optional fields deliberately left out of `inputSchema` instead of being marked nullable?

🎯 **What the interviewer is REALLY testing:**
Understanding a real system limitation and how it's handled through a simple, consistent convention instead of a workaround.

✅ **Model answer:**
"Because `MCPRequestValidationService` today has no concept of 'optional' — it treats every key in `inputSchema` as required. If an optional field (like `notes` when placing an order) stayed in the schema, any call that omitted it would incorrectly get rejected with `422`. The fix wasn't a change to the validation engine itself (which would add more complexity) — it's a simple, documented convention: a genuinely optional field is left out of `inputSchema` entirely and read in the Action's own code with `$input['field'] ?? default`. This is a deliberate trade-off — the auto-generated documentation (`docs/api-reference.md`) won't show these optional fields, but runtime behavior is always correct."

🔁 **Likely follow-ups:**
1. "So a client can't discover an optional field exists?" → Exactly that documented limitation — the only way is reading the docs manually or the code itself; a real, documented future improvement in `HANDOFF.md`.
2. "Why not extend the validation engine instead?" → Because to date, this limitation has never actually blocked a real capability — it only ever needed a simple convention, not a rewrite.

🚩 **Red flags:**
Suggesting "just mark it nullable" without knowing the validation engine doesn't even understand that concept at all.

---

### Q8: How is idempotency handled at the API level?

🎯 **What the interviewer is REALLY testing:**
Real understanding of a genuine reliable-API design principle, beyond a dry definition (a fuller, payments-specific idempotency question lives in file 13 of this handbook; this is the general API-level angle).

✅ **Model answer:**
"The best example is `ConfirmRedirectPaymentAction` — because both the buyer's browser can return and the payment gateway's webhook can arrive independently, this Action may genuinely get called more than once for the exact same `PaymentSession`. Instead of reprocessing (meaning a second order, a second stock decrement), the very first thing this Action does is check the Session's current status — if it's already `Completed`, the same earlier result is returned again with zero new writes. This is idempotency in the literal sense: the result of the second call is exactly the result of the first, not just 'it doesn't error out.'"

🔁 **Likely follow-ups:**
1. "Is every write capability idempotent?" → No, honestly — this pattern is only added deliberately where a real repeat scenario exists (like a webhook arriving twice); calling `commerce.coupon.create` again genuinely creates a second, real coupon.
2. "Why not use a real idempotency key (like Stripe's own standard)?" → Because the source of idempotency in this specific case — the `tracking_reference` (the PaymentSession's own id) — already existed; adding a separate key would have duplicated something already available.

🚩 **Red flags:**
Thinking idempotency just means "don't return the same error twice" — real idempotency means the same result, not just the absence of failure.

---

### Q9: How do you add a brand-new MCP capability from scratch? Walk me through it step by step.

🎯 **What the interviewer is REALLY testing:**
A practical, complete answer, not an abstract description of the architecture.

✅ **Model answer:**
"Say we're adding `commerce.product.archive`. First, an Action in `Application/Actions` that orchestrates the real logic (reading the product from its Repository, calling a Domain method like `Product::archive()`, saving). Second, a new entry in `CommerceCapabilities::definitions()` — a name (which must follow the 3-segment convention, question 6), a description, `inputSchema`, `outputSchema`, and `requiredPermissions`. Third, a handler closure in `CommerceServiceProvider::boot()` connecting that name to the same Action, through `CapabilityHandlerRegistry`. Fourth, if a new permission is needed, it has to be added to the relevant seeder so it's actually grantable. The last step is a complete Feature test covering the real, full MCP path (not just the Action in isolation), including a permission check and multi-tenant isolation."

🔁 **Likely follow-ups:**
1. "Why no migration needed if no new column is required?" → Exactly — these steps only apply to a capability using existing data; if a new Entity/table is needed, the full seven-step pattern (file 02, question 9 of this handbook) runs instead.
2. "What exactly is that multi-tenant isolation test?" → A test directly proving an Agent from a different Tenant sees this capability act on its own data, not the calling Tenant's — a repeated test pattern in nearly every Feature test file in this project.

🚩 **Red flags:**
Forgetting the permission-seeding step — a real, common mistake that leaves a capability existing but with no Agent actually able to call it.

---

### Q10: What's the difference between an input DTO and an output DTO? Do you share one class between them?

🎯 **What the interviewer is REALLY testing:**
Understanding why even a seemingly small difference (input vs. output) shouldn't share a class.

✅ **Model answer:**
"No, deliberately separate. Raw input enters an Action through a plain `$input` array (which the Action itself pulls the needed values out of, usually with no dedicated input DTO class); output always comes back through a specific `*Data` class (like `ProductData`). The reason not to share: an input DTO would always need to be optional/partial (since a client might only send some fields), while an output DTO should always be complete (since the client expects a definitive shape). Combining the two into one class means either everything on input has to become nullable (giving you no real validation), or output is forced to carry meaningless optional fields."

🔁 **Likely follow-ups:**
1. "So input is never type-safe?" → Right after passing through `MCPRequestValidationService` (question 3), every field's type is guaranteed; only its PHP structure is a plain array, not a class.
2. "Why does output have a DTO but input doesn't?" → Because output is a long-lived, stable contract with external clients (it needs stability); input gets freshly validated every single time, so it doesn't need an extra typed layer.

🚩 **Red flags:**
Suggesting "let's build one shared DTO for both, it's cleaner" — it shows a lack of understanding of how genuinely different the input and output needs actually are.

---

### Q11: How do you make sure a change to a capability never breaks existing clients?

🎯 **What the interviewer is REALLY testing:**
Real understanding of backward compatibility at the API level, not just repeating "always stay compatible."

✅ **Model answer:**
"This project's fixed pattern: any new field on an existing input/output is always added **optional and trailing**, never required and never inserted in the middle. This pattern repeats so often it's a formal, documented pattern in `HANDOFF.md` (Pattern #6). A real example: when product variants were added, `AddToCartAction::execute()` gained a new optional `?int $variantId = null` parameter — every existing call that never passed it kept behaving exactly the same, confirmed by running the complete, pre-existing test suite unmodified, with zero changes to a single one of those tests. Those old tests themselves are the living proof that nothing broke."

🔁 **Likely follow-ups:**
1. "What if you genuinely need a breaking change?" → Exactly where the versioning system (question 2) comes in — a real breaking change becomes a new API version, not a silent edit on an existing one.
2. "Why does a new field have to be trailing specifically?" → Because PHP has positional arguments; inserting a parameter in the middle of the list shifts the meaning of every later parameter for any existing positional call, even if the new one is optional.

🚩 **Red flags:**
"I'll just update the docs so clients know" — that's not a real compatibility strategy; the code itself must guarantee it, not hope clients re-read the documentation.

---

### Q12: What's the difference between a 429 (rate limit) and a 422 (validation) error from an API design perspective? Which one is retry-able?

🎯 **What the interviewer is REALLY testing:**
Understanding that not all 4xx codes mean the same thing — some tell a client "try again," others tell it "never retry until something changes."

✅ **Model answer:**
"`422 VALIDATION_ERROR` says 'this exact request will always be rejected until the input itself changes' — retrying without changing the input always produces the same result; there's no real retry logic that makes sense here. `429 TOO_MANY_REQUESTS` is the exact opposite — it says 'this same request will probably succeed if you wait a bit' — this is exactly the code a well-behaved client (or a smart agent) should retry with exponential backoff. `EnforceRateLimitAction` implements this distinction using Laravel's own standard `RateLimiter`, scoped per Agent (not the whole system) — meaning one high-traffic Agent never affects any other Agent."

🔁 **Likely follow-ups:**
1. "Is a 403 (unauthorized) retry-able?" → No — exactly like 422, retrying is pointless until the Agent's real permissions actually change (an operation outside this request entirely).
2. "Do the official SDKs surface this distinction?" → Yes — `ValidationException`, `AuthorizationException`, and their siblings are all separate classes, precisely so a client can programmatically decide which ones are actually worth retrying.

🚩 **Red flags:**
Suggesting a generic retry policy ("just retry every error 3 times") with no distinction between retry-able and non-retry-able errors — that can pointlessly triple an invalid request.

---

← [Database & Performance](04-database-and-performance.md) | Next: [Testing & Quality](06-testing-and-quality.md) →
