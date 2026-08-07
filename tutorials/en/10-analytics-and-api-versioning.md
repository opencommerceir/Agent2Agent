← [i18n and the Admin Dashboard](09-i18n-and-admin-dashboard.md) | Next: [Advanced Commerce](11-advanced-commerce.md) →

# 10. Phase 4 (Part Three) — Analytics, API Versioning, and Performance Optimization

The three final stages of Phase 4 all fall into the cross-cutting infrastructure category.

## Analytics — KPIs and the dashboard (Stage 6)

### The biggest correction of this whole session (at the time)

The original request would have built a brand-new module that re-computed Revenue, Orders, and Top Products from scratch, straight from Commerce's/Loyalty's tables. The problem: **Reporting (file 7) already computes exactly these numbers.** Building a second way to compute the same figures means two sources of truth that could silently drift apart over time.

This was raised directly with the user before any code was written, and confirmed: **reuse.** The result:

```
CalculateKPIAction (the single entry point for every KPI)
   ├── for KPIs Reporting already knows how to compute → calls Reporting's own Query Builders directly
   └── for genuinely new KPIs (conversion rate, growth rate, ...) → 4 new Domain Calculators
```

This Query Builder exception (seen for Reporting in file 7) is reused here for the **second** time, with the exact same reasoning — not a new pattern.

### Four genuinely new calculators

`RevenueCalculator`, `OrderCalculator`, `CustomerCalculator`, `ConversionRateCalculator` — all pure and framework-free, each combining numbers that were already fetched (the same shape every other Domain Service in this project has).

An honest note: `CustomerRetentionRate` and `CustomerLifetimeValue` are both **documented simplifications**, not a real cohort or predictive model — a deliberate, explicit trade-off recorded in `HANDOFF.md` as "real, working, but deliberately simplified."

### Two important security decisions

- Three capabilities (`analytics.dashboard.stats`, etc.) originally had a `tenant_id` input in the request — this input was **deliberately dropped**, because accepting a `tenant_id` from an Agent's own input would let any Agent view another tenant's financial data just by changing a number. Every capability in this codebase scopes only to its own `AuthContext`, never to caller-supplied input.
- Results cache for 1 hour; a new record is only persisted on an actual cache miss.

## API Versioning — v1 and v2 (Stage 7)

### A real contradiction in the request itself, found and corrected

The stated priority order for version detection was: URL > Header > Query. But the request's own example test showed exactly the opposite (a header overriding an explicit `v1` URL). This contradiction was raised before writing any code; the safer decision was confirmed: **an explicit URL version always wins.**

### v1 and v2 are one platform in two envelopes

As mentioned in file 5 — the only real difference is the response envelope shape. To make sure the two versions could never drift apart on the security path (authenticate → rate-limit → authorize → execute), that sequence was extracted into one shared base class — each version-specific controller only implements its own response formatting.

## Performance Optimization (Stage 8, the last stage of Phase 4)

### Auditing database indexes

Before writing the migration, the requested index list was compared against the real schema: most requested indexes already existed, and two referenced columns that didn't exist at all. Only 8 genuinely-needed indexes were added.

### Four real N+1 bugs found

A systematic search for `toEntity()` methods reading a `hasMany` relation without eager loading turned up four real bugs — one of them (`findActiveByEventType()`) ran on **every single** domain event dispatch, not just an occasional page view. All four were fixed with a simple `->with()`, plus a regression test asserting query *count*, not just correctness.

### Two decisions deliberately built differently than requested

1. **Gzip compression** was scoped only to `web` routes, never `mcp/*` — because global compression would break roughly 600 JSON-asserting tests, and risks double-compression if server-level compression is also enabled.
2. **Persistent PDO connections (Persistent Connection)** became **opt-in**, not the default — because in a multi-tenant app, a shared connection between requests can leak one transaction's state into the next tenant's request.

### CacheService

A new tag-aware cache service (`Cache::tags()`), wired into exactly one real path (reading/updating/deleting a product) — not everywhere at once. The cache key includes `tenantId` so one tenant's cached product can never leak to another tenant with the same numeric ID.

## Summary table

| Stage | Key achievement |
|---|---|
| Analytics (6) | Reused Reporting instead of duplicating it + a security fix dropping input `tenant_id` |
| API Versioning (7) | URL always wins; shared security logic between v1/v2 |
| Performance (8) | Fixed 4 real N+1 bugs, real indexes, tenant-safe tag-aware caching |

With this file, **Phase 4 is fully complete** (648 tests, zero regressions). The next file moves into Phase 5 — where Commerce gains five advanced new capabilities.

---
← [i18n and the Admin Dashboard](09-i18n-and-admin-dashboard.md) | Next: [Advanced Commerce](11-advanced-commerce.md) →
