← [Project Storytelling](01-project-storytelling.md) | Next: [Laravel & Design Patterns](03-laravel-and-design-patterns.md) →

# 02. Overall Architecture

This is where the interview shifts from "tell me about your project" to "prove to me you actually know how it works." Question 3 (the full request trace) is usually the one an experienced interviewer weighs the heaviest.

---

### Q1: Is this platform a modular monolith or microservices? Why that choice?

🎯 **What the interviewer is REALLY testing:**
Was this a deliberate, trade-off-driven choice, or just "because microservices are harder"?

✅ **Model answer:**
"Modular monolith — one single Laravel application, but internally split into 10 fully independent modules (`app/Modules/*`), each with its own boundaries. The reasoning: real microservices carry heavy operational cost — multiple databases, network coordination, distributed observability — that would have been premature for a project growing at this pace. The key benefit of a modular monolith is that it gives you the exact same boundary discipline microservices give you (a module only depends on another through an interface, never directly on its Model) without that operational cost. And because the boundaries were kept clean from day one, if a genuine need for physical separation ever arises (say, the Agent Orchestrator module ends up under a very different computational load than Commerce), the cut line is already there — the exact opposite of the painful process of splitting spaghetti code into microservices."

🔁 **Likely follow-ups:**
1. "Which module would be the first real candidate to split out into its own service?" → The Agent Orchestrator, because LLM calls carry a very different latency/load profile than Commerce, and it already only talks to the rest of the system through the Core's own Action/CapabilityExecutionService, never directly through another module's Repository.
2. "Do all modules share one database?" → Yes, today; multi-tenant isolation happens at the `tenant_id` level, not through separate databases — a Phase-1 decision that also explicitly kept the "database-per-tenant" migration path open (file 11 of this handbook, multi-tenancy).

🚩 **Red flags:**
"Microservices are always better but we didn't have time" — it signals the person sees the modular monolith as a limitation, not a legitimate, proven architectural choice.

---

### Q2: Walk me through each module's layering — what do Domain/Application/Infrastructure/Interfaces actually do?

🎯 **What the interviewer is REALLY testing:**
Did you just memorize the folder names, or do you actually understand why the dependency direction exists?

✅ **Model answer:**
"Four layers, each with one clear responsibility, and one golden rule: dependencies always point inward.
- `Domain`: pure business logic — Entities, Value Objects, Repository interfaces, Domain Events, Domain Services. Completely framework-independent; not a single `use Illuminate\...`.
- `Application`: the orchestrator — Actions, DTOs. This is where Laravel becomes visible (dependency injection from the container), but the actual business logic still doesn't live here, only the coordination of steps.
- `Infrastructure`: the real implementation — Eloquent Models, Eloquent Repositories, external HTTP clients (like `WooCommerceClient`).
- `Interfaces`: the contact point with the outside world — Controllers, request validation, MCP capability definitions.
Dependency direction: `Interfaces` depends on `Application`, `Application` depends on `Domain`, and `Infrastructure` also depends on `Domain` (since it implements Repository interfaces) — but `Domain` never depends on any of the other three. That means you can swap the entire `Infrastructure` layer (e.g. move off MySQL) without `Domain` even noticing."

🔁 **Likely follow-ups:**
1. "How do you actually guarantee Domain never becomes coupled to Laravel? Do you have an automated guard for it?" → An honest answer: today it's mostly enforced through code discipline and review, not an automated tool like Deptrac; that's a real, unbuilt improvement.
2. "Why does an Action live in Application, not Domain?" → Because an Action usually 'orchestrates' several Repositories/Domain Services together (e.g. calculate price + decrease stock + record payment), which is itself not a Domain decision — it's the ordering of several Domain decisions.

🚩 **Red flags:**
Saying `Domain` is "where the Eloquent models live" — that's exactly backwards; Eloquent models live in `Infrastructure`.

---

### Q3: Trace a real MCP request for me, step by step, from the moment it arrives to the final response.

🎯 **What the interviewer is REALLY testing:**
This is the ultimate test of "do you actually know how your own system works" — you can't fake this one without having genuinely read the code.

