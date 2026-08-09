← [Overall Architecture](02-overall-architecture.md) | Next: [Database & Performance](04-database-and-performance.md) →

# 03. Laravel & Design Patterns

If the [pre-tutorial](../pre-tutorial/05-common-design-patterns.md) covered what each pattern *is*, this file goes one layer deeper: what these patterns actually look like in this project's real code — and, more importantly, where a subtle Laravel-specific choice (like `bind()` vs. `singleton()`) caused a real bug or drove a deliberate decision.

---

### Q1: Walk me through the Service Container and Dependency Injection in this project — give me a real example of an interface with multiple bindings.

🎯 **What the interviewer is REALLY testing:**
Practical understanding of the container, not just the textbook definition "DI means injecting dependencies."

✅ **Model answer:**
"The best real example is `LLMClientInterface` in the Agent Orchestrator module. It has three real implementations — `OpenAIClient`, `ClaudeClient`, `OpenRouterClient` — and `AgentOrchestratorServiceProvider::register()` decides which one gets injected based on `config('agent-orchestrator.llm.provider')`:
```php
$this->app->bind(LLMClientInterface::class, function ($app) {
    return match (config('agent-orchestrator.llm.provider')) {
        'openai' => new OpenAIClient(...),
        'claude' => new ClaudeClient(...),
        'openrouter' => new OpenRouterClient(...),
        default => throw new InvalidArgumentException(...),
    };
});
```
Any class that requests `LLMClientInterface` in its constructor (like `LLMPlanner`) gets it without ever knowing which real provider is actually behind it. This is exactly Dependency Inversion at the container level."

🔁 **Likely follow-ups:**
1. "What changes if you add a new provider, like a local model?" → Just a new `case` in that same `match` block; no consuming class changes at all.
2. "What happens if the interface isn't bound?" → Laravel tries to instantiate the interface itself, which fails since it's an interface — a loud, clear development-time error, not a silent bug.

🚩 **Red flags:**
Defining the container only as "where Laravel builds your classes" with no real example of why this pattern matters in this project.

---

### Q2: What's the difference between `bind()` and `singleton()`? Where in this project is `bind()` deliberately used instead of `singleton()`, and why does it matter?

🎯 **What the interviewer is REALLY testing:**
This is a subtle, real senior-level question — a lot of people know the surface-level difference but have never actually hit the practical consequence in testing.

✅ **Model answer:**
"`singleton()` means the first time an interface is requested, one instance gets built and that same instance is returned forever (for the lifetime of one request). `bind()` means every time the interface is requested, the closure runs again — a fresh instance, reading the latest `config()` value. In `AgentOrchestratorServiceProvider`, `PlannerInterface`, `ReasoningEngineInterface`, and `LLMClientInterface` are all deliberately registered with `bind()`, not `singleton()` — because tests need to flip `config(['agent-orchestrator.planner.type' => 'llm'])` mid-run and immediately see the different implementation resolve. If these were `singleton()`, whichever one resolved first would win permanently, and any later config change would have zero effect — exactly the same real gotcha `ConnectorRegistry` has (question 6 of this file)."

🔁 **Likely follow-ups:**
1. "So why not just make everything a singleton for speed?" → Because the speed difference at this scale (once per request) is unmeasurable, but the cost to testability is very real.
2. "Does this distinction matter in production?" → Less so, since config doesn't usually change mid-request in real production traffic; but that same flexibility is exactly what makes the Showcase demo's own 'Use real AI' toggle possible (flipping config mid-request for a real HTTP call, not just in a test).

🚩 **Red flags:**
Not mentioning this was a *deliberate* decision — saying "it doesn't matter which one you use" signals the person has never actually run into this gotcha.

---

### Q3: How is the Repository pattern actually implemented in this project? Walk me through a complete example.

🎯 **What the interviewer is REALLY testing:**
Do you just know the pattern's name, or have you actually seen the full Interface → Eloquent Model → `toEntity()` shape?

✅ **Model answer:**
"Three fixed pieces: an interface in `Domain/Repositories` (e.g. `ProductRepositoryInterface`, with methods like `findById()`, `save()`), an Eloquent Model in `Infrastructure/Models` that only represents the table structure, and an implementation in `Infrastructure/Repositories` (`EloquentProductRepository`) that bridges the two worlds. The key detail is the `toEntity()` method — every Repository has one, converting a raw Eloquent row directly into a pure Domain Entity **without** going through the Entity's own public state-transition methods (like `cancel()`); because the Entity is being 'reconstructed' from the database, not 'freshly created.' This distinction matters — a brand-new Entity must pass through every constructor validation rule, but an Entity reconstructed from an already-valid database row doesn't need to run through those same rules again."

