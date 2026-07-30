# OpenCommerce Platform — Session Handoff

**Status: Phase 1 (Core + MCP Gateway) is complete, tested, and verified end-to-end. Next up: Phase 2 (Commerce).**

This file is a working-state snapshot for picking up development in a new
session. It assumes you've already read `CLAUDE.md` and `docs/*.md` (the
project's standing rules) — this document is "what actually got built and
why," not a repeat of the architecture doctrine.

---

## 1. What exists right now

### `app/Core/` — Identity, Tenancy, Registry, Permissions, MCP Gateway

| Sub-area | Key classes | Notes |
|---|---|---|
| Tenant | `Domain/Entities/Tenant.php`, `Application/Actions/CreateTenantAction.php` | Status lifecycle: pending → active → suspended. No `tenant_id` on itself (it *is* the tenant boundary). |
| Organization | `Domain/Entities/Organization.php`, `OrganizationMember.php` | `OrganizationMember` is polymorphic (`MemberType::User\|Agent`) membership, distinct from RBAC roles. |
| Agent Registry | `Domain/Entities/Agent.php`, `AgentToken.php`, `RegisterAgentAction`, `GenerateAgentTokenAction`, `AuthenticateAgentAction` | Tokens: `oc_agent_` + 64 hex chars, only the SHA-256 hash is ever persisted (`agent_tokens` table, separate from `agents` — supports rotation/revocation). **Agent no longer carries an embedded `permissions` array** — that was removed; the Role system below is the only source of truth. |
| Permission System | `Domain/Entities/{Permission,Role,MemberRole}.php`, `CheckPermissionAction` | `Permission` is **global** (no tenant_id — shared platform vocabulary). `Role` is **tenant-scoped**. `MemberRole` links a member (User/Agent) to a Role. `CheckPermissionAction::execute()` returns bool; `::authorize()` throws `PermissionDeniedException`. |
| Capability Registry | `Domain/Entities/Capability.php`, `RegisterCapabilityAction`, `GetCapabilityAction`, `DiscoverCapabilitiesAction` | Also global (no tenant_id). Name format enforced by `CapabilityName` VO: **strict 3-segment `domain.resource.action`** (e.g. `commerce.product.search`) — a 2-segment name throws `InvalidCapabilityNameException`. This bit us once already (see §4). |
| Capability Execution | `Application/Services/CapabilityHandlerRegistry.php`, `CapabilityExecutionService.php` | **This is the piece that used to be a stub.** `CapabilityHandlerRegistry` maps a capability name → callable, singleton-bound in `CoreServiceProvider`. Any Domain Module registers its handlers here (see Demo module for the pattern). `CapabilityExecutionService::execute()` validates input against schema, looks up the handler, calls it, times it. If a capability is registered (discoverable) but has no handler, execution 404s (`CapabilityNotFoundException`) — that's intentional. |
| MCP Gateway | `Interfaces/HTTP/Controllers/MCP/{MCPGatewayController,MCPDiscoveryController}.php`, `Interfaces/HTTP/Requests/MCP/ExecuteCapabilityRequest.php`, `Exceptions/MCPExceptionHandler.php` | Routes: `routes/mcp.php` → `POST /mcp/v1/execute`, `GET /mcp/v1/capabilities`. **Controllers contain zero try/catch** — every exception bubbles up to `MCPExceptionHandler`, wired globally in `bootstrap/app.php`'s `withExceptions()`, scoped to `mcp/*` requests only. Error envelope: `{"error":{"code","message"}}`. Codes: `UNAUTHORIZED` (401), `FORBIDDEN` (403), `NOT_FOUND` (404), `VALIDATION_ERROR` (422), `INTERNAL_ERROR` (500, generic message unless `APP_DEBUG=true`, always logged via `report()`). |

### `app/Modules/Commerce/` — **skeleton only, Phase 2 is where this gets real logic**

- `Domain/UCP/` — 6 immutable value objects (`UCPProduct`, `UCPCategory`, `UCPCustomer`, `UCPOrder`, `UCPCart`, `UCPInventory`). Framework-free, `fromArray()`/`toArray()`. `UCPOrder`/`UCPCart` line items are plain arrays on purpose — a typed `UCPOrderLine` VO needs real connector data to design well, deliberately not invented yet.
- `Domain/Connectors/` — `ConnectorInterface`, `ProductConnectorInterface`, `OrderConnectorInterface`.
- `Infrastructure/Connectors/MockProductConnector.php` — the only implementation that exists. **No real Shopify/WooCommerce connector yet** — wasn't built because it couldn't be tested without live credentials; same pattern (implement `ProductConnectorInterface`) applies whenever real credentials exist.
- `Application/Services/ConnectorRegistry.php` — in-memory name → connector lookup, registered in `CommerceServiceProvider::boot()`.
- **`Domain/Entities/` is empty.** No Product/Order/Customer entities, no business logic, no migrations for Commerce data. This is *all* Phase 2 work.

