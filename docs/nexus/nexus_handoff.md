# Nexus — Handoff Log

سند لاگ اجرایی Nexus. هر بار که دستوری اجرا می‌شود، یک ورودی جدید به انتهای این فایل اضافه می‌شود (append-only) — خلاصه کاری که انجام شد، فایل‌های اصلی تغییر یافته، و کامیت مرتبط.

مرجع کامل نقشه راه: [`docs/nexus-roadmap.md`](../nexus-roadmap.md).

---

## Phase 0 — Foundation (تکمیل شده، قبل از این لاگ)

`app/Domains/Nexus/*` skeleton (10 domains × 5 layers)، `NexusServiceProvider`، `config/nexus/platform.php`، `routes/nexus/{web,api,mcp}.php`، Jarvis design system (`resources/css/nexus.css` + `x-nexus-panel`/`x-agent-pulse`/`x-data-stream`)، غیرفعال‌سازی 8 provider تجاری قدیمی (Commerce/CRM/Finance/Workflows/Loyalty/Reporting/Analytics/Shipping) در `bootstrap/providers.php`، `LICENSE-NEXUS.md`. تست پایه: 873 pass / 283 fail (شکست‌ها همگی از ماژول‌های غیرفعال‌شده، مورد انتظار).

کامیت‌ها: `docs(nexus): add Nexus AI-agent context, supporting specs, and roadmap` و `feat(nexus): Phase 0 foundation — domain skeleton, config, routes, Jarvis design system`.

---

## Phase 1 / M1 — Business Domain

**دستور:** «فایل docs/nexus-roadmap.md رو کامل بخون، فاز 1 رو روی زیرساخت موجود پیاده‌سازی کن، هر مرحله گیت کامیت کن، و این فایل handoff رو بساز.»

**تصمیمات معماری کلیدی (قبل از کدنویسی، طی دو Explore agent موازی روی Core):**
- ورود کاربر صاحب‌کسب‌وکار به پنل، از سیستم `User`/`UserRole` هسته استفاده نمی‌کند (آن انتیتی صراحتاً platform-level و فقط برای اپراتورهای Dashboard است — افزودن نقش سوم به آن، Core را به دامنه‌های تجاری وابسته می‌کرد که هر دو CLAUDE.md آن را ممنوع کرده‌اند). یک گارد/جدول احراز هویت جدید و مستقل (`business_owners`) در M2 ساخته می‌شود.
- `RegisterBusinessAction` اولین جایی است که Action‌های مستقل و تا امروز بی‌ارتباط هسته (`CreateTenantAction`، `CreateOrganizationAction`) را زنجیر می‌کند — دقیقاً معنای «Extend, Don't Rebuild».
- عمداً از `AddMemberToOrganizationAction` استفاده نشد: `MemberType` هسته فقط `User|Agent` واقعی (رکورد در جدول `users`/`agents`) را می‌پذیرد؛ صاحب کسب‌وکار در Phase 1 چنین رکوردی ندارد، پس ثبتش با این Action داده را گمراه‌کننده می‌کرد.
- فیلدهای دوزبانه: قرارداد جدید `{field}_fa` / `{field}_en` (هیچ الگوی از قبل موجودی برای محتوای دوزبانه انتیتی‌ها در پروژه نبود — فقط `t()`/`lang/*.json` برای متن UI).
- ارتباط Business → Agent (ساخت خودکار Agent بعد از تأیید) به‌صورت Event-driven طراحی شد: `VerifyBusinessAction` رویداد `BusinessWasVerified` را dispatch می‌کند؛ در M3 دامنه Agent به آن listen می‌کند (نه فراخوانی مستقیم بین دامنه‌ها — طبق قانون Inter-Module Communication در `docs/modules.md`).

**فایل‌های اصلی:** `app/Domains/Nexus/Business/{Domain,Application,Infrastructure}/**`، `database/migrations/nexus/..._create_businesses_table.php`، `tests/{Unit,Feature}/Nexus/Business/**`، binding در `NexusServiceProvider`.

