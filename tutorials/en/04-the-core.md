← [Tech Stack and Project Structure](03-tech-stack-and-project-structure.md) | Next: [The MCP Gateway](05-mcp-gateway.md) →

# 4. The Core — Identity, Multi-Tenancy, Permissions

The Core (`app/Core/`) was built in Phase 1 and has **stayed independent of every domain to this day** — none of its code imports anything from `App\Modules\*`. This file introduces its main building blocks.

## The identity model

A few concepts that must always be kept distinct:

### Tenant
An independent business on the platform. Data isolation happens at the `tenant_id` level. `Tenant` is one of only two entities that sit **above** tenant scope (it has no `tenant_id` of its own) — the other is `User` (file 9).

### Organization and OrganizationMember
The organizational model **inside** one Tenant; reserved for the future — for when a business's own staff need to log in directly (not built yet, only modeled).

### Agent
The identity of an **AI agent** — not a human. Every Agent has an `AgentToken` (a bearer token) used to authenticate against MCP. `Agent.type` is a separate enum (`shopping`/`analytics`/`customer_service`/`custom`) — **this is completely different from the Agent Orchestrator module's own `AgentType`** (`ceo`/`sales`/`support`/`finance`), and the two must never be confused (this distinction becomes critical in file 14).

### User
A platform-level human identity — for logging into the **Admin Dashboard**. Unlike `Agent`, this is a real human with a real password. `User` also has no `tenant_id`, because a platform operator must be able to manage every tenant (full detail in file 9).

## The permission system

A simple, effective RBAC model:

```
Permission  → one specific grant, e.g. commerce.products.read
Role        → a collection of Permissions
MemberRole  → links a member (e.g. an Agent) to a Role
```

**Critical point:** this permission system is **only used to check MCP capabilities for Agents.** The Admin Dashboard's own authentication (`User`/`UserRole`) is a completely separate, independent mechanism — two sessions, two mechanisms, never combined.

## The Capability Registry

Every "Capability" has a standard, three-segment name:

```
domain.resource.action
```

Examples: `commerce.product.search`, `crm.ticket.create`, `agent.goal.execute`

This "exactly three segments" constraint is a **strict rule** across the whole project (`CapabilityName`/`PermissionKey` enforce it) — file 17 shows it repeatedly forcing capability names to be renamed.

## Capability execution

Two classes are the beating heart of the Core:

- `CapabilityHandlerRegistry` — every module registers a handler function for each of its own capabilities.
- `CapabilityExecutionService` — looks up the requested capability and calls its handler with the input and the `AuthContext`.

A handler's signature:

```php
callable(array $input, AuthContext $context): array
```

### AuthContext — the sacred MCP boundary

`AuthContext` is a simple object holding: `tenantId`, `agentId`, and (since the i18n phase) `language`.

**A very important rule:** `AuthContext` only ever exists at this one boundary (MCP). It is never passed deep into a module's Domain or Application layer — those only ever see plain scalars like `int $tenantId`. This pattern (pattern #1 in file 17) is one of the most important architectural rules in the entire codebase.

The rare, documented exception: inside the Agent Orchestrator module, since that module needs to re-enter the same MCP boundary (call yet another capability), a few specific Interfaces genuinely do take `AuthContext` — this exception is carefully documented (file 12).

## Marker Interfaces — a clever trick

How can the Core map a module-specific exception to the right HTTP status code (say, 404 or 409) without knowing that exception's concrete class?

Answer: two marker interfaces defined in the Core:

```php
NotFoundExceptionInterface    → maps to HTTP 404
ConflictExceptionInterface    → maps to HTTP 409
```

Any module implements one of these two on whatever exception logically means "not found" or "a data conflict." `MCPExceptionHandler` (Core) only ever checks for these interfaces — never the concrete class. This means new modules can add new exception types without ever touching the Core again.

## The MCP Gateway (first look)

Main routes:
```
POST /mcp/v1/execute          POST /mcp/v2/execute
GET  /mcp/v1/capabilities      GET  /mcp/v2/capabilities
```

The full detail of this layer, including real JSON payloads, is in the next file.

## Internationalization (i18n) in the Core

Since Phase 4, the Core carries a small, custom translation subsystem (not Laravel's own built-in one, since the `lang/{code}/{group}.json` file shape didn't fit it):

- `Language` (enum `en`/`fa`)
- `TranslationServiceInterface` / `JsonTranslationLoader`
- `LanguageDetector` — language detection priority: `?lang=` URL parameter → `Accept-Language` header → the Tenant's default language → English

Full detail in file 9.

## Human authentication (the Admin Dashboard)

Since Phase 4, human login also lives in the Core: a real `User` with a hashed password (plain PHP `password_hash`/`password_verify`, not Laravel's Hash facade — because every Domain class must be testable without booting the framework), behind a genuine Laravel session Guard. Full detail in file 9.

## Summary table of Core responsibilities

| Subsystem | What it does |
|---|---|
| Tenant | Manages tenants |
| Organization/OrganizationMember | Organizational model inside one tenant (future-ready) |
| Agent Registry | Registers and tokens AI agents |
| Permission System | Grants permissions at the capability level for Agents |
| Capability Registry | Registers and discovers capabilities |
| Capability Execution | Actually runs a capability |
| MCP Gateway | The entry point for every agent request |
| i18n | Detects and translates language |
| Human Auth | Admin Dashboard login |

In the next file, we go deeper into the MCP Gateway itself and trace exactly what path a real request takes from authentication to execution.

---
← [Tech Stack and Project Structure](03-tech-stack-and-project-structure.md) | Next: [The MCP Gateway](05-mcp-gateway.md) →
