← [Common Design Patterns](05-common-design-patterns.md) | Next: [Security, Auth & Multi-Tenancy](07-security-auth-and-multi-tenancy.md) →

# 6. Backend Infrastructure & Laravel

The previous chapters covered general, framework-independent concepts. This chapter is specifically about tools that Laravel (this project's PHP framework) itself provides — since the main tutorial series constantly refers to these names.

## What is a Framework?

**Simple definition:** a ready-made set of tools and rules that has already solved a lot of repetitive work for you (routing requests, connecting to a database, handling forms, baseline security) — you only build the logic specific to your project on top of it.

📍 **In this project:** Laravel is this framework — version 12 (main series, file 3).

## MVC (Model-View-Controller)

**Simple definition:** a classic organizational pattern for web applications: the "Model" holds data, the "View" is what the user sees (HTML), the "Controller" receives a request, works with the Model, and returns the right View.

**Why it matters here:** this project goes **beyond** plain MVC — because it's a pure API for AI agents (not a website with lots of pages), most of its "Controllers" never return a View at all, only JSON. The real logic isn't inside the Controller either — it lives in the DDD layers (chapter 4).

📍 **In this project:** Controllers (like `MCPGatewayController`) are deliberately kept **thin** — they only receive the request and hand it off to an Action; no business logic lives inside a Controller (an explicit `CLAUDE.md` rule: "Avoid Fat Controllers").

## Route

**Simple definition:** a rule that says "when a request arrives at this address with this method, run this code."

📍 **In this project:** every module owns its own route file (e.g. `routes/mcp.php`, `routes/payments.php`) and loads it itself, inside its own `ServiceProvider` — not all crammed into one giant central file.

## The Service Container and Service Provider

**Simple definition:** the "container" (which we saw in chapter 3) is the heart of Laravel — it builds every class and injects its dependencies. A "Service Provider" is where you tell this container: "whenever someone asks for this interface, give them this real implementation."

📍 **In this project:** every module has its own `*ServiceProvider` — e.g. `CommerceServiceProvider` says "whenever `ProductRepositoryInterface` is requested, give `EloquentProductRepository`," and it also registers that module's own MCP capabilities.

## Middleware

**Simple definition:** code that runs **before** a request reaches the main logic (or **after** a response is built) — like a gatekeeper or filter every request must pass through.

**Why it matters:** for things that need to apply to **every** request (or a class of requests) — like detecting an API version or adding a security header — without repeating that logic in every single Controller.

📍 **In this project:** `ApiVersioning` is a real Middleware that detects whether a request wants v1 or v2 — a deliberate decision explaining why rate limiting became an explicit Action call instead of Middleware (because it needs an agent's `id`, which isn't known yet at that point) is covered in main series file 17.

## Eloquent (ORM)

**Simple definition:** an ORM (Object-Relational Mapper) means instead of writing raw SQL, you work with ordinary PHP objects, and the ORM itself translates them into SQL. Eloquent is Laravel's official ORM.

**Why it matters here:** in this project, Eloquent is used **only** in the `Infrastructure` layer — an Eloquent "Model" is never the same thing as what we call an Entity in `Domain`; the Repository is what translates between the two (`toEntity()`).

📍 **In this project:** `App\Modules\Commerce\Infrastructure\Models\Product` is the Eloquent model; `App\Modules\Commerce\Domain\Entities\Product` is the pure DDD entity — two completely separate classes with a similar name, deliberately.

## Seeder

**Simple definition:** a script that populates the database with sample or initial data — for testing, a live demo, or baseline data every fresh install needs (like default permissions).

📍 **In this project:** `DemoShowcaseSeeder` (main series, file 16) is exactly this — it builds a complete, realistic store (40 products, 180 real orders, ...) so the live Showcase demo has something real to show.

## Artisan (Laravel's CLI)

**Simple definition:** Laravel's official command-line tool — commands like `php artisan migrate` (run database migrations) or `php artisan test` (run the test suite).

📍 **In this project:** even the platform's own scheduled commands (like `loyalty:expire-points`) are built and run on a daily/hourly schedule through this same Artisan system (main series, file 7).

## Queue, Job, and Worker

**Simple definition:** some tasks are time-consuming (like processing a CSV file with thousands of rows) and shouldn't make the user wait. A "Queue" is a line these tasks ("Jobs") get placed in; a separate process (a "Worker") runs them one by one, in the background.

**Why it matters:** it means the user gets an immediate response ("your task has started") instead of being forced to wait several minutes for full processing.

📍 **In this project:** `ProcessBulkImportJob` does exactly this — importing thousands of products from a CSV file, in the background, with progress tracking (main series, file 11).

## Cache

**Simple definition:** keeping the result of an expensive computation (e.g. a heavy query) somewhere fast to read from, so the next time the same thing is needed, it doesn't get recomputed — it's just read from the cache.

**Why it matters:** the cost of an expensive computation is only paid once, not every time.

📍 **In this project:** `CacheService` caches the result of reading a product — with one important security detail: the cache key includes `tenant_id` too, otherwise one Tenant's data could accidentally be returned to a different Tenant that happens to share the same numeric product id (main series, file 10).

## The `.env` File and Config

**Simple definition:** values that differ between environments (development, testing, real production) — like a database password or a third-party API key — are kept in a separate file (`.env`, never committed to Git), not hardcoded directly in the code.

**Why it matters:** if a secret key is hardcoded directly in the code, the moment the code is published publicly (e.g. on GitHub), that key is exposed too.

📍 **In this project:** `OPENROUTER_API_KEY`, `PAYMENT_GATEWAY`, `DB_PERSISTENT_CONNECTIONS` are all read from `.env` — with this project's fixed rule: **the default must always be safe and require no real infrastructure**; actually using real infrastructure is always an explicit opt-in.

## Composer (the PHP package manager)

**Simple definition:** a tool that downloads and manages third-party libraries (like Guzzle for HTTP requests), and can also publish your own project's code as an installable "package" for other projects.

📍 **In this project:** this platform's two PHP SDKs (`opencommerce/sdk` and `opencommerce/sdk-laravel`) are exactly this — installable in any other project through Composer.

---

← [Common Design Patterns](05-common-design-patterns.md) | Next: [Security, Auth & Multi-Tenancy](07-security-auth-and-multi-tenancy.md) →