### `app/Modules/Demo/` — proof-of-concept capabilities, not a real feature

Three working capabilities showing the full Capability Registry → MCP Gateway →
`CapabilityHandlerRegistry` → real handler path end to end:

- `demo.tools.echo` (needs `demo.echo.execute`) — echoes a message + timestamp.
- `demo.tools.time` (needs `demo.time.read`) — current UTC/unix time.
- `demo.tools.calculator` (needs `demo.calculator.execute`) — add/subtract/multiply/divide.

Actions live in `Application/Actions/` and are plain PHP (`execute(array $input): array`
— this signature, not typed named params, because they double as the callables
`CapabilityHandlerRegistry` invokes directly). Manifest data lives in
`Interfaces/MCP/DemoCapabilities.php`. **Capability *descriptions* are seeded via
`database/seeders/DemoCapabilitiesSeeder.php`, not registered in
`DemoServiceProvider::boot()`** — see §4, this is important if you add another
module's capabilities.

### `packages/opencommerce-sdk/` — standalone Composer package

- Namespace `OpenCommerce\SDK`, **Guzzle-based (not Laravel's `Http` facade)** — genuinely
  framework-agnostic, tested completely independently (`cd packages/opencommerce-sdk &&
  composer install && vendor/bin/phpunit tests` → 19/19, zero Laravel).
- Linked into the main app via a `path` repository in the root `composer.json`
  (`vendor/opencommerce/sdk` is a Windows junction, not a copy).
- Entry point: `new MCPClient(new MCPConfig(baseUrl: ..., token: ...))`. Methods:
  `discoverCapabilities()`, `execute(string $name, array $input = [])`, `getCapability(string $name)`.
- Every HTTP-level failure becomes a typed exception (`AuthenticationException` 401,
  `AuthorizationException` 403, `NotFoundException` 404, `ValidationException` 422,
  base `MCPException` for anything else) — there's no "check `isSuccess()`" path for
  errors, only for the happy case (`ExecutionResult::isSuccess()`/`getError()` always
  return `true`/`null` since failures throw instead of returning — documented in the
  code, not a hidden trap).
- `examples/sample-agent.php` at the repo root is a real, runnable standalone script
  (`php examples/sample-agent.php <token> [base-url]`) — proves the SDK works outside
  this Laravel app.
- **The old `app/SDK/*` (Laravel-coupled version) was deleted** — fully superseded, do
  not recreate it.

---

## 2. Full module structure reference

```
app/Core/
├── Domain/{Entities,ValueObjects,Events,Repositories,Exceptions}/
├── Application/{Actions,DTOs,Services,Listeners}/
├── Infrastructure/{Models,Repositories}/
├── Interfaces/HTTP/{Controllers/MCP,Requests/MCP}/
├── Exceptions/MCPExceptionHandler.php
└── CoreServiceProvider.php

app/Modules/Commerce/
├── Domain/{UCP,Connectors,Entities(empty)}/
├── Application/Services/ConnectorRegistry.php
├── Infrastructure/Connectors/MockProductConnector.php
└── CommerceServiceProvider.php

app/Modules/Demo/
├── Domain/ValueObjects/CalculatorOperation.php
├── Application/{Actions,DTOs}/
├── Interfaces/MCP/DemoCapabilities.php
└── DemoServiceProvider.php

packages/opencommerce-sdk/
├── src/{Config,Exceptions,DTOs,Authentication,Discovery,Execution}/
├── src/MCPClient.php
├── tests/  (own PHPUnit suite, own composer.json)
├── composer.json  (name: opencommerce/sdk, namespace OpenCommerce\SDK)
└── README.md

database/
├── migrations/  (2026_07_30_000001 .. 000007 — Core tables; no Commerce/Demo migrations exist)
└── seeders/DemoCapabilitiesSeeder.php  (called from DatabaseSeeder)

tests/
├── Unit/{Core,MCP,Commerce,Demo}/     — framework-free where possible (plain PHPUnit\Framework\TestCase)
└── Feature/{Core,MCP,Commerce,Demo}/  — Illuminate TestCase + RefreshDatabase (sqlite :memory:)

examples/sample-agent.php
bootstrap/app.php        — registers MCPExceptionHandler via withExceptions()
bootstrap/providers.php  — AppServiceProvider, CoreServiceProvider, CommerceServiceProvider, DemoServiceProvider
routes/mcp.php           — loaded by CoreServiceProvider::boot() via loadRoutesFrom()
```

---

## 3. Architectural patterns established — follow these for Commerce

Every Core sub-system (Tenant, Agent, Permission, Capability) was built with the
**same repeating pattern** — Commerce should follow it too:

1. **Domain Entity** — plain PHP, `readonly` where possible, no Eloquent/framework
   dependency, static named constructor (e.g. `Tenant::register(...)`).
2. **Domain Repository Interface** — in `Domain/Repositories/`, returns/accepts
   Domain Entities only.
3. **Infrastructure Eloquent Model + Repository implementation** — maps
   Entity ↔ Eloquent in a private `toEntity()` method.
4. **Application DTO** — `fromEntity()` / `toArray()`, what Actions return.
5. **Application Action** — one verb, one responsibility, constructor-injected
   Repository interface (never the Eloquent model directly).
6. **Domain Event** — past tense (`TenantWasRegistered`, not `RegisterTenant`),
   dispatched via `Event::dispatch()` inside the Action.
7. Bind interface → implementation in the owning module's `ServiceProvider::register()`.

**Multi-tenancy default:** every table needs `tenant_id` **except** deliberately-global
platform vocabulary (`permissions`, `capabilities` tables) — those are shared across
tenants by design, not oversights. If Commerce adds a new global-vocabulary concept,
follow that same exception; everything else gets `tenant_id`.

**Value Objects for any format-constrained string** — `PermissionKey` and
`CapabilityName` both enforce `domain.resource.action` via the identical regex
(`^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$`) but are kept as *separate types*
deliberately — a Capability and a Permission are different concepts in the ubiquitous
language even when the string shape matches.

**Testing pattern:**
- Pure Domain logic (Entities, VOs) → `PHPUnit\Framework\TestCase` directly, no
  Laravel bootstrap, sub-millisecond.
- Actions needing repositories → either Mockery-mocked interfaces (fast, no DB —
  see `tests/Unit/MCP/AuthenticationTest.php`) or full `RefreshDatabase` Feature
  tests when the point is proving real DB + container wiring.
- MCP HTTP behavior → `$this->postJson()`/`getJson()` against `Tests\TestCase`,
  never manual `artisan serve` + curl (that was only used for one-off human-verified
  smoke tests during development, not in the committed suite).

---

## 4. Non-obvious gotchas (learned the hard way — don't repeat these)

1. **`ServiceProvider::boot()` runs before `RefreshDatabase` migrates the test DB.**
   Any DB-querying code in `boot()` will crash every Feature test with
   `no such table: X`. Put "insert reference data once tables exist" logic in a
   **database seeder**, not a provider's `boot()`. (`DemoCapabilitiesSeeder` is the
   reference example — call `$this->seed(DemoCapabilitiesSeeder::class)` in tests
   that need those rows.)