🔁 **Likely follow-ups:**
1. "Why are Model and Entity two separate classes instead of one?" → Because the Model needs to know about Eloquent (framework-dependent), and the Entity shouldn't — this is exactly the Clean Architecture boundary.
2. "When does the Repository write vs. read?" → Both live on the same interface — `save()` is used for both create and update (an upsert), a decision the Repository itself makes based on whether an `id` already exists.

🚩 **Red flags:**
Thinking of a Repository as "just a wrapper around Eloquent" with no real conversion into an independent Entity.

---

### Q4: Where is the Factory pattern actually used in this project?

🎯 **What the interviewer is REALLY testing:**
A real example, not a generic "Factory means a class that builds objects" definition.

✅ **Model answer:**
"Two real, distinct examples. First, `Inventory::stock()` — a static method that builds a fresh `Inventory` instance with zero stock, instead of every Action calling the class constructor directly with default values. Second, `MCPConfig::forVersion()` — a lighter Factory that builds `baseUrl` from `host` + `version` (`{host}/mcp/{version}`), so migrating from v1 to v2 is a one-argument change, not manually editing a URL string. Both examples share one real Factory trait: the construction logic is non-trivial enough (either several default parameters, or a composition rule) that repeating it at every call site risks inconsistency."

🔁 **Likely follow-ups:**
1. "Why static factory methods instead of a separate Factory class?" → Because the logic is simple enough that a standalone class would be overkill — a general rule in this project: architectural complexity should match the real complexity of the problem.
2. "Where could a Factory have been used but wasn't?" → An honest answer: a lot of simple Entities (like `Category`) just use their own plain constructor, since there's no non-trivial construction logic to justify a separate Factory.

🚩 **Red flags:**
Naming a generic PHP pattern (like the GoF Factory Method) without being able to point to a real method in this actual codebase.

---

### Q5: The Strategy/Connector pattern — where, and how many times, does it repeat?

🎯 **What the interviewer is REALLY testing:**
Have you only ever seen a pattern once, or do you understand why a good pattern gets deliberately repeated in a real codebase?

✅ **Model answer:**
"The Connector pattern — one shared interface plus several swappable implementations — repeats **exactly four times** across this platform, each time for a completely different need: connecting to external stores (`ProductConnectorInterface`, real implementation `WooCommerceProductConnector`), shipping providers (`ShippingProviderInterface`), notification channels (`ChannelSenderInterface` — email/SMS/webhook/in-app), and real payment gateways (`RedirectPaymentGatewayInterface` — Zibal/Stripe). All four have exactly the same three pieces: an interface, a Registry to pick an implementation by name (next question), and at least one fake implementation for testing without real network calls. This repetition shows this isn't a one-off pattern solved once — it's a proven template that gets reused every time the same real question, 'how do we talk to a swappable external system,' comes up."

🔁 **Likely follow-ups:**
1. "Why not redesign the pattern from scratch each time?" → Exactly the opposite — every time, the previous pattern was copied and adapted, because it was already proven to work; that's itself a sign of good architecture.
2. "Which implementation of this pattern only has a Mock, no real implementation?" → Real shipping carriers (USPS/FedEx/DHL) — because no real credentials existed to test against honestly, and that's documented as a real future goal, not a false claim.

🚩 **Red flags:**
Only knowing one instance of this pattern (usually WooCommerce) and not being able to say it repeats three more times — it signals shallow, not deep, familiarity with the codebase.

---

### Q6: How does the Registry pattern differ from Strategy, and why was it needed separately?

🎯 **What the interviewer is REALLY testing:**
The subtle distinction between two patterns that often get conflated.

✅ **Model answer:**
"Strategy says 'there are several ways to do one job, all sharing one interface.' Registry answers a separate question: 'at runtime, given a plain string (like `'zibal'`), which implementation do I select?' — meaning a Registry is a name-to-implementation lookup book that sits on top of Strategy. `PaymentGatewayRegistry` does exactly this: `register('zibal', $zibalGateway)` inside `CommerceServiceProvider::boot()`, then `PaymentGatewayRegistry::get('zibal')` wherever it's needed. One real, important detail about Registries: since they're usually bound as `singleton()` (one single instance with every implementation already registered inside it), you can't swap them mid-test the way you'd swap a simple `bind()` — a real, documented gotcha in `HANDOFF.md`, first discovered in `ConnectorRegistry`'s own tests."

🔁 **Likely follow-ups:**
1. "How do you work around that in tests?" → By registering a Mock implementation directly on that same Registry instance, rather than trying to rebind the entire Registry.
2. "Is a Registry always a singleton?" → Usually yes, because the mapping itself (which name maps to which implementation) is fixed for the duration of a request — unlike `LLMClientInterface` (question 2), where the *selected implementation itself* might change mid-test.

