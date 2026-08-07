← [Shipping and Notifications](08-shipping-and-notifications.md) | Next: [Analytics and API Versioning](10-analytics-and-api-versioning.md) →

# 9. Phase 4 (Part Two) — Internationalization and the Admin Dashboard

## Internationalization (i18n) — Stage 4

### Why not use Laravel's own translation system?

Laravel already ships a JSON translation feature, but it expects a single flat `lang/{locale}.json` file keyed by the original text. The real requirement here was different: `lang/{code}/{group}.json` — multiple files per group, with dot-path keys like `messages.dashboard.title`. So a small, custom subsystem (about 40 lines) was built instead.

The main pieces:

```
Language                          enum: en / fa
TranslationServiceInterface        the Domain contract
JsonTranslationLoader              the implementation: reads JSON files
LanguageDetector                   detects the request's language
```

### Language detection priority

```
1. the ?lang= URL parameter
2. the Accept-Language header
3. the Tenant's own default language (default_language column)
4. English (the final fallback)
```

For a Listener (which has no real HTTP Request), only tiers 3 and 4 apply (`detectForTenant()`).

### One very important, recurring gotcha

**A translation key must always start with its group name**: `t('messages.dashboard.title')`, never `t('dashboard.title')`. Omitting the group doesn't raise an error — it silently falls back to showing the key itself as the text. This exact mistake hit all 17 Admin Dashboard Blade files at once in Phase 4, and was only caught by a test that actually asserted on the rendered text (`assertSee(...)`).

## The Admin Dashboard — Stage 5

### Why was this deferred?

The original request bundled the translation backend together with an 8-page admin dashboard, but no human-authentication mechanism existed anywhere in the project yet (every identity path up to that point was Agent-bearer-token-only). Rather than rushing a decision, the question was raised, and the user chose to split the work: first, only the translation backend; the dashboard itself, with a clear auth architecture, in its own later stage.

### The identity model for the Admin Dashboard — an important course correction

The first assumption was that human login should go through `OrganizationMember` (since that model already existed for membership inside one Tenant's Organization). But once the real page list was clear (e.g., a "Manage Tenants" page that needs full CRUD across **every** tenant), that assumption turned out to be wrong — a platform operator isn't a member of one specific organization; they need to see every tenant.

The fix: a brand-new entity, with **no tenant_id at all**, called `User` — the second Core entity that sits above tenant scope (the first was `Tenant` itself).

```
User (platform-level, no tenant_id)
  ├── Email, HashedPassword (built on plain PHP password_hash)
  ├── UserRole: Admin / Operator
  └── protected by a real Laravel session Guard (extends Authenticatable)
```

**Critical point**: this system is completely **independent** from the Role/Permission system Agents use (file 4). Two mechanisms, two paths, never combined — because they answer different questions: "what can this platform operator access" vs. "what can this Agent do inside this tenant."

Default seeded login (change or remove before any real deployment):
```
admin@opencommerce.test / password
```

### The eight Admin Dashboard pages

| Page | What it does |
|---|---|
| Home | 6 KPI cards, revenue/order charts, top 5 products, recent orders |
| Tenants | Full CRUD across tenants |
| Agents | CRUD + suspend/activate, filterable by tenant |
| Products | Read-only, tenant-selectable |
| Orders | List/detail/cancel |
| Notifications | Read-only listing |
| Analytics | A KPI calculation form + CSV/PDF export |
| Settings | Manages only the tenant's default language |

### The golden rule of dashboard controllers

Every dashboard controller calls the **exact same Actions** its matching MCP capability's handler calls — no business logic is ever re-written inside a controller. The Orders page, for example, uses the same `ListOrdersAction`/`GetOrderAction`/`CancelOrderAction` any MCP caller would use.

Since Actions are usually tenant-scoped and a dashboard user isn't tied to a single tenant, every relevant page carries a `?tenant_id=` selector.

### A subtle bug that hit several pages at once

All six tenant-selector dashboard controllers had this code:
```php
$tenants[0]->id() ?? null
```
Problem: when `$tenants` is empty, accessing index `[0]` itself throws before `?->`/`??` ever get a chance to act — because `?->` only protects the "method call" step, not the "array access" step before it. The correct form:
```php
($tenants[0] ?? null)?->id()
```
This bug only showed up against a completely empty database (no tenants at all), and was only caught once a test specifically exercised that exact scenario. A good reminder to always test the "zero data" case.

## Summary of this file

- A custom translation subsystem implements the `{group}.{path}` key shape.
- Language is detected through a four-tier priority chain.
- The Admin Dashboard is a thin Interfaces layer that only ever reuses existing Actions.
- Human authentication (`User`) is entirely independent from Agent authentication.

Next, we look at Analytics/KPIs, API versioning (v1/v2), and performance optimization — the three closing stages of Phase 4.

---
← [Shipping and Notifications](08-shipping-and-notifications.md) | Next: [Analytics and API Versioning](10-analytics-and-api-versioning.md) →