✅ **Model answer:**
"Say an Agent sends `POST /mcp/v1/execute` with `{"capability": "commerce.product.search", "input": {"query": "laptop"}}`.
1. The request lands in `MCPGatewayController` (which extends `AbstractMCPGatewayController`).
2. Authentication first: `AgentAuthenticationService` reads the Bearer token, hashes it, and looks it up in the `agent_tokens` table — if invalid, it returns `401` right there.
3. Rate limiting: `EnforceRateLimitAction` checks this Agent hasn't exceeded the 100-requests-per-minute cap — if it has, `429`.
4. Authorization: `CheckPermissionAction` checks this Agent, through one of its Roles, actually has the `commerce.products.read` permission this specific capability requires — if not, `403`.
5. Input validation: `MCPRequestValidationService` checks the input against that capability's own `inputSchema`.
6. Execution: `CapabilityExecutionService`, through `CapabilityHandlerRegistry`, finds the handler registered for `commerce.product.search` — registered inside `CommerceServiceProvider`, which calls `GetProductAction`/`SearchProductsAction` directly.
7. The Action reaches the `Domain` layer: through `ProductRepositoryInterface`, the real `EloquentProductRepository` reads products from the database and converts them into Entities.
8. The result is converted into a DTO (`ProductData`).
9. `AbstractMCPGatewayController::formatResponse()` wraps the result in that version's own envelope (v1: `data`/`meta`).
10. `MCPExceptionHandler` sits ready across this entire path, translating any exception into the correct HTTP status."

🔁 **Likely follow-ups:**
1. "If the Agent lacks permission, exactly where does it stop?" → Step 4, before the input is even validated — that ordering is deliberate (authorization before validation, not the other way around).
2. "How does this path differ for v2?" → It's exactly the same; only `MCPGatewayControllerV2` has a different `formatResponse()` (`result`/`metadata`). The whole authenticate → rate-limit → authorize → execute sequence lives once, in `AbstractMCPGatewayController`, so the two versions can never drift apart.

🚩 **Red flags:**
Jumping straight from Controller to database without mentioning the auth/authorization/validation steps — it shows the person only knows the happy path, not the real security architecture.

---

### Q4: How do two modules (say, Commerce and Loyalty) talk to each other without becoming coupled?

🎯 **What the interviewer is REALLY testing:**
Practical understanding of Dependency Inversion at the module level, not just at the class level.

✅ **Model answer:**
"The fixed rule: a module never directly depends on another module's Model or Entity — only on that module's own published interfaces. Two real mechanisms:
1. **Domain Events:** when `PlaceOrderAction` in Commerce places an order, it dispatches `OrderWasPlaced` — carrying the full `Order` itself. `OrderPlacedListener` in Loyalty listens for it and awards points, without Commerce ever knowing Loyalty exists at all.
2. **Repository interfaces:** when Loyalty needs to validate a Customer, it never touches `App\Modules\Commerce\Infrastructure\Models\Customer` directly — it only gets `Commerce\Domain\Repositories\CustomerRepositoryInterface` injected.
There's also one deliberate, documented exception: the Reporting module queries Commerce's/Loyalty's Eloquent models directly (`Infrastructure/Queries/*QueryBuilder`) — because computing an aggregate (SUM/COUNT) through a Repository would mean fetching thousands of full Entities and summing them in a PHP loop, exactly the anti-pattern you're supposed to avoid. This exception is deliberate, documented, and scoped to exactly those 5 Query Builder classes."

🔁 **Likely follow-ups:**
1. "Isn't that exception dangerous?" → It is, and that's exactly why it's documented: if Commerce's/Loyalty's schema changes, these same 5 classes have to change too — a deliberate coupling, not a hidden one.
2. "Do Domain Events run sync or async?" → Sync today (same HTTP request); file 9 of this handbook covers exactly that trade-off.

🚩 **Red flags:**
Not mentioning the Reporting exception, or pretending "the rule is never broken" — a real architect knows exactly where and why a rule was deliberately bent.