🚩 **Red flags:**
Treating Registry and Strategy as the same thing — they're complementary patterns, not synonyms.

---

### Q7: The Observer/Event Listener pattern — do domain events run sync or queued? How was that decided?

🎯 **What the interviewer is REALLY testing:**
Real understanding of the sync-vs-async trade-off — not just repeating "events should always be async because it's better."

✅ **Model answer:**
"Every listener in this project runs synchronously today — the same HTTP request that dispatches an event also waits for every listener to fully finish. This is a deliberate choice, not a forgotten limitation: `SendNotificationAction` (which listens for several events) has its own retry-and-backoff logic, but it still runs inside that same request cycle — because no Job exists for it yet. That class's own docblock explicitly says converting it to async only needs wrapping the same call in a Job, not a structural rewrite. Staying sync has a real cost — if a listener is slow (say, a network call that's backing off), the entire main HTTP response gets slow too; but for the platform's current scale, the simplicity of debugging (the whole chain sitting in one single stack trace) outweighed that cost."

🔁 **Likely follow-ups:**
1. "Which listener carries the highest risk of being slow?" → `WebhookSender` (through `ChannelSenderRegistry`), since it connects to a real external server — the first real candidate to convert into a Job.
2. "Why not build it async from the start if there's a real cost?" → Because `QUEUE_CONNECTION=sync` was set in `phpunit.xml` since Phase 1, and no Job existed at all until Phase 5 (Bulk Operations) — the real Queue infrastructure was added when it was genuinely needed, not preemptively.

🚩 **Red flags:**
"Everything should always be async" with no understanding that async carries real operational cost (a Worker, a queue, observability) that has to be justified.

---

### Q8: What's the difference between a (Laravel) Facade and a (design pattern) Facade? Where is either used in this project?

🎯 **What the interviewer is REALLY testing:**
A common terminology trap — a lot of Laravel developers conflate these two.

✅ **Model answer:**
"A design-pattern Facade means 'a simple face in front of a complex subsystem' — the MCP Gateway itself, at a high level, is exactly this. A Laravel Facade is a more specific technical mechanism: a static-looking class that resolves a real instance from the container behind the scenes (like `Cache::remember()`). This project uses the second kind sparingly and prefers explicit Dependency Injection instead — with one deliberate, documented exception: the Laravel-specific SDK (`packages/opencommerce-sdk-laravel`) ships a real Facade called `OpenCommerce` (`OpenCommerce::execute(...)`), because that SDK's audience is an external developer who prefers the convenience of a static-looking call over explicit injection — the exact opposite of the platform's own internal philosophy."

🔁 **Likely follow-ups:**
1. "Why avoid Facades internally but ship one in the SDK?" → Because inside the platform, explicit testability (mocking an injected dependency) is the priority; in the SDK, the end developer's convenience is the priority.
2. "How does a Laravel Facade actually work under the hood?" → Through `__callStatic()`, which routes the static call to `Facade::getFacadeRoot()` (a real instance resolved from the container).

🚩 **Red flags:**
Conflating these two completely different concepts that just happen to share a name — a common mistake that immediately reveals the depth of your knowledge.

---

### Q9: Where is Middleware used in this project — and where is it deliberately *not* used?

🎯 **What the interviewer is REALLY testing:**
Understanding that every tool (Middleware vs. an explicit Action call) has a proper scope, not that one is universally "the right choice."

✅ **Model answer:**
"`ApiVersioning` is a real Middleware sitting on `routes/mcp.php` — because it only ever needs the raw Request itself (the URL path, the `Accept` header), which is already available before the Controller even runs. Rate limiting, by contrast, is deliberately **not** Middleware — it's an explicit `EnforceRateLimitAction` call inside the Controller itself, right after authentication. The reason: rate limiting needs the Agent's real `id`, which simply isn't known at all until `AgentAuthenticationService` has validated the token — a Middleware running before the Controller doesn't have that information yet. This is a general rule in this project: a cross-cutting concern shared across several routes becomes Middleware if it only needs the raw Request; it stays an explicit Action if it needs something only known inside the Controller, after authentication."

🔁 **Likely follow-ups:**
1. "Couldn't you put rate limiting after a separate auth Middleware?" → Technically possible, but this project has always kept authentication inside the Controller itself (`AgentAuthenticationService`), not a Laravel Guard/Middleware — so this ordering was the more natural fit.
2. "Where else does this same rule show up?" → The human Admin Dashboard shows the opposite case — because it uses a real Laravel Session/Guard, the standard `auth`/`admin` Middleware makes complete sense there, since authentication genuinely happens before the Controller runs.

🚩 **Red flags:**
"Everything should be Middleware because it's cleaner" — with no regard for what information Middleware actually has access to, and when.