**تست:** 11 تست جدید (5 Unit روی Entity، 6 Feature روی Actions با DB واقعی) — همه پاس.

**کامیت:** `feat(nexus): add Business domain (register/verify/update)`.

---

## Phase 1 / M2 — Business Auth (ثبت‌نام + ورود پرتال کسب‌وکار)

**تصمیم کلیدی:** گارد احراز هویت کاملاً مستقل و جدید (`business`) در `config/auth.php` + جدول `business_owners` — هیچ ارتباطی با گارد `web`/جدول `users` هسته ندارد (طبق تصمیم معماری M1). میدل‌ورهای `EnsureBusinessOwnerIsAuthenticated`/`RedirectIfBusinessOwnerIsAuthenticated` دقیقاً معادل `Authenticate`/`RedirectIfAuthenticated` هسته‌اند، یک گارد پایین‌تر.

**فایل‌های اصلی:**
- `database/migrations/nexus/..._create_business_owners_table.php`
- `app/Domains/Nexus/Business/Infrastructure/Models/BusinessOwner.php`
- `app/Http/Middleware/{EnsureBusinessOwnerIsAuthenticated,RedirectIfBusinessOwnerIsAuthenticated}.php` + alias در `bootstrap/app.php` (`business.auth`/`business.guest`)
- کنترلرها: `RegisterBusinessController`، `BusinessLoginController`، `BusinessLogoutController`، `BusinessDashboardController` (پلیس‌هولدر — M6 کامل می‌شود)
- ویوها: `resources/views/nexus/business/{register,login,dashboard}.blade.php` (دوزبانه، Jarvis تم)
- کلیدهای ترجمه جدید: `lang/{fa,en}/messages.json` → `nexus.business.*` (فرم‌ها + ۲۰+ صنعت + نوع کسب‌وکار)
- روت‌ها: `routes/nexus/web.php` → `nexus.business.{register,register.store,login,login.store,logout,dashboard}`

**تست:** 9 تست Feature جدید (ثبت‌نام موفق + آپلود لوگو، ایمیل تکراری، ریدایرکت وقتی لاگین است، لاگین موفق/ناموفق، دسترسی بدون لاگین، logout) — همه پاس. کل سوییت: 891 pass / 283 fail (بدون رگرشن — دقیقاً baseline قبلی + 18 تست جدید Nexus).

**کامیت:** `feat(nexus): add Business auth guard and registration/login portal`.

---

## Phase 1 / M3 — Agent Domain

**تصمیم کلیدی:** `Agent` جدید Nexus (شخصیت/لحن/محدودیت اختیار/استراتژی) کاملاً از `Agent` هسته (فقط identity + bearer token، صفر فیلد رفتاری) و از `AgentProfile` ماژول AgentOrchestrator (جدول keyword→capability استاتیک بر اساس `config/agents/`) جداست — هیچ‌کدام این فیلدها را ندارند یا نباید داشته باشند. `CreateAgentForBusinessAction` هم ردیف Nexus را می‌سازد و هم با `RegisterAgentAction`/`GenerateAgentTokenAction` موجود هسته، یک Agent+Token واقعی Core می‌سازد (`core_agent_id` ذخیره می‌شود) — یعنی Agent از روز اول اعتبارنامه واقعی MCP دارد، بدون ساخت یک مکانیزم auth دوم.

**ارتباط Business → Agent کاملاً Event-driven:** `CreateAgentOnBusinessVerifiedListener` به `BusinessWasVerified` (از M1) گوش می‌دهد و tenantId/organizationId/نام‌ها را مستقیماً از خود Entity داخل رویداد می‌خواند — دامنه Agent هیچ وابستگی مستقیمی به `BusinessRepositoryInterface` ندارد (طبق قانون Inter-Module Communication در `docs/modules.md`).

