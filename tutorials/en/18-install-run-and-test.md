← [Architecture Patterns and Gotchas](17-architecture-patterns-and-gotchas.md) | Next: [Technical Debt and Roadmap](19-technical-debt-and-roadmap.md) →

# 18. Install, Run, and Test the Project — Step by Step

This file is fully practical. If you want to get this project running on your own machine and see the live demo, start here.

## Prerequisites

- PHP 8.2 or newer
- Composer
- Node.js and npm (for building the Admin Dashboard's and the demo's frontend assets)
- MySQL (optional for development — tests use in-memory SQLite)

## Step 1 — Install dependencies

```powershell
composer install
```

This installs every PHP package, including `barryvdh/laravel-dompdf` (for report PDF export).

The official PHP SDK has its own dependencies too:

```powershell
cd packages/opencommerce-sdk
composer install
cd ../..
```

Frontend assets (Tailwind, Alpine.js, Chart.js — needed by the Admin Dashboard and the demo page):

```powershell
npm install
npm run build
```

> Note: tests never need these built assets (`withoutVite()` is used in tests) — if you only want to run tests, you can skip this step.

## Step 2 — Set up the database

```powershell
php artisan migrate
php artisan db:seed
```

`db:seed` runs every module's capability seeder (Demo, Commerce, CRM, Finance, Workflows, Loyalty, Reporting, Shipping, Notifications, Analytics) and also creates a default Admin Dashboard user:

```
email:    admin@opencommerce.test
password: password
```

⚠️ Make sure to change or remove this default account before any real deployment.

So that output file links (like report CSV/PDF exports) work correctly:

```powershell
php artisan storage:link
```

## Step 3 — Run the test suite

```powershell
php artisan test
```

This should run around 1,102 tests successfully (zero failures). To test the SDK separately:

```powershell
cd packages/opencommerce-sdk
vendor/bin/phpunit tests
cd ../..
```

## Step 4 — Start the server

```powershell
php artisan serve --port=8000
```

Now these routes are available:

| URL | What it is |
|---|---|
| `http://127.0.0.1:8000/login` | Admin Dashboard login (use the default user above) |
| `http://127.0.0.1:8000/mcp/v1/*` | The MCP Gateway, v1 |
| `http://127.0.0.1:8000/mcp/v2/*` | The MCP Gateway, v2 |
| `http://127.0.0.1:8000/showcase` | The live Showcase demo (needs its own seed — next step) |

## Step 5 — Set up and run the live Showcase demo

The Showcase demo needs its own dedicated data, which isn't seeded by default:

```powershell
php artisan db:seed --class=DemoShowcaseSeeder
```

Or, if you want to rebuild the data from scratch (e.g. between two demo runs):

```powershell
php artisan demo:reset
```

Now open:

```
http://127.0.0.1:8000/showcase
```

- Pick a persona (CEO/Sales/Support/Finance).
- Click one of the "Suggested Goals" or type your own (e.g. "increase sales by 15% this week").
- Watch the "think → plan → execute → reflect" cycle render live.
- Watch the side panel tabs (Products/Orders/KPIs) update with real data.
- Click the 🕘 button to see past conversation history.

### If you want to use a real (free) AI model

Set these in your `.env` file:

```env
LLM_PROVIDER=openrouter
OPENROUTER_API_KEY=<your key from openrouter.ai>
OPENROUTER_MODEL=meta-llama/llama-3.1-405b-instruct:free
```

Then toggle "🧠 Use real AI" on the demo page. If no key is configured, nothing breaks — the system quietly falls back to the deterministic Planner.

### If you want to protect the live demo with a passcode

```env
SHOWCASE_PASSCODE=your-chosen-passcode
```

Leave it blank and anyone with the `/showcase` URL can access it directly.

## Scheduled commands

The project ships several Artisan commands that, in a real deployment, should be triggered every minute via a real cron entry (`* * * * * php artisan schedule:run`); to test any of them manually, just run them directly:

```powershell
php artisan loyalty:expire-points           # daily @ 02:00 — expires loyalty points
php artisan commerce:check-abandoned-carts  # hourly — detects abandoned carts
php artisan analytics:generate-snapshot     # daily @ 01:00 — generates an analytics snapshot
php artisan cache:warm                      # daily @ 00:00 — warms the cache
php artisan subscription:process-due        # daily @ 00:00 — processes due subscriptions
php artisan schedule:list                   # confirms all are registered
```

## Manually testing an MCP capability with curl

You first need a real Agent token (see `packages/opencommerce-sdk/README.md`'s Quick Start section for how to mint one). Then:

```bash
curl -X POST http://127.0.0.1:8000/mcp/v1/execute \
  -H "Authorization: Bearer <agent-token>" \
  -H "Content-Type: application/json" \
  -d '{"capability":"demo.tools.echo","input":{"message":"hello"}}' -i
```

For v2, just swap `v1` for `v2` — the response shape changes (`result`/`metadata` instead of `data`/`meta`), but the behavior is identical.

## Performance tools (optional)

```powershell
php artisan performance:benchmark           # timing: product search + KPI calculation
php artisan performance:check-lazy-loading  # checks for likely N+1 query patterns
```

And `/dashboard/performance` (after logging into the Admin Dashboard) shows real platform performance stats.

## Quick summary checklist

```
✅ composer install (+ inside packages/opencommerce-sdk)
✅ npm install && npm run build
✅ php artisan migrate && php artisan db:seed
✅ php artisan storage:link
✅ php artisan test  (should show 1,102 passing tests)
✅ php artisan serve --port=8000
✅ php artisan db:seed --class=DemoShowcaseSeeder
✅ open http://127.0.0.1:8000/showcase
```

The final file takes an honest look at the project's known technical debt and the suggested path forward.

---
← [Architecture Patterns and Gotchas](17-architecture-patterns-and-gotchas.md) | Next: [Technical Debt and Roadmap](19-technical-debt-and-roadmap.md) →