---

### Q5: Why shouldn't Core know anything about Commerce? What actually happens if it does?

🎯 **What the interviewer is REALLY testing:**
Do you understand the real consequences of breaking this rule, or are you just repeating "Separation of Concerns" as a slogan?

✅ **Model answer:**
"If `app/Core` had a `use App\Modules\Commerce\...`, two real problems follow: first, the Core is no longer usable for any other domain at all — exactly what would make the 'fork the project into a completely different industry' model (main series file 22) impossible. Second, you'd create a genuine circular dependency: Commerce already depends on Core (identity, permissions); if Core also depended on Commerce, the two could no longer be independently tested or developed. This rule isn't just theoretical in this project — every time a new feature (like i18n) needed a new field on `AuthContext`, that field was added in Core, but its meaning (which language, how it's detected) stayed entirely irrelevant to Commerce."

🔁 **Likely follow-ups:**
1. "So how does Commerce use Core's own services?" → Only through Core's own published interfaces — e.g. `AuthContext`, `CheckPermissionAction` — never the other way around.
2. "Give me a real example of where this rule almost got broken." → When Finance needed to supply a real tax rate for Commerce's own pricing, Commerce never became directly dependent on Finance — instead Commerce defined its own interface (`TaxRateProviderInterface`) and Finance implemented it; the dependency direction always stayed one-way.

🚩 **Red flags:**
Repeating only the phrase "Separation of Concerns" with no explanation of the actual consequence or a real example.

---

### Q6: Is there a deliberate exception to "no module directly accesses another module's Model"?

🎯 **What the interviewer is REALLY testing:**
Do you follow an architectural rule blindly, or do you know when and why a deliberate exception is the right call?

✅ **Model answer:**
"Yes — the Reporting module. Five classes (`SalesQueryBuilder`, `TopProductsQueryBuilder`, ...) query Commerce's and Loyalty's Eloquent Models directly, not through a Repository interface. The reason: these are strictly read-only (they never write anything), and computing a SUM/COUNT/GROUP BY through a Repository's `listByTenant()` would mean pulling thousands of full Entities and manually summing them in PHP — exactly the N+1/loop-aggregate anti-pattern. This exception is the standard CQRS pattern ('a read model can cross an aggregate boundary a write operation never could') — and the Analytics module later repeated the exact same exception one layer over, on Reporting's own Query Builders (not directly on Commerce), rather than recomputing the same numbers from scratch."