**فایل‌های اصلی:** `app/Domains/Nexus/Agent/{Domain,Application,Infrastructure}/**`، `database/migrations/nexus/..._create_nexus_agents_table.php`، listener ثبت‌شده در `NexusServiceProvider::boot()`، `tests/{Unit,Feature}/Nexus/Agent/**`.

**تست:** 10 تست جدید (5 Unit روی Entity، 5 Feature شامل تست یکپارچه‌ی واقعی «تأیید Business ⇒ ساخت خودکار Agent با Core Agent/Token واقعی») — همه پاس. کل تست‌های Nexus: 28 پاس.

**کامیت:** `feat(nexus): add Agent domain with auto-creation on business verification`.

---

## Phase 1 / M4 — Catalog Domain (Product/Service)

**تصمیم‌های کلیدی:**
- `Money` VO مخصوص Nexus (کپی مستقل، دقیقاً مثل الگوی موجود در `app/Modules/{Commerce,Analytics,Finance,Shipping}` که هرکدام نسخه خودشان را دارند — یک Money مشترک خودش یک وابستگی مستقیم بین ماژول‌ها می‌شد). ستون‌های `price_amount`/`price_currency` دقیقاً همان قرارداد smallest-currency-unit جدول `products` هسته (Commerce) را دنبال می‌کنند.
- roadmap یک اکشن به اسم «UpdateCatalog» نام می‌برد؛ چون Product و Service شکل فیلد متفاوت دارند (stock_quantity در برابر duration_minutes) و قرارداد کل پروژه «هر Action = یک نوع Entity» است، به‌جایش `UpdateProductAction`/`UpdateServiceAction` پیاده‌سازی شد — این انحراف از نام دقیق roadmap عمداً و مستند است. `SearchCatalogAction` همان‌طور که اسمش می‌گوید واقعاً روی هر دو نوع باهم کار می‌کند.
- Variants محصول (که roadmap اشاره می‌کند) در Phase 1 پیاده نشد — هیچ‌کدام از ۴ اکشن نام‌برده‌شده به آن نیاز ندارد؛ ستون `attributes` (JSON) جای نگهداری داده‌های سفارشی صنعت/محصول تا وقتی زیرسیستم واقعی variant لازم شود.

**فایل‌های اصلی:** `app/Domains/Nexus/Catalog/{Domain,Application,Infrastructure}/**` (`Product`, `Service`, `Money`)، دو migration (`nexus_products`, `nexus_services`)، `tests/{Unit,Feature}/Nexus/Catalog/**`.

**تست:** 15 تست جدید (8 Unit: Money + Product + Service، 7 Feature: Add/Update × 2 نوع + Search با و بدون query + ایزوله‌بودن بین دو Business) — همه پاس. کل تست‌های Nexus: 43 پاس.

**کامیت:** `feat(nexus): add Catalog domain (products/services)`.

---

## Phase 1 / M5 — هماهنگ‌سازی دیزاین‌سیستم Jarvis با docs/claude/ui-system-design.md

بین Phase 0 و شروع Phase 1، فایل `docs/claude/ui-system-design.md` (سند رسمی‌تر و کامل‌تر دیزاین‌سیستم) به پروژه اضافه شد که با نسخه ساده Phase 0 اختلاف داشت (نام توکن‌ها، مقدار پس‌زمینه، فونت‌ها، وضعیت‌های `x-agent-pulse`). این مرحله `resources/css/nexus.css` و کامپوننت‌ها را دقیقاً با همان سند هماهنگ کرد:

- توکن‌ها بازنویسی شدند: `--color-nexus-dark` (`#0A0E27`، جایگزین `nexus-bg` قبلی)، `--color-nexus-glass`، `--color-nexus-text`/`text-muted`، `--color-nexus-surface-1`/`2`، `--color-nexus-success`/`warning`/`error`، `--radius-sm/md/lg` (۸/۱۴/۲۰px).
- فونت: `Space Grotesk` (هدینگ) + `JetBrains Mono` (داده/کد) جایگزین Instrument Sans — فقط به‌عنوان fallback stack در CSS، بدون `<link>` به Google Fonts (دقیقاً همان الگوی Phase 0: بدون وابستگی شبکه جدید).
- کی‌فریم‌های `pulse-glow`/`data-stream`/`breathe`/`draw-line` + گارد `prefers-reduced-motion`.
- `x-nexus-panel`: props جدید `glow` (cyan/purple/none) و `corner` (round/cut، با `clip-path` واقعی) و `interactive` اضافه شد؛ `title` prop قبلی حفظ شد. `x-agent-pulse`: واژگان وضعیت به ۵ حالت سند (`idle|thinking|active|warning|error`) تغییر کرد. دو کامپوننت جدید ساخته شد: `x-status-badge` و `x-metric-card`. `x-negotiation-line`/`x-command-palette` عمداً ساخته نشدند — چیزی در Phase 1 از آن‌ها استفاده نمی‌کند.
- همه ویوهای موجود (`welcome`, `layouts/app`, `business/{register,login,dashboard}`) به توکن‌های جدید سوییچ شدند.

**تأیید:** `npm run build` تمیز، ۴۳ تست Nexus بدون تغییر پاس، بازدید دستی از `/nexus`, `/nexus/business/register`, `/nexus/business/login` (۲۰۰ + کلاس‌های جدید در HTML رندرشده + بررسی CSS کامپایل‌شده برای وجود توکن‌ها/کی‌فریم‌ها).

**کامیت:** `feat(nexus): reconcile Jarvis design system with docs/claude/ui-system-design.md`.

---

## Phase 1 / M6 — Business Dashboard واقعی

**تصمیم کلیدی:** `GetBusinessDashboardAction` (یک read-model محض، بدون هیچ mutation) در دامنه جدید `Analytics` نکسوس ساخته شد — دقیقاً همان نقشی که `GetDashboardStatsAction` ماژول Analytics پایه برای `DashboardController` هسته بازی می‌کند (Controller → یک Action متمرکز، نه چند repository call پراکنده). این Action به‌طور آگاهانه از سه دامنه (Business/Agent/Catalog) می‌خواند — چون فقط خواندن است و هیچ نوشتنی رخ نمی‌دهد، ناقض قانون Inter-Module Communication نیست (آن قانون درباره‌ی وابستگی سمت نوشتن/منطق تجاری است، نه read model نمایشی).

موجودی کردیت و مذاکرات فعال چون هنوز دامنه‌های Credit/Negotiation (Phase 2/3) وجود ندارند، به‌صورت صادقانه `null` برمی‌گردند و در ویو به‌صورت «—» نمایش داده می‌شوند — نه عدد ساختگی صفر.

**فایل‌های اصلی:** `app/Domains/Nexus/Analytics/Application/Actions/GetBusinessDashboardAction.php`، `BusinessDashboardController` کامل شد، `resources/views/nexus/business/dashboard.blade.php` بازنویسی کامل (وضعیت Agent با `x-agent-pulse`، شمارش کاتالوگ و پلیس‌هولدرهای کردیت/مذاکره با `x-metric-card`، برچسب تأیید با `x-status-badge`).

**تست End-to-End دستی (نه فقط PHPUnit):** ثبت‌نام واقعی → داشبورد «Agent هنوز ساخته نشده» + شمارش صفر → تأیید ادمین → داشبورد «Test Company» + وضعیت Verified + Agent واقعی با Core Agent/Token واقعی → افزودن محصول → شمارش کاتالوگ آپدیت شد به ۱. حین این تست یک باگ واقعی پیدا و رفع شد: دیتابیس dev (`database/database.sqlite`) از قبل از M1 migrate نشده بود؛ `php artisan migrate` اجرا شد.

**تست خودکار:** ۵ تست جدید (۳ Feature روی `GetBusinessDashboardAction`، ۲ Feature روی `BusinessDashboardController` قبل/بعد از تأیید) — همه پاس. کل تست‌های Nexus: ۴۸ پاس.