---

### Q10: Why does every Entity have its own separate DTO? Why not just return the Entity directly?

🎯 **What the interviewer is REALLY testing:**
Understanding the boundary between an internal object (which can change) and an external contract (which shouldn't change without warning).

✅ **Model answer:**
"If an Entity were returned directly as a JSON response, any internal change to that Entity (adding a new method, or renaming an internal field for clarity) would automatically break the MCP contract too — something no client (especially an AI agent that never reads the source code) should ever be surprised by. Every Entity has its own dedicated `*Data` class (e.g. `ProductData`) that Actions build and return — an explicit middle layer keeping the 'external' shape separate from the 'internal' shape. Even more interesting: this project's DTOs typically have a `toArray()` that outputs `snake_case` keys (the wire contract) while the actual PHP classes use `camelCase` internally (the code contract) — meaning this layer also handles translating between coding convention and wire convention."

🔁 **Likely follow-ups:**
1. "Doesn't this cause code duplication?" → A little, but that duplication is deliberate and cheap compared to the risk of leaking internal details into a public contract.
2. "Is a DTO always a one-to-one match with one Entity?" → Not necessarily — some DTOs (like `ExecutionResultData`) carry several optional extra fields with no matching Entity field at all, specifically designed for that purpose.

🚩 **Red flags:**
"Because that's just how Laravel teaches you to do it" — with no real architectural reasoning behind it.

---

### Q11: Tell me about a real bug caused by a design pattern being misused, and how you found it.

🎯 **What the interviewer is REALLY testing:**
The classic "real debugging story" question — the answer needs to be a complete story with a concrete outcome, not a vague general statement.

✅ **Model answer:**
"Exactly the same Registry-vs-bind gotcha I mentioned in question 6 — but here's the full story: a test tried to swap a Mock implementation in place of a real Connector inside `ConnectorRegistry`, mid-run, the same way rebinding `bind()` works for ordinary interfaces. But because `ConnectorRegistry` itself was a singleton with every real implementation already registered during `boot()`, rebinding the interface itself didn't help — the already-built Registry instance kept holding onto the old implementation. That caused a test to actually hit a near-real, network-adjacent implementation instead of the intended Mock. The fix wasn't to change the Registry's architecture (which was correct for other reasons) — it was to register the Mock directly on that same Registry instance before the test ran. We recorded this in `HANDOFF.md` as a documented gotcha for every future developer, so nobody would burn time rediscovering the exact same trap."

🔁 **Likely follow-ups:**
1. "Why not make the Registry itself rebindable?" → Because being a singleton was correct for real production behavior (the name-to-implementation map shouldn't change mid-request); the problem was purely a testing concern, not a flaw in the pattern itself.
2. "How did you make sure this bug never happens again?" → By explicitly documenting the gotcha, not through an automated fix — a deliberate trade-off keeping the architecture simple at the cost of one documented caveat.

🚩 **Red flags:**
Having no real story for this question at all — a strong signal the person has never genuinely gotten deep into testing this codebase.

---

### Q12: Why does `MCPExceptionHandler` eventually need to be container-resolved instead of `new`'d directly?

🎯 **What the interviewer is REALLY testing:**
A precise, real example of how a new requirement can break an old, seemingly harmless decision.

✅ **Model answer:**
"In `bootstrap/app.php`, the `$exceptions->render()` block originally called `new MCPExceptionHandler()` directly — completely harmless, since that class had no constructor dependencies at all. When i18n was added, this class needed real dependencies (`LanguageDetector`, `TranslationServiceInterface`) for the first time, so it could include `error.localized_message` in an error response. `new MCPExceptionHandler()` stopped working — either we'd have to manually build those two dependencies ourselves (duplicating exactly what the container already does), or switch to `app(MCPExceptionHandler::class)` and let the container resolve them. That one-line change was exactly the moment it became clear that manually `new`-ing a class is never a permanently safe decision — that class could gain a new dependency at any point in the future."

🔁 **Likely follow-ups:**
1. "Does this mean everything should always be container-resolved?" → Not necessarily — Domain Value Objects and Entities are deliberately always built with `new`, since they have, and should have, zero external dependencies; this rule only applies to classes that genuinely take real dependencies.
2. "How did you know this change was needed?" → The moment `LanguageDetector` was added to `MCPExceptionHandler`'s constructor, every `new MCPExceptionHandler()` call site immediately threw a 'missing required argument' error in tests — the type system itself surfaced the problem right away.

🚩 **Red flags:**
Not knowing the difference between manually `new`-ing a class and resolving it through the container, or assuming the two are always equivalent.

---

← [Overall Architecture](02-overall-architecture.md) | Next: [Database & Performance](04-database-and-performance.md) →