🔁 **Likely follow-ups:**
1. "How scoped is this exception?" → Exactly to those 5+ Query Builder classes, with no interface and no ServiceProvider binding (since there's genuinely only one way to run a SQL aggregate against a specific schema).
2. "Why isn't this exception dangerous, but the lack of a Repository interface elsewhere would be?" → Because these never write anything; the real danger is when a module can also *write* another module's data directly through its Model.

🚩 **Red flags:**
Denying this exception exists ("no, everything always goes through an interface") — it shows the person hasn't actually read the Reporting module's own code.

---

### Q7: If you had to turn this modular monolith into microservices, where would you start?

🎯 **What the interviewer is REALLY testing:**
Are today's boundaries genuinely ready for this split, or do they just look ready?

✅ **Model answer:**
"First, the module with the least direct Domain-to-Domain dependency on the rest and the most to gain from independent scaling — the Agent Orchestrator: LLM calls have a completely different latency and load profile than Commerce, and it already only talks to the rest of the system through `CapabilityExecutionService`/`CheckPermissionAction` (Core's own mechanisms), never directly through another module's Repository. Next step: everywhere a Domain Event (`OrderWasPlaced` and similar) is used, it becomes a real message queue (RabbitMQ/Kafka) — since these are already built on the 'the publisher never knows its consumers' pattern, that conversion is relatively low-risk. The last and hardest piece is the Reporting Query Builder exception — since these rely directly on Commerce's/Loyalty's live schema, splitting them apart either needs a shared read replica, or a full rewrite into an event-driven pattern where each module publishes its own data for Reporting to consume."

🔁 **Likely follow-ups:**
1. "How does multi-tenancy get affected?" → Today's model (one database, `tenant_id`) either stays as-is (each service still filters by `tenant_id`) or moves to "database-per-service, still multi-tenant" — two separate paths, both already anticipated by this project's multi-tenant architecture.
2. "What's the real cost of doing this?" → An honest answer: distributed observability (tracing one request across several services), which is free today because everything runs in one process.

🚩 **Red flags:**
"I'd just move everything to microservices at once" — signals a lack of understanding that this is a gradual, risk-assessed process, not a switch you flip.

---

### Q8: Does ServiceProvider boot order actually matter? Give me a real example.

🎯 **What the interviewer is REALLY testing:**
A subtle, real Laravel container detail that only someone who's actually wrestled with it would know.

✅ **Model answer:**
"Yes, and this project has a real, important example: Commerce defines a `TaxRateProviderInterface` and binds its default to `NullTaxRateProvider` (always returns null, meaning a flat 9% fallback rate) inside `CommerceServiceProvider::register()` — so Commerce works completely standalone even if Finance isn't installed at all. When Finance genuinely exists, `FinanceServiceProvider::register()` rebinds that same interface, this time to `CommerceTaxRateProvider` (which actually reads from Finance's own tax rate table). Because Laravel runs every provider's `register()` before any provider's `boot()`, and `bootstrap/providers.php` deliberately lists `FinanceServiceProvider` after `CommerceServiceProvider`, the second bind always wins — with zero `if` conditions in the code, purely through registration order."

🔁 **Likely follow-ups:**
1. "What would happen if the order were reversed?" → Commerce would overwrite its own bind again and Finance would have no effect — exactly why that explicit order in `bootstrap/providers.php` is documented and deliberate.
2. "Why this pattern instead of an explicit `if (class_exists(Finance))` check?" → Because this pattern is fully aligned with Dependency Inversion — Commerce shouldn't even need to know whether Finance exists; it only ever consumes an interface.

🚩 **Red flags:**
"ServiceProvider order doesn't matter, Laravel handles it automatically" — flatly wrong, and it shows the person has never actually run into a real bind-override bug.

---

### Q9: How do you add a completely new domain module (not commerce) without touching the Core at all?

🎯 **What the interviewer is REALLY testing:**
Is the claim "the Core is domain-independent" actually testable, or is it just a slogan in the docs?

✅ **Model answer:**
"Exactly the same seven-step pattern every existing module already follows: a new folder under `app/Modules/`, with the standard four layers (`Domain`/`Application`/`Infrastructure`/`Interfaces`); its own Entities and Value Objects; its own Repository interfaces with an Eloquent implementation; a `*ServiceProvider` that binds those interfaces and registers its own MCP capabilities in `CapabilityHandlerRegistry`; and a `routes/*.php` it loads itself via `loadRoutesFrom()`. None of this requires changing a single line inside `app/Core` — this is literally the test the main tutorial series' file 22 'fork into a new industry' model is built on: identity, permissions, authentication, and the MCP Gateway are all already there; only the new domain logic gets added."

🔁 **Likely follow-ups:**
1. "Is this actually tested, or just theoretical?" → Practically every module from Phase 2 onward (CRM, Finance, ...) has literally run this exact exercise — each one was added without touching Core at all.
2. "Did the Agent Orchestrator follow the exact same pattern?" → Almost — with one documented difference: since it's an orchestration layer, not a business domain, it depends directly on Core's own Actions (`DiscoverCapabilitiesAction`), not on another module's Repository interface — a deliberate architectural exception, documented in `HANDOFF.md`.

🚩 **Red flags:**
Not being able to state the four layers precisely, or how capability registration works — it shows the person has only heard "the Core is independent" without ever having actually done it.

---

### Q10: Where exactly does the MCP Gateway sit in the architecture? Why must everything pass through it?

🎯 **What the interviewer is REALLY testing:**
Understanding the MCP Gateway as a **deliberate architectural chokepoint**, not just a routing layer.

✅ **Model answer:**
"The MCP Gateway is exactly the Core's own `Interfaces` layer — the single door an AI agent walks through. What makes it matter is that every security/operational rule that must apply to *every* capability (authentication, rate limiting, authorization, API-version detection) is implemented exactly once, in `AbstractMCPGatewayController` — not duplicated inside every module's own Controller. This means a brand-new capability added to an eleventh module tomorrow automatically gets the exact same security guarantees, without anyone needing to remember to re-implement them. Even the human Admin Dashboard (which uses Sessions, not tokens) goes through a completely separate route — deliberately never merged with this gateway."

🔁 **Likely follow-ups:**
1. "What happens if a module tries to bypass this gateway?" → It can't, in any normal way — no other route exposes MCP capabilities; this shared Controller is the only real entry point.
2. "Why is rate limiting an explicit Action call instead of Middleware?" → Because it needs the Agent's real `id`, which isn't known yet at the point Middleware would run, before the Controller resolves it — a decision documented in main series file 17.

🚩 **Red flags:**
Describing the MCP Gateway as just "a routes file" — it shows its role as a centralized security layer was never understood.

---

### Q11: How does this project actually deploy? One process, or several?

🎯 **What the interviewer is REALLY testing:**
Operational understanding, not just code — do you know how a modular monolith actually runs in practice?

✅ **Model answer:**
"Because it's a modular monolith, everything runs inside one single PHP-FPM/web-server process — one deployment, not ten. That said, time-consuming work is still split off into separate processes from that same codebase: `routes/console.php` has several scheduled commands (like `loyalty:expire-points`) run through a real server Cron; and heavy operations (like importing a large CSV file) are handed off to a Queue/Job running on a separate Worker, instead of blocking the main HTTP request. So in terms of deployment units, it's one; in terms of real processes running in production, at least three categories: the web server, the queue Worker, and the Cron scheduler."

🔁 **Likely follow-ups:**
1. "What happens if the Worker goes down?" → Queued jobs (like a CSV import) stay queued, but the main API itself (the MCP Gateway) remains completely unaffected and available — because they're fully decoupled.
2. "Why does the test suite use the `sync` queue driver?" → So every test observes a Job's real behavior immediately, without needing to poll or simulate a real queue.

🚩 **Red flags:**
Assuming "modular monolith" means everything, always, runs in exactly one process with zero separation — it shows the person confuses code architecture with deployment architecture.

---

### Q12: What's the biggest architectural risk of a modular monolith, and how did you mitigate it?

🎯 **What the interviewer is REALLY testing:**
Critical thinking about your own architectural choice — not a blind defense of it.

✅ **Model answer:**
"The biggest real risk is gradual boundary erosion — over time, under pressure to 'move faster,' a developer is tempted to directly reach into another module's Model 'just this once, because it's faster.' If that happens repeatedly, the modular monolith's core benefit (clean boundaries, ready for future separation) quietly disappears, with no runtime error ever signaling it — the code still works, it's just no longer modular. Mitigating this in this project came in two layers: strict documentation of every decision (`HANDOFF.md`, which is still used as a code-review reference today), and an architecture audit before every new stage (checking a request against the real, existing code — the same discipline file 1, question 11 of this handbook covers). One real, unbuilt improvement: an automated tool (like Deptrac for PHP) enforcing these boundaries in CI, instead of relying only on human review."

🔁 **Likely follow-ups:**
1. "Give me a real moment where this temptation came up but you resisted it." → Exactly when Analytics wanted to query Commerce's/Loyalty's Models directly; the final decision was to reuse Reporting's own Query Builders instead of a second, direct access path.
2. "Why wasn't a Deptrac-like tool added from the start?" → An honest speed-vs-completeness trade-off — exactly the kind of thing file 1, question 9 of this handbook already covers under 'what would you build differently.'

🚩 **Red flags:**
"There's no risk at all, the architecture is perfect" — no real architecture is risk-free; claiming otherwise instantly costs you credibility.

---

← [Project Storytelling](01-project-storytelling.md) | Next: [Laravel & Design Patterns](03-laravel-and-design-patterns.md) →