**کامیت:** `feat(nexus): build real Business Dashboard (Agent status, catalog counts)`.

---

## ⚠️ حادثه ریموت گیت (بین M6 و M7)

کاربر متوجه شد که تا این لحظه هیچ کامیتی push نشده بود، و ریپازیتوری واقعی مقصد را اعلام کرد: **https://github.com/opencommerceir/Agent2Agent**. بررسی نشان داد:
- هیچ push اشتباهی به جای دیگر انجام نشده بود — تمام ۸ کامیت (docs + Phase 0 + M1 تا M6) فقط لوکال روی `main` بودند.
- تنها remote موجود `upstream` بود (تغییرنام‌یافته از `origin` در Phase 0 دقیقاً برای جلوگیری از push اشتباهی به opencommerce-platform) — هیچ push‌ای به آن هم نخورده بود.
- ریپازیتوری Agent2Agent وجود داشت ولی کاملاً خالی بود (چون آدرسش تا این لحظه به من داده نشده بود).

**رفع شد:** ریموت `origin` روی `https://github.com/opencommerceir/Agent2Agent.git` اضافه شد و هر ۸ کامیت با `git push -u origin main` push شدند. از این به بعد هر کامیت جدید (شروع از M7) به‌صورت خودکار به همین ریپازیتوری push می‌شود.

---

## Phase 1 / M7 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: همه migration های جدید (`businesses`, `business_owners`, `nexus_agents`, `nexus_products`, `nexus_services`) تمیز اجرا شدند.
- `php artisan test` کامل: **921 pass / 283 fail** — بدون رگرشن (baseline قبل از Phase 1: 873 pass / 283 fail؛ ۴۸ تست جدید Nexus همگی pass).
- تست End-to-End دستی دوم (روی دیتابیس تازه migrate‌شده، سناریوی کامل و تمیز): ثبت‌نام کسب‌وکار «Sara Store» → داشبورد pending با Agent نساخته → تأیید ادمین → افزودن ۱ محصول + ۱ خدمت → داشبورد: نام صحیح، وضعیت Verified، شمارش کاتالوگ ۱/۱ — همه درست.
- `git log --oneline` روی `origin/main`: ۸ کامیت (این را M7 به ۹ می‌رساند).