2. **`CapabilityName`/`PermissionKey` require exactly 3 dot-separated segments.**
   `demo.echo` is invalid; `demo.tools.echo` is not. Always sanity-check new
   capability/permission strings against this before wiring them in.

3. **Guzzle's `http_errors: false` must be set per-request, not just at client
   construction.** If `packages/opencommerce-sdk`'s `AuthenticatedRequest` ever gets
   refactored, keep it on the `request()` calls themselves — an injected/custom
   Guzzle client won't inherit it from the default client's constructor options.

4. **JSON has no distinct float type.** `42.0` round-trips through
   `response()->json()` as `42` (int). Don't assert `assertJsonPath('x', 42.0)`.

5. **`git` is not on PATH in this shell environment.** Use PowerShell +
   `Get-ChildItem` to locate `git.exe` under `C:\Program Files\Git` if you need it,
   or ask the user to run git commands. Nothing in this session was git-committed —
   check `git status` before assuming any prior "handoff" implies committed state.

6. **PHP installed here is 8.2.12**, not 8.3+ as the docs' aspirational target
   states. Main `composer.json` already correctly says `^8.2`; the SDK package's
   `composer.json` was fixed to match (was `^8.3`, caused `composer install` to fail).

7. **`CapabilityExecutionService` now requires a real registered handler.** Any test
   that registers a capability via `RegisterCapabilityAction` and then actually
   calls `POST /mcp/v1/execute` on it (not just discovery, or an auth/permission
   failure that never reaches execution) needs a matching
   `CapabilityHandlerRegistry::register()` call — otherwise it 404s. Tests that only
   need the capability to *exist* (permission/auth/not-found checks) don't need a
   handler.