**کامیت:** `docs(nexus): Phase 1 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 1 (Business & Agent Core) — تکمیل شد

| دامنه | Entity ها | Action ها | تست |
|---|---|---|---|
| Business | `Business` | Register, Verify, UpdateProfile | ۱۱ |
| Business Auth | `BusinessOwner` | (guard جدید `business`) | ۹ |
| Agent | `Agent` (Nexus) | CreateForBusiness, UpdatePersonality, SetAuthorityLimits | ۱۰ |
| Catalog | `Product`, `Service`, `Money` | AddProduct, AddService, UpdateProduct, UpdateService, SearchCatalog | ۱۵ |
| Analytics (جزئی) | — | GetBusinessDashboard | ۳ |
| Dashboard | — | (کنترلر) | ۲ |
| **مجموع** | | | **۴۸ (پاس)** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. احراز هویت هر نقش جدید (Business owner) باید گارد/جدول مستقل خودش را بگیرد، نه توسعه `UserRole` هسته.
2. ارتباط بین دامنه‌های Nexus باید Event-driven باشد مگر برای read model های محض (مثل داشبورد) که خواندن مستقیم از چند repository قابل قبول است.
3. فیلدهای دوزبانه = ستون‌های `{field}_fa`/`{field}_en`.
4. هر دامنه Money/VOهای خودش را می‌سازد، مشترک نمی‌شود.
5. وقتی نام یک Action در roadmap با قرارداد «هر Action یک نوع Entity» تناقض دارد، انحراف مستند و توضیح داده می‌شود (نمونه: UpdateCatalog → UpdateProduct/UpdateService).

**آماده برای Phase 2 (Negotiation Engine)** طبق `docs/nexus-roadmap.md`.

---

# Phase 2 — Negotiation Engine

**دستور:** «برو فاز بعدی» (بعد از تأیید Phase 1). قبل از کدنویسی، دو Explore agent موازی روی الگوهای موجود (ثبت MCP capability سرتاسری، state machine، PDF، چت Showcase، Money VO) اجرا شد و پلن کامل تأیید شد — جزئیات در `docs/nexus-roadmap.md` بخش Phase 2.

## Phase 2 / M1 — دامنه Marketplace

**تصمیم کلیدی:** Marketplace هیچ جدول خودش ندارد — یک read model صرف روی `businesses` + `nexus_products`/`nexus_services` است، دقیقاً مثل الگوی `Infrastructure/Queries/*` ماژول Reporting در پلتفرم پایه (به‌جای اینکه `BusinessRepositoryInterface`/`ProductRepositoryInterface` را با متدهای مخصوص marketplace شلوغ کنیم).

**فایل‌های اصلی:** `app/Domains/Nexus/Marketplace/Infrastructure/Queries/BusinessSearchQuery.php` (کوئری متمرکز)، `Application/Actions/{SearchMarketplaceAction,GetRecommendationsAction,RankSuppliersAction}.php`.

- `SearchMarketplaceAction`: فقط businessهای verified، به‌جز خود caller؛ جستجو با نام کسب‌وکار یا نام محصول/خدمت؛ فیلتر صنعت اختیاری.
- `GetRecommendationsAction`: businessهای verified همان صنعت (ساده‌ترین معیار صادقانه قبل از وجود Reputation در Phase 6).
- `RankSuppliersAction`: رتبه‌بندی بر اساس تعداد آیتم کاتالوگ (تنها سیگنال واقعی موجود، نه یک امتیاز جعلی).

**تست:** ۶ تست Feature جدید — همه پاس.

**کامیت:** `feat(nexus): add Marketplace domain (search/recommendations/rank suppliers)`.

---

## Phase 2 / M2 — اولین MCP Capability واقعی Nexus (`nexus.marketplace.search`)

اولین اثبات end-to-end که کل زنجیره MCP برای Nexus کار می‌کند: manifest → Seeder ایدمپوتنت (`NexusMarketplaceCapabilitiesSeeder`، اضافه‌شده به `DatabaseSeeder`) → `CapabilityHandlerRegistry::register()` در `NexusServiceProvider::boot()` → `SearchMarketplaceAction`.

**مسئله جدید:** هندلر MCP فقط `AuthContext->agentId` (شناسه Core Agent) را دارد، نه اینکه کدام Business Nexus در حال تماس است. راه‌حل: متد جدید `AgentRepositoryInterface::findByCoreAgentId()` + یک Action مشترک `App\Domains\Nexus\Agent\Application\Actions\ResolveActingBusinessAction` که هر capability جدید Nexus (از جمله Negotiation در M4) از آن استفاده می‌کند — به‌جای تکرار همین منطق در هر closure.

**تصمیم:** `nexus.marketplace.search` یک permission واقعی (`nexus.marketplace.read`) می‌خواهد، نه لیست خالی — با اینکه جستجو ریسک پایینی دارد، برای سازگاری با قرارداد موجود پلتفرم (هیچ capability موجودی با `requiredPermissions` خالی ثبت نشده) همین الگو حفظ شد.

**تست:** ۲ تست Feature جدید — یکی با توکن Bearer واقعی یک Agent واقعی (از مسیر رویداد-محور M3 فاز ۱ ساخته شده، نه mock) کل چرخه `/mcp/v1/execute` را طی می‌کند و نتیجه cross-tenant واقعی می‌گیرد؛ دیگری بدون permission رد می‌شود (403). کل تست‌های Marketplace: ۸ پاس.

**کامیت:** `feat(nexus): wire nexus.marketplace.search as a real MCP capability`.

---