---

## 5. Known technical debt (ranked, carried over from two code reviews)

1. **No CI actually running yet.** `.github/workflows/tests.yml` exists but nothing
   confirms a GitHub remote/Actions is active — verify before relying on it.
2. **N+1 query on the permission-check hot path.**
   `EloquentMemberRoleRepository::findRolesForMember()` queries once per Role. Fine
   at demo scale; revisit with real traffic.
3. **No global rate limiting on `routes/mcp.php`.** Routes carry zero middleware
   (deliberate — no CSRF/session for a stateless Agent API), which also means no
   throttle. Needed before any public exposure.
4. **`User` identity path is incomplete.** `Domain\Entities\User` exists with no
   Repository/Infrastructure counterpart. `MemberType::User` is valid in the type
   system but unusable in practice — only `MemberType::Agent` has a working path
   end to end.
5. **`Role` is tenant-scoped, not organization-scoped.** Removing a member from one
   Organization revokes *all* their Roles tenant-wide (via
   `RevokeRolesWhenMemberRemovedFromOrganization` listener). Correct today (nothing
   supports multi-org membership yet); would need `Role.organization_id` if that
   changes.
6. **`DemoCapabilitiesSeeder` runs a DB check per capability, once, whenever someone
   runs `db:seed`** — not a recurring cost (unlike the original `boot()`-based design
   this replaced), but still not automatic on fresh deploys; document
   `php artisan db:seed` as a required deploy step, or build a dedicated
   `artisan capabilities:sync` command if that's not acceptable.
7. **Coverage percentage is unmeasured** — no Xdebug/PCOV installed in this
   environment. Test *scenarios* (success/error/edge cases per Action/endpoint) are
   thorough; a literal % is not available without installing a coverage driver.

---

## 6. How to run things

```powershell
# First time / after pulling
composer install
cd packages/opencommerce-sdk; composer install; cd ../..
composer update opencommerce/sdk   # only if the SDK package itself changed

# Database
php artisan migrate
php artisan db:seed --class=Database\Seeders\DemoCapabilitiesSeeder

# Tests
php artisan test                                    # full app suite (95 tests)
cd packages/opencommerce-sdk; vendor/bin/phpunit tests; cd ../..   # SDK's own suite (19 tests)

# Manual/live verification
php artisan serve --port=8000
php examples/sample-agent.php <agent-token> http://127.0.0.1:8000/mcp/v1
```

To generate a throwaway Agent token for manual testing, see the Tinker snippet in
`packages/opencommerce-sdk/README.md`'s "Quick Start" section.

---

## 7. What's next: Phase 2 — Commerce

Per `docs/roadmap.md`, Commerce is next. The skeleton from Phase 1 (`UCP/`,
`Connectors/`, `ConnectorRegistry`, `CapabilityHandlerRegistry`) is what it plugs
into. Concretely, Commerce needs:

- Real `Domain/Entities/` (Product, Order, Customer, Cart, Inventory) following the
  Entity → Repository → Action → DTO → Event pattern from §3.
- Migrations for Commerce's own tables (with `tenant_id` — commerce data is *not*
  global vocabulary like Permission/Capability).
- Real capabilities registered (e.g. `commerce.product.search`,
  `commerce.order.create`) with handlers wired into `CapabilityHandlerRegistry`,
  following the Demo module's pattern exactly.
- At least one real Connector implementation (WooCommerce or Shopify) — deferred
  in Phase 1 specifically because it needs live credentials to test against
  honestly; don't hand over untested HTTP-calling code.
- Decide whether capability *description* registration for Commerce follows the
  same seeder pattern as Demo, or graduates to a dedicated `artisan
  capabilities:sync` command (see debt item §5.6) — worth deciding once, not
  reinventing per module.

Before starting, it's worth deciding explicitly (not improvising mid-build) how a
Commerce capability's execution handler will reach into real business logic vs.
the connector layer — the Demo module's handlers call an Action directly; Commerce's
will likely need to call an Action that itself calls a Connector, which is a slightly
longer chain worth designing on purpose.
