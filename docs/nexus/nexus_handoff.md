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

## Phase 2 / M3 — هسته دامنه Negotiation

**اولین aggregate واقعاً cross-tenant پروژه.** همه repositoryهای موجود (Commerce, Finance, Agent, Catalog) دقیقاً با یک `tenant_id` scope می‌شوند. یک معامله بین Agent دو Business متفاوت ذاتاً بین دو Tenant مشترک است — پس `negotiations` هر دو طرف را صریح ذخیره می‌کند (`initiator_business_id`+`initiator_tenant_id`, `counterparty_business_id`+`counterparty_tenant_id`) و `NegotiationRepositoryInterface::findVisibleTo()` بر اساس «آیا این Business یکی از دو طرف است؟» اجازه دسترسی می‌دهد، نه یک `WHERE tenant_id = ?`.

**State machine:** دقیقاً الگوی `Subscription` (ماژول Commerce) — نگاشت صریح `ALLOWED_TRANSITIONS` + یک `transitionTo()` نگهبان که همه متدهای عمومی از آن عبور می‌کنند. `accept()`/`reject()` از هر سه حالت Proposed/Countered/PendingApproval قابل فراخوانی‌اند (همان الگوی چند-حالت-مبدأ `Subscription::cancelImmediately()`). `Expired` مدل‌سازی شده ولی هیچ متدی این فاز به آن transition نمی‌کند — دقیقاً همان gap مستندشده‌ی `Subscription::Expired` خودش (نه سهل‌انگاری، یک precedent مستقیم در همین کدبیس).

**تصحیح نسبت به پلن اولیه:** پلن گفته بود «`counter()`/`accept()` هر دو باید authority_limits را چک کنند» — در پیاده‌سازی این را محدود به فقط `accept()` کردم، چون یک پیشنهاد متقابل (`counter`) هیچ تعهدی ایجاد نمی‌کند، فقط قبول‌کردن (`accept`) یک معامله را نهایی می‌کند؛ و آستانه، محدودیت اختیار **خودِ Agent قبول‌کننده** است (نه طرف مقابل) — یعنی «آیا من مجازم خودم را متعهد به این معامله کنم؟»، نه قضاوت درباره طرف مقابل.

**فایل‌های اصلی:** `app/Domains/Nexus/Negotiation/{Domain,Application,Infrastructure}/**`، `database/migrations/nexus/..._create_negotiations_table.php`، `..._create_negotiation_messages_table.php`.

**تست:** ۲۶ تست جدید (۱۷ Unit روی state machine + VOها، ۹ Feature روی ۴ Action با داده واقعی cross-tenant) — همه پاس. یک باگ واقعی در حین نوشتن تست پیدا و رفع شد: `Event::fake()` بدون آرگومان در ابتدای تست، لیسنر واقعی `BusinessWasVerified` (که Agent را می‌سازد) را هم خاموش می‌کرد؛ راه‌حل: `Event::fake([NegotiationWasAccepted::class])` به‌جای fake کلی.

**کامیت:** `feat(nexus): add Negotiation domain core (state machine, terms, propose/counter/accept/reject)`.

---

## Phase 2 / M4 — MCP Capabilities مذاکره + تأیید انسانی

پنج capability جدید (`nexus.negotiation.{propose,counter,accept,reject,status}`) با همان الگوی manifest→Seeder→handler M2. هر پنج هندلر از `ResolveActingBusinessAction` (ساخته‌شده در M2) برای فهمیدن «کدام Business Nexus در حال تماس است» استفاده می‌کنند — دقیقاً همان سرمایه‌گذاری مشترک که برایش ساخته شد.

**باگ واقعی پیدا و رفع شد در حین طراحی:** ابتدا خواستم `AcceptDealAction` را برای «تأیید انسانی یک مذاکرهٔ Pending» دوباره استفاده کنم، ولی چون آن Action دوباره authority_limits را چک می‌کند، در حالت Pending دوباره سعی می‌کرد `requestApproval()` بزند — که طبق جدول `ALLOWED_TRANSITIONS` از حالت `pending_approval` به خودش مجاز نیست و Exception می‌داد. راه‌حل: دو Action مجزا و ساده — `ApprovePendingNegotiationAction`/`RejectPendingNegotiationAction` — که مستقیم `accept()`/`reject()` را صدا می‌زنند، بدون چک مجدد آستانه (چون انسان از قبل تصمیم گرفته).

**محدودیت شناخته‌شده (مستند):** در حال حاضر هر یک از دو طرف مذاکره می‌تواند یک Pending Approval را تأیید/رد کند، نه فقط طرفی که آستانه‌اش رد شده — چون Negotiation entity هنوز رکورد نمی‌کند کدام طرف باعث Pending شدن شده. تنگ‌کردن این محدوده، کار فاز بعدی است، نه سهل‌انگاری این فاز.

**تست:** ۶ تست Feature جدید — یک سناریوی کامل propose→counter→accept→status با دو توکن Bearer واقعی روی `/mcp/v1/execute`، سناریوی reject، سناریوی عبور از آستانه اختیار (→ pending_approval)، و ۳ تست روی Approve/Reject انسانی. یک باگ تست پیدا شد (پیشنهاد به خودِ همان کسب‌وکار به‌جای طرف مقابل) و رفع شد. کل تست‌های Negotiation: ۳۲ پاس (۱۷ Unit + ۱۵ Feature).

**کامیت:** `feat(nexus): wire nexus.negotiation.* MCP capabilities + human approval actions`.

---

## Phase 2 / M5 — سرویس Reasoning مذاکره

`NegotiationReasoningService` — قطعی و rule-based، بدون هیچ فراخوانی LLM، مطابق پیش‌فرض «Rule Engine 80%، بدون هزینه» در `docs/claude/llm-strategy.md`. چهار متد به شکل هر `NegotiationMessageType` (`forProposal`/`forCounter`/`forAccept`/`forReject`) که هرکدام `array{thoughts, confidence, decision, explanation}` برمی‌گردانند — همان shape که `docs/nexus-roadmap.md` برای think()/reflect() خواسته بود، ولی interface موجود AgentOrchestrator (`ReasoningEngineInterface`) عمداً استفاده نشد چون به `Goal`/`ExecutionResult`/۴ نوع ثابت Agent گره خورده و قابل انتقال نبود (طبق تحقیق قبل از Phase 2).

قوانین واقعی، نه placeholder: `forCounter` درصد تغییر قیمت نسبت به پیشنهاد قبلی را حساب می‌کند و confidence را بر اساس نزدیکی دو پیشنهاد تنظیم می‌کند؛ نزدیک به سقف دور مذاکره یک هشدار اضافه می‌کند؛ `forAccept` بین «قبول مستقیم» و «نیاز به تأیید انسان» بر اساس همان چک authority_limits که M3/M4 ساختند تمایز می‌گذارد.

هر ۴ Action مذاکره (M3/M4) به‌روزرسانی شدند تا `reasoning` را قبل از ذخیره هر `NegotiationMessage` محاسبه کنند.

**تست:** ۷ تست Unit جدید روی خودِ سرویس reasoning (framework-free). کل تست‌های Negotiation: ۳۹ پاس (۲۴ Unit + ۱۵ Feature).

**کامیت:** `feat(nexus): add deterministic Negotiation reasoning (think/reflect-shaped traces)`.

---

## Phase 2 / M6 — دامنه Contract (PDF + امضای هش)

`GenerateContractOnNegotiationAcceptedListener` روی `NegotiationWasAccepted` گوش می‌دهد (نه فراخوانی مستقیم از Negotiation → Contract) و `GenerateContractAction` را صدا می‌زند: یک snapshot ساختاریافته و بدون‌زبان از معامله (نام‌های دوزبانه دو کسب‌وکار، قیمت، تعداد) می‌سازد، هش می‌کند (`hash('sha256', json_encode($terms))` — دقیقاً همان الگوی `AgentToken::hash()`، تنها precedent واقعی «امضای دیجیتال» در کدبیس)، و با `Pdf::loadView()` (اولین استفاده از این متد در کل پروژه — قبلاً فقط `Pdf::loadHTML()` روی رشتهٔ دستی استفاده شده بود) یک PDF واقعی می‌سازد.

**تصمیم:** قرارداد یک PDF دوزبانه است (هر دو بخش فارسی و انگلیسی در یک فایل)، نه دو فایل جدا — همان شکل رایج قراردادهای تجارت بین‌المللی. چون این تولید داخل یک MCP request بدون middleware `web` اجرا می‌شود، از `t()`/`dashboard_language()` (که به session نیاز دارند) استفاده نشد — برچسب‌های دو زبان مستقیم در Blade نوشته شده‌اند.

**تأیید واقعی، نه فرضی:** طبق پلن، ریسک اصلی این مرحله (آیا dompdf واقعاً فارسی/RTL را درست رندر می‌کند؟) با تولید دستی یک PDF واقعی و بازکردن آن بررسی شد — متن فارسی با شکل صحیح حروف (RTL, حروف چسبیده) درست نمایش داده شد، نه جعبه‌های خالی یا کاراکترهای درهم. حجم فایل (~۸۸۰KB) تأیید می‌کند فونت DejaVu Sans کامل (که گلیف‌های فارسی/عربی دارد) embed شده است.

**فایل‌های اصلی:** `app/Domains/Nexus/Contract/{Domain,Application,Infrastructure}/**`، `database/migrations/nexus/..._create_contracts_table.php`، `resources/views/nexus/contracts/pdf.blade.php`.

**تست:** ۲ تست Feature جدید — یکی مسیر کامل (Accept → event → Contract واقعی + PDF واقعی در Storage با بایت‌های `%PDF-` معتبر)، دیگری تأیید می‌کند Reject هیچ Contract ای نمی‌سازد.

**کامیت:** `feat(nexus): add Contract domain (auto-generate bilingual PDF + hash signature on acceptance)`.

---

## Phase 2 / M7 — Live Negotiation Viewer

صفحه‌ای که صاحب کسب‌وکار می‌تواند مذاکرهٔ زندهٔ Agent خودش را ببیند و معاملات بالای سقف اختیار را تأیید/رد کند. چون هیچ زیرساخت WebSocket/broadcast در پروژه وجود ندارد (`BROADCAST_CONNECTION=log`، بدون `config/broadcasting.php`)، صفحه هر ۳ ثانیه با `setInterval` + `fetch` روی یک endpoint سبک JSON (`GET /nexus/negotiations/{id}/messages?after={id}`) poll می‌کند — بدون اختراع زیرساخت real-time جدید.

سه Action جدید و کوچک برای پشتیبانی از viewer: `ListMyNegotiationsAction` (صفحهٔ فهرست، نام دوزبانه طرف مقابل را هم resolve می‌کند)، `ListNegotiationMessagesAction` (بار اول صفحه)، `PollNegotiationMessagesAction` (فقط پیام‌های بعد از یک id — endpoint polling). هرکدام به‌طور مستقل عضویت caller را چک می‌کنند (نه اعتماد به چک قبلی کنترلر) — همان الگوی self-contained authorization که همهٔ Actionهای این پروژه دارند.

**فایل‌های اصلی:** `NegotiationViewerController` (Negotiation/Interfaces/Http)، `resources/views/nexus/negotiations/{index,show}.blade.php`، مسیرهای جدید زیر گروه `business.auth:business` موجود در `routes/nexus/web.php`.

**تست:** ۵ تست Feature جدید — نمایش صفحه برای یک طرف واقعی، endpoint polling (فقط پیام‌های جدید برمی‌گرداند)، Approve واقعی (که Contract واقعی هم می‌سازد، چون مسیر رویدادی M6 دست نمی‌خورد)، Reject، و فهرست مذاکرات.

**کامیت:** `feat(nexus): add Live Negotiation Viewer (polling UI, human approve/reject)`.

---

## Phase 2 / M8 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس تازه: همهٔ ۳ migration جدید (`negotiations`, `negotiation_messages`, `contracts`) تمیز اجرا شدند.
- `php artisan test` کامل: **۹۷۵ pass / ۲۸۳ fail** — بدون رگرشن (baseline قبل از Phase 2: ۹۲۱ pass؛ ۵۴ تست جدید Nexus همگی pass: Marketplace ۸، Negotiation ۴۱، Contract ۲، Viewer ۵... مجموع دقیق‌تر: از ۹۲۱ به ۹۷۵ یعنی ۵۴ تست جدید).
- تست End-to-End دستی کامل (نه فقط PHPUnit) — یک نکتهٔ محیطی واقعی پیدا و دور زده شد: بازهٔ پورت ۸۰۹۰–۸۱۸۹ توسط ویندوز (احتمالاً Hyper-V/WSL) رزرو شده بود (`netsh interface ipv4 show excludedportrange`) و `php artisan serve` را می‌شکست؛ با پورت ۹۵۰۰ حل شد. سناریوی کامل روی سرور واقعی:
  1. ثبت‌نام و تأیید دو کسب‌وکار واقعی (Buyer/Seller)، هرکدام Agent واقعی با Core Token گرفتند.
  2. `nexus.marketplace.search` (با Bearer واقعی) کسب‌وکار دیگر را پیدا کرد.
  3. `nexus.negotiation.propose` → `nexus.negotiation.accept` روی مبلغی بالاتر از `authority_limits.max_deal_value` خریدار → وضعیت واقعاً `pending_approval` شد.
  4. صاحب کسب‌وکار خریدار با session واقعی (`/nexus/business/login`) وارد شد، صفحهٔ لیست و نمایش مذاکره را دید (banner تأیید انسانی نمایش داده شد)، و با POST واقعی به `/nexus/negotiations/{id}/approve` تأیید کرد.
  5. وضعیت به `accepted` تغییر کرد و یک Contract واقعی با PDF واقعی (۸۷۸KB، قبلاً از نظر رندر فارسی صحت‌سنجی شده) روی دیسک ساخته شد.
- `git log --oneline origin/main`: هر ۸ مرحلهٔ Phase 2 (M1 تا M8) کامیت و push شده‌اند.

**کامیت:** `docs(nexus): Phase 2 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 2 (Negotiation Engine) — تکمیل شد

| دامنه | Entity/Service اصلی | Action ها | MCP Capability | تست |
|---|---|---|---|---|
| Marketplace | — (read model) | Search, GetRecommendations, RankSuppliers | `nexus.marketplace.search` | ۸ |
| Negotiation | `Negotiation`, `NegotiationMessage` | Initiate, SendCounterOffer, Accept, Reject, Approve/RejectPending, Get, List, Poll | `nexus.negotiation.{propose,counter,accept,reject,status}` | ۴۱ |
| Contract | `Contract` | GenerateContract (event-driven) | — | ۲ |
| Live Viewer | — (کنترلر + polling) | — | — | ۵ |
| **مجموع** | | | | **۵۶ (پاس)** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **اولین aggregate cross-tenant پروژه** — الگوی «هر دو طرف صریح ذخیره می‌شوند + authorization بر اساس عضویت، نه tenant_id واحد» برای هر دامنهٔ آیندهٔ چندطرفه (Marketplace رتبه‌بندی گروهی، Contract چندجانبه در فازهای بعد) قابل استفاده مجدد است.
2. **الگوی کامل MCP Capability** (manifest → Seeder ایدمپوتنت → `CapabilityHandlerRegistry::register()` → Action، با `ResolveActingBusinessAction` برای یافتن Business از AuthContext) اکنون دوبار (Marketplace, Negotiation) اثبات شده — برای Contract/Reputation در فازهای بعد مستقیم کپی می‌شود.
3. **Reasoning قطعی، نه LLM** — تا وقتی که استراتژی LLM واقعی (Phase 4 خود roadmap) پیاده نشده، هر «تفکر Agent» باید rule-based و zero-cost باشد.
4. **امضای دیجیتال = هش ساده** — تا زمانی که نیاز واقعی به PKI/امضای واقعی حقوقی مطرح شود.
5. **Viewer زنده = polling، نه WebSocket** — تا وقتی زیرساخت broadcast واقعی (Reverb/Pusher) اضافه نشده.
6. **محدودیت شناخته‌شده باقی‌مانده:** فقط طرفی که آستانه‌اش رد شده باید بتواند Pending Approval را resolve کند؛ فعلاً هر دو طرف می‌توانند — باید در فاز بعد سخت‌گیرانه‌تر شود.

**آماده برای Phase 3 (Credit & Payment Economy)** طبق `docs/nexus-roadmap.md`.

---

# Phase 3 — Credit & Payment Economy

**دستور:** «برو برای فاز 3 و پیاده‌سازیش» (بعد از تأیید Phase 2). قبل از کدنویسی، سه Explore agent موازی روی الگوهای موجود (درگاه پرداخت Zibal/Stripe، پنل ادمین/hot-reload/audit، پایپ‌لاین MCP + اسکلت فعلی Credit) اجرا شد و پلن کامل ۷ مرحله‌ای (M1–M7) تأیید شد.

## Phase 3 / M1 — هستهٔ دامنهٔ Credit (Ledger)

**یافتهٔ کلیدی پیش از کدنویسی:** هیچ الگوی ledger/wallet/balance در کل کدبیس وجود نداشت (حتی در ماژول‌های غیرفعال‌شدهٔ Finance/Commerce) — `app/Domains/Nexus/Credit/` هنوز دقیقاً همان اسکلت خالی Phase 0 بود. همچنین `CommerceServiceProvider` (و Finance/CRM/...) در `bootstrap/providers.php` کاملاً غیرفعال‌اند؛ کلاس‌هایشان (از جمله `ZibalPaymentGateway`/`StripePaymentGateway`) هنوز autoload می‌شوند ولی هیچ‌کدام bind/boot نمی‌شوند — نکته‌ای که M3 (خرید کردیت) باید صریح رعایت کند.

**تصمیم‌های کلیدی:**
- `CreditBalance` (یک رکورد به‌ازای هر Business، دقیقاً همان الگوی ۱:۱ که `Agent` دارد — بدون ستون `tenant_id`، چون `business_id` خودش کفایت می‌کند، عیناً مثل `nexus_agents`) موجودی را به‌صورت عدد صحیح خام «کردیت» نگه می‌دارد، نه `Money` — کردیت واحد پول واقعی نیست («۱٬۰۰۰ کردیت»، `docs/claude/monetization.md`)؛ سمت پول واقعی فقط در M3 (خرید) با یک `Money` VO مستقل Credit ظاهر می‌شود.
- `CreditTransaction` یک ردیف ledger **غیرقابل‌تغییر** است (بدون `updated_at`، `created_at->useCurrent()`، دقیقاً همان الگوی مستندشدهٔ `workflow_logs`) — نه یک state machine مثل `Negotiation`/`PaymentSession`، چون یک واقعیت ثبت‌شده است نه یک workflow. همین ledger خودش audit trail الزام‌شدهٔ CLAUDE.md برای اقدامات Agent را برآورده می‌کند؛ ساخت یک `AuditLog` عمومی جداگانه scope creep بود (roadmap آن را نخواسته).
- `InsufficientCreditException` رابط `ConflictExceptionInterface` هسته (`app/Core/Domain/Exceptions/Contracts`) را پیاده می‌کند — یعنی بدون هیچ تغییری در `Core`، `MCPExceptionHandler` آن را خودکار به `409 CONFLICT` نگاشت می‌کند (الگوی از پیش موجود، فقط برای اولین بار توسط یک دامنهٔ Nexus استفاده شد).
- تأمین خودکار موجودی: `GrantStartingCreditsOnBusinessVerifiedListener` روی همان رویداد `BusinessWasVerified` (کنار listener موجود Agent) گوش می‌دهد — حتی وقتی `starting_balance` پیکربندی‌شده صفر است، رکورد `CreditBalance` باز می‌شود (نه skip می‌شود)، چون تمام مسیرهای پایین‌دستی (`GetCreditBalanceAction`، هر Action دروازه‌بانی‌شده در M2) به وجود این ردیف متکی‌اند.
- `GetBusinessDashboardAction` (Phase 1/M6) به‌روزرسانی شد: `creditBalance` دیگر همیشه `null` نیست — مستقیماً از `CreditBalanceRepositoryInterface` می‌خواند (نه از طریق `GetCreditBalanceAction` که برای رکورد نبود Exception می‌زند)، چون یک Business دیده‌شده اینجا می‌تواند هنوز تأیید نشده باشد (رکورد Credit هنوز باز نشده) — `null` هنوز یعنی «هنوز فراهم نشده»، نه یک عدد جعلی.

**فایل‌های اصلی:** `app/Domains/Nexus/Credit/{Domain,Application,Infrastructure}/**`، دو migration (`nexus_credit_balances`, `nexus_credit_transactions`)، binding و listener جدید در `NexusServiceProvider`.

**تست:** ۲۱ تست جدید (۱۰ Unit روی `CreditBalance`/`CreditTransaction`، ۱۱ Feature روی ۴ Action + listener تأمین خودکار) — همه پاس. سوییت کامل `--filter=Nexus`: ۱۲۲ پاس (بدون رگرشن؛ یک تست موجود `GetBusinessDashboardActionTest` برای به‌روزرسانی به‌جای `null` انتظار `creditBalance = 0` بعد از تأیید را گرفت).

**کامیت:** `feat(nexus): add Credit domain core (ledger, auto-provisioned balance)`.

---

## Phase 3 / M2 — CostGate روی MCP Capabilityهای موجود

**تصمیم کلیدی:** طبق Decision 007 («Core نباید هیچ منطق تجاری داشته باشد»)، CostGate نمی‌تواند در `AbstractMCPGatewayController`/`CapabilityExecutionService` زندگی کند — دقیقاً همان محدودیتی که چک `authority_limits` در `AcceptDealAction` از قبل رعایت می‌کرد. `SpendCreditsForActionAction` (خودِ «CostGate» roadmap) داخل هر Action گیت‌شده صدا زده می‌شود، نه در پایپ‌لاین مشترک MCP.

**باگ واقعی پیدا و رفع شد پیش از commit:** چون نام Capabilityها خودشان نقطه دارند (`nexus.marketplace.search`)، خواندن مستقیم `config("...action_costs.{$actionKey}")` با dot-notation لاراول اشتباه است — لاراول رشته را از روی نقطه می‌شکند و به‌جای یک کلید تخت، دنبال آرایه‌های تودرتو می‌گردد (که وجود ندارند) و همیشه `null`/۰ برمی‌گرداند، یعنی CostGate بی‌صدا هیچ‌وقت واقعاً چک نمی‌شد. راه‌حل: کل آرایهٔ `action_costs` یک‌بار با `config('nexus.platform.credit.action_costs')` خوانده می‌شود، بعد با اندیس آرایهٔ ساده (نه dot-notation) به کلید نقطه‌دار دسترسی پیدا می‌شود.

**نقطهٔ شارژ در هر Action دقیقاً بعد از موفقیت گذار وضعیت، نه قبلش:** در `SendCounterOfferAction`/`AcceptDealAction`/`RejectDealAction`، چک credit بعد از فراخوانی موفق `counter()`/`accept()`/`reject()` انجام می‌شود (نه قبلش) — یک درخواست نامعتبر (مثل عبور از سقف دور مذاکره) هرگز کردیت کم نمی‌کند. در `InitiateNegotiationAction`/`SearchMarketplaceAction` که هیچ Exception‌ای بعد از اعتبارسنجی رخ نمی‌دهد، چک بعد از تمام اعتبارسنجی‌ها و درست قبل از عملیات اصلی قرار گرفت.

**`contract.generate` روی Business آغازگر مذاکره شارژ می‌شود:** رویداد `NegotiationWasAccepted` فقط خودِ Entity را حمل می‌کند، نه اینکه کدام مسیر (`AcceptDealAction` یک طرف، یا `ApprovePendingNegotiationAction` یک انسان) باعث آن شد — پس `GenerateContractOnNegotiationAcceptedListener` قطعی همیشه `initiatorBusinessId()` را شارژ می‌کند. یک ساده‌سازی مستند، نه سهل‌انگاری (همان سبک محدودیت شناخته‌شدهٔ «هر دو طرف می‌توانند Pending Approval را resolve کنند» در Phase 2/M4).

**Capability جدید:** `nexus.credit.balance` (فقط خواندن، رایگان — چک موجودی خودش هرگز نباید هزینه داشته باشد، وگرنه یک Business با موجودی دقیقاً صفر هیچ‌وقت نمی‌فهمید) با همان الگوی manifest→Seeder→handler.

**رگرسیون تست‌های فازهای قبل:** با فعال شدن هزینه واقعی، تست‌های موجود Marketplace/Negotiation/Contract (که قبلاً هیچ کردیتی نمی‌گرفتند) شکست می‌خوردند؛ هر فایل تست با یک کمک‌متد مشترک (`verifiedBusiness()`/`verifiedBusinessWithOwner()`) یک شارژ سخاوتمندانهٔ ثابت (۱۰۰٬۰۰۰ کردیت) بعد از تأیید هر Business اضافه کرد — تغییری در منطق دامنه، فقط fixture.

**فایل‌های اصلی:** `app/Domains/Nexus/Credit/Application/Actions/SpendCreditsForActionAction.php`، `app/Domains/Nexus/Credit/Interfaces/MCP/CreditCapabilities.php`، `database/seeders/NexusCreditCapabilitiesSeeder.php`، `config/nexus/platform.php` (`credit.action_costs`)، ۶ فایل Action/Listener موجود (Marketplace+Negotiation+Contract) به‌روزرسانی شدند.

**تست:** ۱۵ تست جدید (۳ روی خودِ `SpendCreditsForActionAction`، ۱۰ integration روی هر ۵ Action گیت‌شده + شارژ `contract.generate`، ۲ روی `nexus.credit.balance` از طریق MCP) — همه پاس. کل تست‌های Nexus: ۱۳۷ پاس. سوییت کامل: ۱۰۱۰ pass / ۲۸۳ fail (بدون رگرشن — همان baseline ثابت ۲۸۳ شکست ماژول‌های غیرفعال).

**کامیت:** `feat(nexus): wire CostGate into existing MCP capabilities`.

---

## Phase 3 / M3 — Payment Integration (خرید کردیت با Zibal/Stripe)

**یافتهٔ کلیدی پیش از کدنویسی:** `CommerceServiceProvider` (جایی که `ZibalPaymentGateway`/`StripePaymentGateway`/`PaymentGatewayRegistry` واقعاً ثبت می‌شدند) از Phase 0 در `bootstrap/providers.php` غیرفعال است — کلاس‌ها هنوز autoload می‌شوند ولی هیچ‌کدام bind/boot نمی‌شوند. طبق «Extend, Don't Rebuild»: همان کلاس‌های آداپتور واقعی Zibal/Stripe (HTTP، امضا، verify) دوباره استفاده شدند، ولی Nexus خودش یک `PaymentGatewayRegistry` singleton مستقل می‌سازد و در `NexusServiceProvider::boot()` همان دو گیت‌وی را زیر آن ثبت می‌کند — دقیقاً همان ۳ خط Commerce، فقط صاحبش عوض شده.

**تصمیم کلیدی (مرز عبور Money):** `RedirectPaymentGatewayInterface::initiate()` امضایش روی `Money` خودِ Commerce تایپ شده، نه Credit. به‌جای بازسازی کامل ادغام HTTP واقعی Zibal/Stripe (که ناقض «هرگز rebuild نکن» بود)، این عبور مرز عمداً و فقط در یک نقطه (`PurchaseCreditsAction`) با یک تبدیل صریح انجام شد؛ لایهٔ Domain کردیت هرگز `Money` کامرس را import نمی‌کند.

**فقط Zibal، فعلاً:** ۳ پکیج کردیت (`docs/claude/monetization.md`) به تومان قیمت‌گذاری شده‌اند (Starter ۵۰۰هزار→۱۰۰۰کردیت، Professional ۲میلیون→۵۰۰۰، Enterprise ۱۰میلیون→۳۰۰۰۰). چون Zibal فقط ریال می‌پذیرد، تبدیل تومان→ریال (×۱۰) دقیقاً همین یک‌جا (مرز فراخوانی گیت‌وی) انجام می‌شود. Stripe در رجیستری ثبت شده (اثبات اینکه سیم‌کشی connector کار می‌کند) ولی `PurchaseCreditsAction` صراحتاً برای `gateway=stripe` خطای مستند می‌دهد — Stripe اصلاً ریال/تومان پشتیبانی نمی‌کند و یک ست پکیج دلاری جداگانه نیاز دارد که خارج از اسکوپ همین فاز است (یک محدودیت صادقانهٔ مستند، نه باگ، هم‌سبک «امضای دیجیتال = هش ساده» Phase 2/M6).

**`CreditPurchaseSession`** دقیقاً کپی شکل `PaymentSession` کامرس است (`ALLOWED_TRANSITIONS`، `assignId()`/`markInitiated()` یک‌باره‌ای) اما بدون cart/tax/discount/coupon (که برای خرید کردیت بی‌معنی‌اند). **`ConfirmCreditPurchaseAction`** هم دقیقاً همان الگوی `ConfirmRedirectPaymentAction` را کپی می‌کند: هرگز به claim فراخوان اعتماد نمی‌کند، همیشه با `verify()` واقعی گیت‌وی دوباره می‌پرسد، و روی session تکمیل‌شده idempotent است (کردیت دوباره اعطا نمی‌شود).

**مسیرها:** `GET/POST /nexus/credit/purchase` (گارد `business.auth`) و `GET /nexus/credit/payments/{gateway}/callback` (عمومی، بدون session/auth — دقیقاً همان بی‌طرفی `PaymentCallbackController` کامرس، چون گیت‌وی خارجی هیچ هویت تنانتی نمی‌شناسد). Webhook واقعی Stripe در این مرحله ساخته نشد چون هیچ مسیر خریدی هنوز به Stripe نمی‌رسد که چیزی برای تأیید داشته باشد.

**تست:** ۲۸ تست جدید (۱۰ Unit روی `CreditPackage`/`CreditPurchaseSession`، ۱۸ Feature روی `PurchaseCreditsAction`/`ConfirmCreditPurchaseAction`/جریان کامل HTTP) — همه با `MockRedirectPaymentGateway` خودِ Commerce (رجیستر شده زیر نام `'zibal'` در `setUp()`، بدون هیچ HTTP واقعی) — همه پاس. کل تست‌های Nexus: ۱۵۵ پاس. سوییت کامل: ۱۰۲۸ pass / ۲۸۳ fail (بدون رگرشن).

**کامیت:** `feat(nexus): add credit purchase payment integration (Zibal)`.

---

## Phase 3 / M4 — Escrow

**تصمیم صداقت اسکوپ (مثل «امضای دیجیتال = هش ساده» Phase 2/M6):** Nexus هیچ زیرساخت واقعی پرداخت/تسویه بانکی بین دو کسب‌وکار ندارد (آن Enterprise/Phase 7 است). پس `Escrow` اینجا یک **لایهٔ ردیابی وضعیت** روی ارزش معاملهٔ Contract است، نه نگهداری واقعی پول بین دو حساب بانکی — دقیقاً همین در docblock خودِ Entity مستند شده تا هیچ توسعه‌دهندهٔ بعدی آن را با یک درگاه تسویهٔ واقعی اشتباه نگیرد.

**زنجیرهٔ رویداد کامل (بدون هیچ تماس مستقیم بین دامنه‌ها):** `AcceptDealAction`/`ApprovePendingNegotiationAction` → `NegotiationWasAccepted` → `GenerateContractOnNegotiationAcceptedListener` → `GenerateContractAction` (که حالا رویداد جدید `ContractWasGenerated` را هم dispatch می‌کند) → `HoldEscrowOnContractGeneratedListener` → `HoldEscrowAction`. هر پنج قطعه با تست‌های M4 روی زنجیرهٔ واقعی (بدون `Event::fake()`) پوشش داده شدند.

**«Payment processing: 100cr + 0.5%» از `monetization.md` به دو چیز جدا تفسیر شد:**
1. ۱۰۰ کردیت flat → از طریق CostGate موجود (`SpendCreditsForActionAction`، کلید `contract.escrow.hold`) از Business آغازگر (همان ساده‌سازی مستند `contract.generate`) کسر می‌شود.
2. ۰.۵٪ → یک کارمزد **پول واقعی** است، نه کردیت — روی خودِ `Escrow` به‌عنوان `platformFeePercent`/`platformFeeAmount`/`netAmount` snapshot می‌شود (از `config('nexus.platform.margin.transaction_fee_percent')` در همین مرحله؛ M5 این را به `MarginSettingsService` retrofit می‌کند تا hot-reload واقعی شود).

**`businessAId`/`businessBId` مستقیماً از Contract روی خودِ Escrow دنورمالایز شدند** (نه lookup در لحظهٔ authorization از طریق `ContractRepositoryInterface`) تا `ReleaseEscrowAction`/`DisputeEscrowAction` دقیقاً همان قاعدهٔ «هر Action عضویت caller را خودش، مستقل، چک می‌کند» را حفظ کنند.

**State machine:** `Held → {Released, Disputed}`, `Disputed → Refunded` (`ALLOWED_TRANSITIONS` + `transitionTo()`، همان الگوی `Subscription`/`Negotiation`/`PaymentSession`/`CreditPurchaseSession`). «تأیید تحویل» (release) و «اعتراض» (dispute) توسط **هر دو طرف** قابل انجام است — همان محدودیت شناخته‌شده و مستند «هر دو طرف می‌توانند Pending Approval را resolve کنند» از Phase 2/M4، اینجا هم عمداً تکرار شد نه فراموش. حل واقعی اختلاف (evidence/mediation/arbitration) صراحتاً به Phase 6 (Trust & Reputation) موکول شد — `RefundEscrowAction` فقط ادمین (گارد `auth`/`admin` هستهٔ پلتفرم، هرگز `business.auth`) می‌تواند صدا بزند و فقط وضعیت را ثبت می‌کند، هیچ انتقال پول واقعی رخ نمی‌دهد (چون از اول هم پولی واقعاً جابه‌جا نشده بود).

**UI:** پنل Escrow جدید در `resources/views/nexus/negotiations/show.blade.php` (دکمه‌های تأیید تحویل/اعتراض وقتی `Held`) + یک صفحهٔ ادمین کوچک `dashboard.nexus.escrows.index` (فهرست Escrowهای Disputed + دکمهٔ Refund) زیر پیشوند `/dashboard` موجود پلتفرم پایه (گارد `auth`/`admin`، نه Jarvis-themed — تم پیش‌فرض Dashboard).

**فایل‌های اصلی:** `app/Domains/Nexus/Contract/{Domain,Application,Infrastructure}/**` (Escrow entity/repo/actions/listener)، `database/migrations/nexus/..._create_nexus_escrows_table.php`، `app/Http/Controllers/Dashboard/NexusEscrowController.php`، به‌روزرسانی `NegotiationViewerController`/`GenerateContractAction`/`NexusServiceProvider`.

**تست:** ۲۰ تست جدید (۷ Unit روی `Escrow`، ۱۳ Feature روی زنجیرهٔ رویداد واقعی + Release/Dispute/Refund + کنترلر ادمین + پنل Viewer) — همه پاس. یک تست موجود از M2 (`CostGateIntegrationTest`) برای انتظار شارژ جدید `contract.escrow.hold` به‌روزرسانی شد. کل تست‌های Nexus: ۱۷۵ پاس. سوییت کامل: ۱۰۴۸ pass / ۲۸۳ fail (بدون رگرشن).

**کامیت:** `feat(nexus): add Escrow (state-tracking layer over Contract deal value)`.

---

## Phase 3 / M5 — Admin Margin Settings (hot-reload واقعی)

**یافتهٔ کلیدی پیش از کدنویسی (تأیید هر دو Explore agent Phase 3):** در کل کدبیس هیچ مکانیزم settings قابل hot-reload وجود نداشت — نه جدول DB-backed، نه هیچ override زمان‌اجرا روی `config()`. الگوی موجود `SettingsController` هسته فقط یک ستون واقعی روی مدل `Tenant` می‌نویسد (`default_language`)، نه یک سیستم key/value عمومی. پس این مرحله باید از صفر ساخته می‌شد — دقیقاً همان چیزی که پلن پیش‌بینی کرده بود.

**`PlatformSetting`** یک انتیتی key/value ساده در دامنهٔ جدید `Admin` (اسکلت Phase 0، تا این لحظه کاملاً خالی) است. **`MarginSettingsService`** خودِ مکانیزم hot-reload است: هر خواندن از `Cache::rememberForever()` عبور می‌کند (کلید per-setting)، و اگر ردیف override در DB نباشد، به `config('nexus.platform.margin.*')` سقوط می‌کند (نصب تازه بدون هیچ اقدام ادمین کار می‌کند)؛ هر نوشتن بلافاصله همان کلید کش را `Cache::forget()` می‌کند — واقعاً بدون هیچ ری‌استارت یا `php artisan config:cache`، برخلاف `config()` استاتیک خودِ لاراول (که همین دلیل وجود این سرویس است، نه فقط نوشتن مستقیم در فایل config).

**Retrofit مستند در پلن:** `HoldEscrowAction` (M4) قبلاً مستقیماً `config('nexus.platform.margin.transaction_fee_percent')` می‌خواند — همان‌طور که در docblock خودش نوشته شده بود، این مرحله آن را به `MarginSettingsService::transactionFeePercent()` سوییچ کرد. تست جدید ثابت می‌کند یک override ادمین (نه فقط config) واقعاً روی Escrow بعدی اعمال می‌شود.

**تصمیم معماری کنترلر:** به‌جای اکشن‌های pass-through اضافه (`GetMarginSettingsAction`/`UpdateMarginSettingsAction`)، `NexusMarginSettingsController` مستقیماً به `MarginSettingsService` وابسته است — چون این سرویس خودش همان شکل درستِ لایهٔ Application را دارد (get/set)، پیچیدن یک Action دور آن فقط لایهٔ اضافه بدون فایده بود.

**مسیر:** `GET/PUT /dashboard/nexus/margin-settings` زیر گروه موجود `auth`+`admin` پلتفرم پایه (نه `business.auth`) — این یک نگرانی سطح اپراتور پلتفرم است، همان مرز معماری‌ای که Phase 1/M1 برای `User`/`UserRole` هسته تعیین کرد.

**فایل‌های اصلی:** `app/Domains/Nexus/Admin/{Domain,Application,Infrastructure}/**`، `database/migrations/nexus/..._create_nexus_platform_settings_table.php`، `app/Http/Controllers/Dashboard/NexusMarginSettingsController.php`، به‌روزرسانی `HoldEscrowAction`/`NexusServiceProvider`.

**تست:** ۸ تست جدید (۴ روی `MarginSettingsService` خودش — fallback به config، اثر فوری `set()` بدون پاک‌کردن کش، دیدپذیری از یک instance تازه، عدم تکرار ردیف روی `set()` دوم؛ ۳ روی کنترلر ادمین؛ ۱ تست جدید در `HoldEscrowOnContractGeneratedListenerTest` که ثابت می‌کند override ادمین بر config غالب است) — همه پاس. کل تست‌های Nexus: ۱۸۳ پاس. سوییت کامل: ۱۰۵۶ pass / ۲۸۳ fail (بدون رگرشن).

**کامیت:** `feat(nexus): add hot-reloadable Admin Margin Settings`.

---

## Phase 3 / M6 — Revenue Dashboard

**باگ واقعی پیدا و رفع شد پیش از commit (مقیاس واحد پول ناسازگار بین دو دامنه):** `nexus_credit_purchase_sessions.total_amount` تومان خام است (۵۰۰۰۰۰ یعنی ۵۰۰٬۰۰۰ تومان — همان قرارداد M3، بدون واحد فرعی)، ولی `nexus_escrows.*_amount` قرارداد Money خودِ Negotiation را به ارث می‌برد که واحد فرعی ۲رقمی دارد (`negotiations/show.blade.php` از قبل روی همین مقادیر `/100` می‌زند) — یک ناسازگاری از پیش موجود بین دو دامنهٔ Nexus، نه چیزی که این مرحله ایجاد کرده. جمع مستقیم این دو عدد در پیش‌نویس اول `GetRevenueDashboardAction` (قبل از commit) کاملاً نادرست بود (کارمزد ۰.۵٪ روی ۱۰٬۰۰۰ تومان واقعی، ۱۰۰ برابر بزرگ‌تر از واقعیت نمایش داده می‌شد). راه‌حل: `RevenueQuery` هر مقدار برگرفته از Escrow را قبل از هر جمعی بر ۱۰۰ تقسیم می‌کند (نرمال‌سازی به تومان واقعی)، مستند شده در docblock خودِ کلاس تا این ناسازگاری هرگز دوباره کشف نشود.

**دو جریان درآمد واقعی و مجزا** (طبق `docs/claude/monetization.md`): فروش پکیج کردیت (`nexus_credit_purchase_sessions.status = completed`، پول واقعی) و کارمزد Escrow (۰.۵٪ فقط وقتی Escrow به `released` برسد — `Held` هنوز شناسایی‌نشده، `Disputed`/`Refunded` برگشت‌خورده، مطابق منطق واقعی revenue recognition، نه «همان لحظهٔ وعده»). کردیت‌های مصرف‌شده (deduction) به‌عنوان یک سیگنال حجم مصرف گزارش می‌شود، نه درآمد دوباره — کسب‌وکار از قبل بابت آن کردیت‌ها پول واقعی پرداخته.

**«Net revenue» فعلاً برابر «Gross revenue» است** — چون هنوز هیچ هزینهٔ واقعی (مثل هزینهٔ LLM، قلمرو Phase 4) ردیابی نمی‌شود؛ دو کلید جدا نگه داشته شدند تا وقتی Phase 4 رسید، بدون تغییر shape این Action، جای محاسبهٔ هزینه باز باشد.

**`GetBusinessDashboardAction`** (آخرین جای‌نگه‌دار صادقانهٔ باقی‌مانده از Phase 1/M6): `activeNegotiations` حالا واقعاً از `NegotiationRepositoryInterface::findVisibleTo()` شمارش می‌شود (وضعیت‌های Proposed/Countered/PendingApproval) — دیگر `null` نیست.

**فایل‌های اصلی:** `app/Domains/Nexus/Analytics/Infrastructure/Queries/RevenueQuery.php`، `app/Domains/Nexus/Analytics/Application/Actions/GetRevenueDashboardAction.php`، `app/Http/Controllers/Dashboard/NexusRevenueController.php`، `resources/views/dashboard/nexus/revenue/index.blade.php`.

**تست:** ۱۲ تست جدید (۵ روی `GetRevenueDashboardAction` — شامل تست مستقیم صحت نرمال‌سازی واحد پول، ۲ روی کنترلر ادمین، به‌علاوه به‌روزرسانی `GetBusinessDashboardActionTest` برای `activeNegotiations` واقعی) — همه پاس. کل تست‌های Nexus: ۱۹۰ پاس. سوییت کامل: ۱۰۶۳ pass / ۲۸۳ fail (بدون رگرشن).

**کامیت:** `feat(nexus): add Revenue Dashboard`.

---

## Phase 3 / M7 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: همهٔ ۵ migration جدید (`nexus_credit_balances`, `nexus_credit_transactions`, `nexus_credit_purchase_sessions`, `nexus_escrows`, `nexus_platform_settings`) تمیز اجرا شدند (کنار چند migration دیگر پلتفرم پایه که بین این‌بار و آخرین اجرا اضافه شده بودند — `agent_messages`, `delegation_requests`, `reasoning_traces`, `payment_sessions`, بدون ارتباط با Nexus).
- `php artisan test` کامل: **۱۰۶۳ pass / ۲۸۳ fail** — بدون رگرشن (baseline قبل از Phase 3: ۹۷۵ pass؛ خالص +۸۸ تست پاس اضافه‌شده به کل سوییت. مجموع تست‌های *نوشته‌شده* در M1 تا M6 حدود ۱۰۴ متد بود — عدد خالص کوچک‌تر است چون چند تست موجود Phase 1/2 به‌جای افزوده‌شدن، به‌روزرسانی شدند (مثلاً `GetBusinessDashboardActionTest`، `CostGateIntegrationTest`)، نه اینکه رگرسیونی رخ داده باشد؛ همان ۲۸۳ شکست ثابت ماژول‌های غیرفعال، بدون تغییر، در هر مرحله تأیید شد.
- تست End-to-End دستی کامل روی سرور واقعی (`php artisan serve --port=9500`، همان مشکل رزرو پورت ویندوز Phase 2/M8 دوباره صادق بود) — این‌بار به‌جای کلیک مرورگری، از `php artisan tinker` روی همان سرور/دیتابیس واقعی استفاده شد (منطق یکسان، فقط بدون رندر HTML دستی):
  1. ثبت‌نام و تأیید دو کسب‌وکار واقعی (Buyer/Seller) → `CreditBalance` واقعاً برای هر دو باز شد (تأیید زنجیرهٔ رویداد M1 روی دیتابیس واقعی، نه فقط تست).
  2. `InitiateNegotiationAction` → `AcceptDealAction` روی مبلغ ۵٬۰۰۰٬۰۰۰ (واحد Negotiation) → Contract واقعی با PDF واقعی (۸۷۸KB، `%PDF-1.7` تأییدشده روی دیسک) → Escrow واقعی `Held` با `grossAmount=5000000`, `feePercent=0.5`, `feeAmount=25000`.
  3. `MarginSettingsService::set('transaction_fee_percent', 2.5)` در وسط سناریو (بعد از Hold) → سپس Escrow را Release کردیم → `platformFeePercent` روی خودِ Escrow **همان ۰.۵٪ اصلی** ماند (snapshot در لحظهٔ hold، نه دوباره‌محاسبه‌شده با override جدید) — دقیقاً رفتار مورد انتظار «compute once, apply durably later».
  4. `GetRevenueDashboardAction` بعد از Release: `escrowFeeRevenue = {amount: 250, count: 1}` — یعنی نرمال‌سازی واحد پول M6 (۲۵۰۰۰ واحد فرعی Negotiation ÷ ۱۰۰ = ۲۵۰ تومان واقعی) روی دادهٔ واقعی هم درست کار کرد.
  5. موجودی نهایی Buyer: `1000 - 20 (propose) - 2 (accept) - 50 (contract.generate) - 100 (escrow.hold) = 828` — دقیقاً مطابق محاسبهٔ دستی.
  6. تمام مسیرهای جدید (`/nexus/credit/purchase`, `/dashboard/nexus/margin-settings`, `/dashboard/nexus/revenue`, `/dashboard/nexus/escrows`) روی سرور واقعی چک شدند: صفحات عمومی `200`، صفحات گاردشده بدون session `302` (ریدایرکت به login) — بدون هیچ `500`.
- `git log --oneline origin/main`: هر ۷ مرحلهٔ Phase 3 (M1 تا M7) کامیت و push شده‌اند.

**کامیت:** `docs(nexus): Phase 3 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 3 (Credit & Payment Economy) — تکمیل شد

| دامنه | Entity/Service اصلی | Action ها | MCP Capability | تست |
|---|---|---|---|---|
| Credit (ledger) | `CreditBalance`, `CreditTransaction` | Grant, Deduct, Refund, GetBalance | `nexus.credit.balance` | ۲۱ |
| Credit (CostGate) | — | SpendCreditsForAction | — (گیت روی ۵ Capability موجود + `contract.generate`) | ۱۵ |
| Credit (Payment) | `CreditPurchaseSession`, `CreditPackage` | PurchaseCredits, ConfirmCreditPurchase | — | ۲۸ |
| Contract (Escrow) | `Escrow` | Hold, Release, Dispute, Refund | — | ۲۰ |
| Admin | `PlatformSetting`, `MarginSettingsService` | (get/set سرویس) | — | ۸ |
| Analytics (Revenue) | — (read model) | GetRevenueDashboard | — | ۱۲ |
| **مجموع** | | | | **~۱۰۴ تست نوشته‌شده / خالص +۸۸ در کل سوییت** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **CostGate همیشه داخل خودِ Action، نه در پایپ‌لاین مشترک MCP** — طبق Decision 007 (بدون منطق تجاری در Core)؛ همیشه *بعد از* موفقیت گذار وضعیت اصلی شارژ می‌شود، هرگز قبلش (یک درخواست نامعتبر هرگز کردیت کم نمی‌کند).
2. **کلیدهای پیکربندی هرگز نباید خودشان نقطه داشته باشند وقتی با `config()`ی dot-notation خوانده می‌شوند** — یک باگ واقعی که در M2 پیدا و مستند شد؛ آرایهٔ حاوی کلیدهای نقطه‌دار باید یک‌بار در سطح بالا خوانده شود و بعد با اندیس ساده (نه dot-path) به آن دسترسی پیدا کرد.
3. **«Extend, Don't Rebuild» یعنی حتی از یک ماژول غیرفعال هم می‌توان کلاس‌های Infrastructure واقعی (نه بایندینگ‌ها) را دوباره استفاده کرد** — Nexus کلاس‌های واقعی Zibal/Stripe کامرس را زیر رجیستری مستقل خودش ثبت کرد، بدون بازسازی ادغام HTTP.
4. **صداقت اسکوپ برای زیرساخت‌هایی که وجود ندارند (Escrow، امضای دیجیتال Phase 2)** یک الگوی تکرارشوندهٔ این پروژه است، نه یک استثنا — یک state-tracking layer با docblock صریح، نه شبیه‌سازی یک سیستم واقعی که وجود ندارد.
5. **hot-reload واقعی = DB + Cache، نه `config()`** — اولین‌بار در این کدبیس ساخته شد (M5)؛ هر تنظیم قابل‌تغییر آینده باید همین الگو (`Cache::rememberForever` + `Cache::forget` روی نوشتن) را دنبال کند، نه یک فایل config جدید.
6. **واحدهای پول بین دامنه‌های مختلف Nexus می‌توانند مقیاس متفاوتی داشته باشند** (Credit = تومان خام، Negotiation/Escrow = واحد فرعی ۲رقمی) — هر Query/Action ای که چند دامنه را با هم جمع می‌زند باید صریحاً نرمال‌سازی کند؛ این یک ناسازگاری از پیش موجود بین Phase 2 و Phase 3 است، نه یک باگ تازه.
7. **محدودیت شناخته‌شدهٔ باقی‌مانده (تکرار از Phase 2):** Release/Dispute Escrow توسط هر دو طرف قابل انجام است، نه فقط طرف مربوطه — همان الگوی Pending Approval؛ باید در فاز بعد سخت‌گیرانه‌تر شود.
8. **«Net revenue» = «Gross revenue» فعلاً** — تا وقتی هزینهٔ واقعی LLM (Phase 4) ردیابی شود.

**آماده برای Phase 4 (LLM Provider System)** طبق `docs/nexus-roadmap.md`.

---

# Phase 4 — LLM Provider System

**دستور:** «برو سراغ فاز 4 کامل کن» (بعد از تأیید Phase 3). قبل از کدنویسی، سه Explore agent موازی روی زیرساخت موجود (LLM موجود در AgentOrchestrator، الگوی hot-reload/CostGate Phase 3، الگوی Connector Registry/wiring در NexusServiceProvider) اجرا شد و پلن کامل ۸ مرحله‌ای (M1–M8) تأیید شد — با یک تصمیم صریح پیش از هر خط کد: **بازنویسی reasoning مذاکره به LLM واقعی عمداً خارج از اسکوپ این فاز است** (پایین‌تر توضیح داده شده).

**یافتهٔ کلیدی پیش از کدنویسی:** یک انتزاع LLM دیگر، ساده‌تر و کاملاً جدا، از قبل در ماژول *فعال* `AgentOrchestrator` وجود داشت (`LLMClientInterface::complete()/completeStructured()`، پیاده‌سازی‌های واقعی OpenAI/Claude/OpenRouter، یک بایندینگ سراسری واحد، بدون متادیتای cost/token). این انتزاع فاقد شکل موردنیاز Phase 4 بود (بدون cost/token/latency، بدون رجیستری چندگانه) و طبق قانون ۱ («Extend, Don't Rebuild») بررسی شد ولی **عمداً reuse نشد** — یک طراحی موازی مستقل، نه نقض آن قانون، چون شکل واقعاً متفاوت بود (برخلاف Zibal/Stripe در Phase 3/M3 که شکل دقیقاً یکی بود). در عوض، الگوهای اثبات‌شدهٔ HTTP آن (Guzzle، `?ClientInterface $http = null` تزریق‌پذیر، نرمال‌سازی خطا به یک Exception) کپی شد. همچنین `config/nexus/platform.php` از قبل یک بخش `llm` داشت که هیچ‌کدام کدهای فعلی آن را نمی‌خواندند — کاملاً آزاد برای بازطراحی بدون شکستن چیزی.

---

## Phase 4 / M1 — هستهٔ دامنهٔ LLM Provider

**تصمیم کلیدی:** `LLMProviderInterface` (`chat()`/`estimateCost()`/`supports()`، دقیقاً طبق `docs/nexus-roadmap.md`) در `Domain/Services` قرار گرفت (نه `Domain/Repositories`) چون یک پورت خروجی به سیستم خارجی است، نه پورت پایداری — دقیقاً همان جایگاه `LLMClientInterface` در AgentOrchestrator. `LLMResponse` یک Value Object دامنه (immutable، فکتوری استاتیک `success()`) است چون خروجی یک اینترفیس دامنه است. فیلد `error` طبق شکل مستندشدهٔ `docs/claude/llm-strategy.md` نگه داشته شد ولی همیشه `null` است — هر خطای واقعی در این کدبیس با یک Exception تایپ‌شده اعلام می‌شود، نه یک پاسخ soft-error.

**فایل‌های اصلی:** `app/Domains/Nexus/Llm/Domain/{Services/LLMProviderInterface, ValueObjects/{LLMResponse,LLMFeature}, Exceptions/*}.php`، بازنویسی کامل بخش `llm` در `config/nexus/platform.php` (شمای جدید: `providers` با ۶ کلید، `provider_tiers`، `feature_providers`، `fallback_chain`، `cost_control`، `behavior`).

**تست:** ۵ تست جدید (Unit روی `LLMResponse`/`LLMFeature`) — همه پاس.

**کامیت:** `feat(nexus): add LLM Provider domain core (interface, response VO, exceptions)`.

---

## Phase 4 / M2 — پیاده‌سازی‌های واقعی Provider + رجیستری

**تصمیم کلیدی:** پنج از شش provider (OpenAI, OpenRouter, Groq, self-hosted Qwen, local Llama) دقیقاً همان فرمت سیمی OpenAI Chat Completions را صحبت می‌کنند — پس یک `AbstractOpenAiCompatibleProvider` مشترک منطق HTTP را یک‌بار پیاده می‌کند؛ فقط `AnthropicLLMProvider` جدا ماند (Messages API واقعاً متفاوت است — دقیقاً همان دلیلی که `ClaudeClient` را از `OpenAIClient` جدا نگه داشته بود). `LLMProviderRegistry` پنجمین کاربرد الگوی Connector همین کدبیس است (`ConnectorRegistry`/`ShippingProviderRegistry`/`ChannelSenderRegistry`/`PaymentGatewayRegistry`) — عیناً از `PaymentGatewayRegistry` کپی شد (`register`/`get`/`registered` + یک `NotFoundException`).

**باگ واقعی که از قبل مستند بود، این‌بار پیشگیرانه رعایت شد:** همان مشکل RFC 3986 که در `OpenRouterClient` (AgentOrchestrator) واقعاً رخ داده بود (وقتی `base_uri` خودش یک path segment دارد، مسیر درخواست باید بدون `/` ابتدایی باشد وگرنه Guzzle آن segment را بی‌صدا حذف می‌کند) — این‌بار در `AbstractOpenAiCompatibleProvider` از روز اول با `rtrim($baseUrl,'/').'/'` + مسیر نسبی رعایت شد و برای هر Provider با تست جداگانه (`UriResolver::resolve` روی کلاینت واقعی پشت Reflection) تأیید شد.

**Provider های local/self-hosted:** چون هیچ سرور مدل محلی واقعی در این محیط توسعه اجرا نمی‌شود (همان صداقت مستندشدهٔ هر Connector خارجی دیگر این کدبیس)، به یک endpoint قابل‌تنظیم و سازگار با OpenAI (پیش‌فرض سبک Ollama، `http://localhost:11434/v1`) اشاره می‌کنند؛ `estimateCost()` برایشان همیشه `0.0` است، بدون قید‌وشرط.

**فایل‌های اصلی:** ۷ کلاس Provider زیر `Infrastructure/Providers/**`، `Application/Services/LLMProviderRegistry.php`، بروزرسانی `NexusServiceProvider` (singleton جدید + ثبت هر ۶ provider در `boot()` از روی `config('nexus.platform.llm.providers.*')`).

**تست:** ۳۸ تست جدید (۴ Unit روی رجیستری، ۳۴ Feature روی ۶ Provider — هرکدام با Guzzle `MockHandler`، هیچ HTTP واقعی) — همه پاس. کل تست‌های Nexus: ۲۳۳ پاس.

**کامیت:** `feat(nexus): add LLM provider adapters + registry (OpenAI/Anthropic/OpenRouter/Groq/local Qwen/local Llama)`.

---

## Phase 4 / M3 — دفتر کل هزینه (`LLMUsageLog`)

**تصمیم کلیدی:** `LLMUsageLog` عیناً همان شکل `CreditTransaction` را کپی می‌کند (ردیف immutable، بدون `updated_at`، فکتوری استاتیک `record()`) — همان فلسفهٔ audit-trail، این‌بار برای هر تلاش فراخوانی LLM (موفق یا ناموفق، اصلی یا fallback). `businessId`/`agentId` هر دو nullable هستند تا پینگ‌های «تست اتصال» ادمین (بدون هیچ Business/Agent) هم بتوانند ثبت شوند — یک پینگ روی provider پولی واقعاً هزینهٔ واقعی دارد و نباید بی‌صدا از audit trail غایب باشد.

**`chargedCostUsd`** برای اولین‌بار به‌ `MarginSettingsService::llmCostMarkupPercent()` (که از Phase 3/M5 وجود داشت ولی هیچ‌وقت صدا زده نشده بود) یک caller واقعی می‌دهد: `chargedCostUsd = realCostUsd × (۱ + markup/۱۰۰)`. تجمیع (برای چک بودجهٔ M6) عمداً از repository جدا نگه داشته شد و در یک `LLMUsageQuery` (شِل خالی در این مرحله) قرار گرفت — دقیقاً همان جداسازی که `RevenueQuery` قبلاً برای Analytics تثبیت کرده بود.

**فایل‌های اصلی:** `app/Domains/Nexus/Llm/{Domain/Entities/LLMUsageLog, Domain/Repositories/LLMUsageLogRepositoryInterface, Infrastructure/{Models,Repositories,Queries}/*}.php`، `database/migrations/nexus/..._create_nexus_llm_usage_logs_table.php`.

**تست:** ۷ تست جدید (۴ Unit روی Entity، ۳ Feature روی رفت‌وبرگشت واقعی دیتابیس) — همه پاس. کل تست‌های Nexus: ۲۴۰ پاس.

**کامیت:** `feat(nexus): add LLM cost ledger (LLMUsageLog)`.

---

## Phase 4 / M4 — `LLMSettingsService` (hot-reload)

**تصمیم کلیدی:** کپی ساختاری دقیق `MarginSettingsService` (همان `Cache::rememberForever`/`Cache::forget`، همان fallback به `config()`) با یک `CACHE_PREFIX` مستقل (`nexus.llm_setting.`) — و همان جدول عمومی `nexus_platform_settings` را دوباره استفاده می‌کند، **بدون migration جدید**، فقط با کلیدهای تازه (`llm.feature_provider.*`, `llm.fallback_chain`, `llm.cost_control.*`). `set*()` پیش از ذخیره، شناسهٔ provider را با `LLMProviderRegistry::registered()` اعتبارسنجی می‌کند — دقیقاً همان «validate provider IDs on save» که `docs/claude/llm-strategy.md` می‌خواست؛ یک تایپوی ادمین هرگز بی‌صدا routing را خراب نمی‌کند.

**فایل‌های اصلی:** `app/Domains/Nexus/Llm/Application/Services/LLMSettingsService.php`.

**تست:** ۹ تست جدید (fallback به config روی نصب تازه، اثر فوری `set()` بدون پاک‌کردن کش، دیدپذیری از یک instance تازه، رد شدن provider نامعتبر بدون persist) — همه پاس. کل تست‌های Nexus: ۲۴۹ پاس.

**کامیت:** `feat(nexus): add LLMSettingsService (hot-reload feature routing/budget)`.

---

## Phase 4 / M5 — `LLMRouter`

**تصمیم کلیدی:** نقطهٔ اتصال مرکزی — provider اصلی را از `LLMSettingsService` می‌خواند، صدا می‌زند، و در صورت شکست (و فقط اگر `behavior.enable_fallback` روشن باشد) زنجیرهٔ fallback را طی می‌کند؛ یک candidate پولی وقتی provider اصلی خودش رایگان/محلی بوده، پرش داده می‌شود مگر `allow_local_to_paid_fallback` صریحاً روشن باشد («هرگز از local به paid فال‌بک نکن مگر صریحاً مجاز شود»، `docs/claude/llm-strategy.md` §۱۱). هر تلاش — موفق یا ناموفق، اصلی یا fallback — یک ردیف `LLMUsageLog` می‌سازد.

**اگر کل زنجیره شکست بخورد:** `AllLLMProvidersFailedException` پرتاب می‌شود، نه یک `LLMResponse` جعلی. «استفاده از Rule Engine در صورت fail شدن همه LLMها» (roadmap) با این طراحی برآورده می‌شود که هر دامنه‌ای که این Router را صدا می‌زند، خودش این Exception را بگیرد و به منطق قطعی موجودش (مثل `NegotiationReasoningService`) برگردد — نه اینکه خودِ Router یک Rule Engine عمومی دومی اختراع کند که در این کدبیس وجود ندارد. **در این فاز هیچ caller واقعی این Exception را نمی‌گیرد** (تصمیم مستند زیر) — درستیِ این قرارداد را تست‌های همین کلاس و E2E دستی M8 اثبات می‌کنند.

**فایل‌های اصلی:** `app/Domains/Nexus/Llm/Application/Services/LLMRouter.php`.

**تست:** ۶ تست جدید (موفقیت اصلی، شکست اصلی→موفقیت fallback با `fromFallback=true`، شکست کل زنجیره، عدم فال‌بک خودکار local→paid، فعال‌سازی صریح آن، غیرفعال‌بودن fallback) — همه پاس. کل تست‌های Nexus: ۲۵۵ پاس.

**کامیت:** `feat(nexus): add LLMRouter (provider selection, fallback chain, usage logging)`.

---

## Phase 4 / M6 — کنترل بودجه (`LLMBudgetGuard`)

**تصمیم کلیدی (مرز عبور واحد پول):** بودجه‌ها به تومان (IRT) پیکربندی/نمایش می‌شوند (هم‌راستا با `credit.currency`)، ولی `LLMUsageLog` هزینه را به دلار (USD — واحدی که هر provider واقعی صورتحساب می‌دهد) ذخیره می‌کند. `LLMBudgetGuard` تنها نقطهٔ صریح تبدیل این دو ارز است (`× usd_to_irt_rate`) — دقیقاً همان درسی که `RevenueQuery` قبلاً دربارهٔ ناهم‌خوانی واحد فرعی Credit/Escrow مستند کرده بود.

**«مسدود کردن provider های پولی، اجبار به local» بدون هیچ حالت خاص:** Guard برای provider های رایگان/local همیشه no-op است، و برای زمینهٔ خالی (هر دو `agentId`/`businessId` تهی — یعنی پینگ تست اتصال ادمین) هم چک نمی‌کند. یک candidate پولی که از بودجه رد شود، دقیقاً مثل یک provider خراب توسط حلقهٔ موجود `LLMRouter` گرفته و به candidate بعدی (که طبق فیلتر tier قبلاً حتماً رایگان/local است) می‌رود — بدون هیچ شاخهٔ کد اضافه.

**فایل‌های اصلی:** `app/Domains/Nexus/Llm/Application/Services/LLMBudgetGuard.php`، تکمیل متدهای تجمیعی `LLMUsageQuery` (شِل خالی از M3)، بروزرسانی `LLMRouter` برای صدا زدن Guard پیش از هر candidate پولی.

**تست:** ۹ تست جدید (۶ روی خودِ Guard — no-op برای رایگان/بدون زمینه، پرتاب واقعی روی رد شدن از بودجهٔ روزانه/ماهانه با ردیف‌های واقعی دیتابیس؛ ۳ integration روی `LLMRouter` که نشان می‌دهد عبور از بودجه شفاف به provider رایگان سقوط می‌کند) — همه پاس. کل تست‌های Nexus: ۲۶۴ پاس.

**کامیت:** `feat(nexus): add LLM budget control (LLMBudgetGuard)`.

---

## Phase 4 / M7 — Admin LLM Switcher

**تحویلیِ واقعی roadmap:** «ادمین می‌تواند LLM را بدون تغییر کد و با یک کلیک عوض کند.» `NexusLlmSettingsController` (گارد `auth`+`admin` هستهٔ پلتفرم، `layouts.dashboard` — **نه** تم Jarvis، دقیقاً همان مرز معماری‌ای که Phase 1/M1 برای `User`/`UserRole` هسته تعیین کرد و `NexusMarginSettingsController` قبلاً دنبال کرده بود) مستقیماً به `LLMSettingsService`/`LLMProviderRegistry`/`LLMUsageQuery` وابسته است، بدون Action واسط — همان توجیهی که `NexusMarginSettingsController` قبلاً داده بود.

**«تست اتصال» عمداً از `LLMRouter`/`LLMBudgetGuard` عبور نمی‌کند:** یک ادمین باید بتواند حتی وسط قطعی یا عبور از بودجه، سلامت یک provider را بررسی کند — دقیقاً همان مشکلی که این دکمه قرار است تشخیص دهد. با این‌حال هر پینگ (موفق یا ناموفق) در `LLMUsageLog` ثبت می‌شود (`feature = 'admin_test_connection'`, `business_id`/`agent_id` هر دو null) چون یک پینگ روی provider پولی واقعاً هزینهٔ واقعی دارد.

**بنر «عبور از بودجه»:** یک نسخهٔ کوچک‌شدهٔ Monitoring Dashboard کامل §۱۲ (که به‌طور مستند به یک «Phase 4.5» بعدی موکول شد) — فقط «آیا الان کسی از بودجه رد شده؟»، با دو متد جدید `LLMUsageQuery::any{Agent,Business}Over*Budget()` که بیشترین هزینهٔ گروه‌بندی‌شده را در PHP مقایسه می‌کنند (نه `groupBy()->having()->exists()`، برای اجتناب از تفاوت رفتار درایورهای مختلف دیتابیس).

**فایل‌های اصلی:** `app/Http/Controllers/Dashboard/NexusLlmSettingsController.php`، `resources/views/dashboard/nexus/llm-settings/index.blade.php` (فرم Tailwind ساده هم‌شکل با `margin-settings`، به‌علاوه یک دکمهٔ «تست» به‌ازای هر ویژگی با Alpine.js `x-data`/`fetch()` — همان الگوی pollingِ `negotiations/show.blade.php`)، مسیرهای جدید زیر گروه موجود `nexus.` در `routes/web.php`، کلیدهای ترجمهٔ جدید `nexus.admin.llm_settings.*` در `lang/{fa,en}/messages.json`.

**تست:** ۸ تست جدید (۴ روی کنترلر — نمایش، ذخیره با hot-reload فوری، رد provider نامعتبر؛ ۴ روی endpoint تست اتصال — موفق، ناموفق، provider ثبت‌نشده، هرگز چک نشدن بودجه) — همه پاس. کل تست‌های Nexus: ۲۷۲ پاس.

**کامیت:** `feat(nexus): add Admin LLM Switcher (feature routing, fallback chain, budget, test connection)`.

---

## Phase 4 / M8 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: تنها migration جدید (`nexus_llm_usage_logs`) تمیز اجرا شد.
- `php artisan test` کامل: **۱۱۴۵ pass / ۲۸۳ fail** — بدون رگرشن (baseline قبل از Phase 4: ۱۰۶۳ pass؛ خالص +۸۲ تست پاس اضافه‌شده، دقیقاً برابر ۸۲ تست نوشته‌شدهٔ M1 تا M7 — این‌بار، برخلاف فازهای قبل، هیچ تست موجودی نیاز به بروزرسانی نداشت چون Phase 4 هیچ رفتار موجودی را عوض نکرد، فقط زیرساخت مستقل جدید اضافه کرد).
- تست End-to-End دستی واقعی (نه فقط PHPUnit، از طریق `php artisan tinker` روی سرور/دیتابیس واقعی — همان جایگزین مستندشدهٔ کلیک مرورگری از Phase 2/3 به‌خاطر مشکل شناخته‌شدهٔ رزرو پورت ویندوز):
  1. هر ۶ provider واقعاً در `LLMProviderRegistry` ثبت‌شده بودند.
  2. نگاشت پیش‌فرض feature→provider نصب تازه دقیقاً با `config/nexus/platform.php` مطابقت داشت (`reasoning`→`qwen-14b-local`, `negotiation`→`qwen-14b-local`, `classification`→`llama-3.2-3b-local`, `fallback`→`openrouter`).
  3. **اثبات واقعی (بدون mock) مسیر شکست→fallback:** `reasoning` را به `qwen-14b-local` اشاره دادیم (که در این محیط توسعه به `localhost:11434` نرسیدنی است) و زنجیرهٔ fallback را به `openrouter` (بدون کلید API واقعی) — فراخوانی واقعی `LLMRouter::route()` دقیقاً دو خطای شبکه‌ای واقعی گرفت (`cURL error 7: Failed to connect` برای local، یک خطای HTTP واقعی از OpenRouter برای fallback)، هر دو تلاش را در `LLMUsageLog` ثبت کرد، و در نهایت `AllLLMProvidersFailedException` پرتاب کرد — دقیقاً رفتار طراحی‌شده، این‌بار روی شبکهٔ واقعی نه `MockHandler`.
  4. **اثبات hot-reload:** `LLMSettingsService::setFeatureProvider()` وسط همان session تغییر داده شد؛ یک instance کاملاً تازهٔ سرویس (شبیه‌سازی یک request جدید) بلافاصله مقدار جدید را دید — بدون هیچ ری‌استارت.
  5. `MarginSettingsService::llmCostMarkupPercent()` مقدار پیش‌فرض `30` را برگرداند — تأیید این‌که M3 واقعاً به آن وصل است.
  6. `php artisan route:list --path=llm-settings`: هر ۳ مسیر جدید (`GET/PUT /dashboard/nexus/llm-settings`, `POST .../test-connection`) درست ثبت شده‌اند؛ صحت گارد (`302` بدون session) و بدون هیچ `500` قبلاً توسط تست‌های Feature کنترلر (M7) روی همین مسیرها تأیید شده بود.
- `git log --oneline`: هر ۷ مرحلهٔ Phase 4 (M1 تا M7) کامیت شده‌اند.

**کامیت:** `docs(nexus): Phase 4 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 4 (LLM Provider System) — تکمیل شد

| دامنه | Entity/Service اصلی | Provider ها | Admin سطح | تست |
|---|---|---|---|---|
| Llm (Core) | `LLMResponse`, `LLMFeature` | — | — | ۵ |
| Llm (Providers) | ۷ پیاده‌سازی `LLMProviderInterface` | OpenAI, Anthropic, OpenRouter, Groq, Qwen-14B-local, Llama-3.2-local | — | ۳۸ |
| Llm (Ledger) | `LLMUsageLog` | — | — | ۷ |
| Llm (Settings) | `PlatformSetting` (reused) | `LLMSettingsService` | — | ۹ |
| Llm (Router) | — | `LLMRouter` | — | ۶ |
| Llm (Budget) | — | `LLMBudgetGuard`, `LLMUsageQuery` | بنر عبور از بودجه | ۹ |
| Llm (Admin UI) | — | — | `/dashboard/nexus/llm-settings` + تست اتصال | ۸ |
| **مجموع** | | | | **۸۲ (پاس)** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **بازنویسی reasoning مذاکره به LLM واقعی عمداً خارج از اسکوپ این فاز ماند** — `NegotiationReasoningService` (Phase 2/M5) قطعی و بدون شکست باقی می‌ماند؛ زیرساخت LLM کامل و آزمایش‌شده است (`LLMRouter::route(LLMFeature::Negotiation, ...)` دقیقاً همان فراخوانی‌ای است که یک بازطراحی آینده نیاز دارد)، ولی اتصال واقعی آن یک تغییر جدا و مستند برای فاز/بعدفاز دیگری است، نه چیزی که این‌جا قاچاقی اضافه شود.
2. **دو انتزاع LLM مجزا در این کدبیس عمداً همزیستی می‌کنند** — `LLMClientInterface` (AgentOrchestrator، بدون cost/token، یک بایندینگ سراسری) برای Agent های Core، و `LLMProviderInterface` (Nexus، رجیستری چندگانه با متادیتای کامل) برای مصرف‌کنندگان آیندهٔ Nexus — یکی‌سازی آن‌ها یک تصمیم بزرگ‌تر است که این فاز عمداً نگرفت.
3. **Monitoring Dashboard کامل (نمودارهای per-provider/per-feature) ساخته نشد** — فقط یک بنر سادهٔ «کسی الان از بودجه رد شده؟». یک `GetLLMUsageDashboardAction`/گسترش `LLMUsageQuery` مشابه `RevenueQuery`/`GetRevenueDashboardAction` Phase 3، کاندید طبیعی یک فاز بعدی است.
4. **کلیدهای provider هرگز نباید خودشان نقطه داشته باشند** — همان درسِ Phase 3/M2 دربارهٔ `action_costs`، این‌بار در طراحی `provider_tiers`/`feature_providers` از ابتدا رعایت شد (شناسه‌ها با `-` جدا می‌شوند، نه `.`).
5. **واحد پول بین Credit (تومان) و Llm (دلار) نیز، مثل Escrow/Negotiation در Phase 3، باید صریح و در یک نقطهٔ مشخص تبدیل شود** — `LLMBudgetGuard` این نقطه است برای دامنهٔ LLM؛ هر مصرف‌کنندهٔ آیندهٔ دیگر باید همین‌جا تبدیل کند، نه جای دیگر.

**آماده برای Phase 5 (Viral Growth Engine)** طبق `docs/nexus-roadmap.md`.

---

# Phase 5 — Viral Growth Engine

**دستور:** «فاز 5 رو باید درست کنی» (بعد از تأیید Phase 4). قبل از کدنویسی، دو Explore agent موازی روی زیرساخت موجود (سیستم Notification/Mail، وضعیت اسکلت Reputation/Marketplace/Analytics، الگوی MCP capability/event-wiring، الگوی کد تولید کوپن) اجرا شد و پلن کامل ۶ مرحله‌ای (M1–M6) تأیید شد.

**یافتهٔ کلیدی پیش از کدنویسی:** هیچ دامنهٔ از پیش‌ساخته‌ای برای «Growth» در اسکلت اولیهٔ ۱۰-دامنه‌ای Phase 0 وجود نداشت (Referral/Invite/Coalition هیچ‌کدام جا نمی‌افتند در Business/Agent/Catalog/Negotiation/Contract/Credit/Reputation/Marketplace/Analytics/Admin). دقیقاً همان دلیلی که Phase 4، دامنهٔ کاملاً جدید `Llm` را بدون اسکلت از قبل ساخت، این فاز هم دامنهٔ جدید `app/Domains/Nexus/Growth` را ساخت (Referral + Invite + Coalition)؛ Network Visualization و Viral Analytics به‌جای دامنهٔ جدید، به دامنه‌های read-model موجود (Marketplace، Analytics) اضافه شدند — چون هر دو صرفاً می‌خوانند، نه می‌نویسند، دقیقاً همان استثنای Inter-Module Communication که از Phase 1/M6 مستند است. ماژول *فعال* `App\Modules\Notifications` (با `EmailSender`/`SendNotificationAction` واقعی) پیدا شد و برای ارسال دعوت‌نامه استفاده شد — بدون ساخت یک پایپ‌لاین ایمیل دوم.

## Phase 5 / M1 — Referral System core (پاداش دوطرفه، ردیابی چندلایه)

**تصمیم کلیدی:** هر Business تأییدشده یک `ReferralCode` منحصربه‌فرد خودکار می‌گیرد (`IssueReferralCodeOnBusinessVerifiedListener` روی همان `BusinessWasVerified`، کنار listenerهای Agent/Credit موجود — الگوی auto-provisioning تکرارشونده). ثبت‌نام با `?ref=CODE` یک `ReferralSignup` با وضعیت Pending می‌سازد (`RecordReferralSignupAction`، فراخوانی‌شده از خودِ Controller نه از داخل `RegisterBusinessAction` — امضا/تست‌های آن Action دست‌نخورده ماند). پاداش فقط در لحظهٔ Verified شدنِ معرفی‌شونده پرداخت می‌شود (`GrantReferralRewardOnBusinessVerifiedListener`) — یک معرفی برای کسب‌وکاری که هرگز تأیید نشود، هیچ‌وقت پاداشی نمی‌گیرد، دقیقاً همان صداقتی که تأمین کردیت شروع از Phase 3/M1 قبلاً رعایت کرده بود. Multi-tier: اگر معرف خودش قبلاً معرفی‌شده و پاداش‌گرفته بود، معرفِ او هم یک پاداش تیر-۲ کوچک‌تر می‌گیرد — فقط یک گام، نه زنجیرهٔ نامحدود.

**فایل‌های اصلی:** `app/Domains/Nexus/Growth/{Domain,Application,Infrastructure,Interfaces}/**` (ReferralCode/ReferralSignup)، دو migration، `CreditTransactionType::ReferralBonus` جدید، بخش `growth` جدید در `config/nexus/platform.php`، capability `nexus.referral.status` (رایگان، مثل `nexus.credit.balance`)، صفحهٔ `/nexus/growth/referrals`.

**تست:** ۲۰ تست جدید (Unit روی Entityها + Feature روی چرخهٔ کامل ثبت‌نام→Verify→پاداش دوطرفه→تیر-۲، capability MCP، کنترلر) — همه پاس.

**کامیت:** `feat(nexus): add Referral System core (two-sided rewards, multi-tier)`.

---

## Phase 5 / M2 — Agent-Invites-Agent (ردیابی دعوت + ارسال ایمیل واقعی)

**تصمیم کلیدی:** `SendAgentInviteAction` از ماژول *فعال* `Notifications` (`SendNotificationAction`) استفاده می‌کند، نه یک پایپ‌لاین ایمیل جدید — یک دعوت خام به یک ایمیل بیرونی، بدون Customer/Agent id مالک، پس `$recipientType`/`$recipientId` عمداً `null` می‌مانند (طبق مستندات خودِ `SendNotificationAction`: preference-check کاملاً skip می‌شود). یک `NotificationType::AgentInvite` جدید افزوده شد (تغییر additive، بدون شکستن چیزی).

**صداقت ردیابی:** `Invite` فقط دو وضعیت دارد (`Sent`/`Converted`) — نه «باز شد»/«کلیک شد»، چون هیچ زیرساخت pixel/redirect-tracking در این کدبیس وجود ندارد (همان صداقت اسکوپِ Escrow). تبدیل («Converted») فقط وقتی رخ می‌دهد که همان کد معرفی واقعاً در ثبت‌نام استفاده شود (`RecordReferralSignupAction` قدیمی‌ترین Invite بازِ همان کد را می‌بندد) — سیگنالی قابل اثبات، نه فرض «ایمیل باز شد».

**CostGate:** `nexus.invite.send` هزینهٔ کوچک ثابتی دارد (config جدید، نه در `docs/claude/monetization.md` — مستند و مستدل به‌عنوان افزودهٔ جدید Phase 5) تا از اسپم جلوگیری کند؛ برخلاف `nexus.referral.status`/`nexus.credit.balance` که رایگان می‌مانند.

**فایل‌های اصلی:** `Invite` (Entity/Repo/Model)، `SendAgentInviteAction`، `ListSentInvitesAction`، capability `nexus.invite.send`، صفحهٔ `/nexus/growth/invites`.

**تست:** ۱۴ تست جدید Growth + ۲ تست جدید در `RegisterBusinessControllerTest` (مسیر `?ref=` واقعی از طریق کنترلر) — همه پاس.

**کامیت:** `feat(nexus): add Agent-Invites-Agent (Invite tracking + email delivery)`.

---

## Phase 5 / M3 — Group Buying Coalitions (خرید گروهی)

**تصمیم کلیدی:** `Coalition` مستقیماً VOهای خودِ Negotiation (`Money`, `CatalogItemType`) را دوباره‌استفاده می‌کند، نه یک کپی چهارم — چون هدف یک Coalition دقیقاً یک Negotiation واقعی است (`CloseCoalitionAction`)، یک کپی مستقل فقط بلافاصله دوباره تبدیل می‌شد؛ برخلاف Money مستقل هر دامنهٔ دیگر (که مقداری واقعاً مال خودشان را نگه می‌دارند). سازمان‌دهنده خودش اولین عضو Coalition است (کمیت خودش هم در سفارش انبوه حساب می‌شود).

**بستن Coalition:** وقتی حداقل تعداد عضو رسید، `CloseCoalitionAction` کمیت همهٔ اعضا را جمع می‌زند، تخفیف را روی قیمت واحد اعمال می‌کند (`Coalition::discountedUnitPrice()`)، و دقیقاً یک `Negotiation` واقعی با `InitiateNegotiationAction` موجود باز می‌کند (Extend, Don't Rebuild — بدون مکانیزم «معاملهٔ انبوه» موازی؛ CostGate آن Action هم رایگان اعمال می‌شود، بدون تکرار). تخفیف واقعاً اعمال‌شدن، تضمین‌شده نیست — تأمین‌کننده هدف هنوز باید مثل هر Negotiation دیگری propose/counter/accept/reject کند (همان صداقت Escrow).

**بستن حلقه:** `CompleteCoalitionOnNegotiationAcceptedListener` روی `NegotiationWasAccepted` گوش می‌دهد و اگر آن Negotiation متعلق به یک Coalition بود، آن را Completed می‌کند — no-op برای اکثریت قریب‌به‌اتفاق Negotiationهای غیرمرتبط. **محدودیت مستند:** چون هیچ `NegotiationWasRejected` در کدبیس وجود ندارد (Phase 2/M3)، رد شدن معاملهٔ انبوه هیچ سیگنال خودکاری ندارد — `CancelCoalitionAction` مسیر خروج دستی سازمان‌دهنده است.

**فایل‌های اصلی:** `Coalition`/`CoalitionMember` (Entity/Repo/Model)، ۷ Action (Create/Join/Leave/Get/ListOpen/Close/Cancel)، ۶ capability (`nexus.coalition.{create,join,list,close,leave,cancel}`)، صفحات `/nexus/growth/coalitions`.

**تست:** ۳۰ تست جدید — شامل یک سناریوی کامل واقعی (بدون mock): تشکیل → پیوستن → بستن → پذیرش Negotiation توسط تأمین‌کننده → Coalition واقعاً Completed — همه پاس.

**کامیت:** `feat(nexus): add Group Buying coalitions (bulk-discount negotiation)`.

---

## Phase 5 / M4 — Network Visualization (نمایش گراف شبکه)

**تصمیم کلیدی:** دامنهٔ جدیدی ساخته نشد — `NetworkQuery` به دامنهٔ *موجود* Marketplace اضافه شد (همان الگوی `BusinessSearchQuery`: یک Query class ساده، نه Repository، چون Marketplace اصلاً جدول خودش ندارد). «رابطه» فقط یک واقعیتِ واقعاً ثبت‌شده است — یک Negotiation Accepted، یا عضویت مشترک در یک Coalition — هرگز یک امتیاز affinity ساختگی (همان صداقتی که `RankSuppliersAction` قبلاً دربارهٔ نبود Reputation اعمال کرده بود).

**گراف محدود، نه کامل پلتفرم:** `GetBusinessNetworkAction` شرکای مستقیم + هم‌عضوهای Coalition + یک گام جلوتر («شرکای شرکای شما») را می‌سازد، با سقف‌های صریح (حداکثر ۵ شریک بسط‌داده‌شده، حداکثر ۱۰ پیشنهاد) — یک گراف قابل‌خواندن و bounded، نه یک BFS کامل روی کل گراف مذاکرات پلتفرم.

**بدون وابستگی JS جدید:** صفحهٔ `/nexus/network` یک گراف رادیال ساده با SVG درون‌خطی و کمی جاوااسکریپت خام رسم می‌کند (مختصات با مثلثات ساده محاسبه می‌شوند) — دقیقاً همان فلسفهٔ «بدون اختراع زیرساخت جدید» که Phase 2/M7 برای polling به‌جای WebSocket دنبال کرد.

**فایل‌های اصلی:** `NetworkQuery`، `GetBusinessNetworkAction`، `BusinessNetworkData`، capability `nexus.marketplace.network` (رایگان)، صفحهٔ `/nexus/network`.

**تست:** ۱۰ تست جدید (شامل تست صریح «شریک مستقیم هرگز دوباره به‌عنوان پیشنهادی لیست نمی‌شود») — همه پاس.

**کامیت:** `feat(nexus): add Network Visualization (Marketplace domain extension)`.

---

## Phase 5 / M5 — Viral Analytics (K-factor، Cohort، A/B Test)

**تصمیم کلیدی:** `GrowthAnalyticsQuery` به دامنهٔ *موجود* Analytics اضافه شد (همان الگوی `RevenueQuery`: Query class ساده، نه Repository، برای خواندن چندلایه). K-factor با فرمول استاندارد محاسبه می‌شود: (میانگین دعوت ارسالی به‌ازای هر کسب‌وکار دعوت‌کننده) × (نرخ تبدیل آن دعوت‌ها) — صفر بدون داده، نه خطای تقسیم‌بر‌صفر (همان صداقت `RevenueCalculatorTest` برای نرخ رشد).

**یافتهٔ صادقانه:** جدول `businesses` ستون `verified_at` ندارد (`VerifyBusinessAction` فقط `verification_status` را تغییر می‌دهد) — پس Cohort بر اساس **هفتهٔ ثبت‌نام** (`created_at`) گروه‌بندی شد، نه «هفتهٔ تأیید» که سند roadmap به آن اشاره کرده بود؛ سیگنال واقعاً موجود، نه یکی ساختگی.

**A/B Testing:** `Invite::messageVariant` (پیش‌فرض `'a'`) از M2 از قبل وجود داشت دقیقاً برای همین — `inviteVariants()` نرخ تبدیل را به‌ازای هر نسخه پیام گروه‌بندی می‌کند.

**فایل‌های اصلی:** `GrowthAnalyticsQuery`، `GetGrowthDashboardAction`، `NexusGrowthController`، صفحهٔ ادمین `/dashboard/nexus/growth` (گارد `auth`/`admin` هستهٔ پلتفرم، نه Jarvis — همان مرز Phase 1/M1).

**تست:** ۷ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Viral Analytics (K-factor, cohorts, A/B test dashboard)`.

---

## Phase 5 / M6 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: همهٔ ۵ migration جدید (`nexus_referral_codes`, `nexus_referral_signups`, `nexus_invites`, `nexus_coalitions`, `nexus_coalition_members`) تمیز اجرا شدند.
- `php artisan test` کامل: **۱۲۲۵ pass / ۲۸۳ fail** — بدون رگرشن (baseline قبل از Phase 5: ۱۱۴۵ pass؛ همان ۲۸۳ شکست ثابت ماژول‌های غیرفعال، بدون تغییر، در هر مرحله تأیید شد).
- تست End-to-End دستی کامل روی دیتابیس واقعی (از طریق `php artisan tinker <file>` — همان جایگزین مستندشدهٔ کلیک مرورگری از Phase 2/3/4 به‌خاطر مشکل شناخته‌شدهٔ رزرو پورت ویندوز؛ این‌بار یک نکتهٔ ابزاری تازه هم کشف شد: فایل باید با `<?php` شروع شود وگرنه `tinker` به‌جای اجرا، فقط سورس را echo می‌کند):
  1. ثبت‌نام Root Co → تأیید → کد معرفی خودکار صادر شد (`REF-KD1IA3`).
  2. ثبت‌نام Referee Co با آن کد → تأیید → هر دو طرف واقعاً پاداش گرفتند (۲۰۰/۱۰۰ کردیت)، `tier1Count=1`.
  3. Root Co یک Invite واقعی به یک ایمیل بیرونی فرستاد (`status=sent`) → یک کسب‌وکار سوم (Lead Co) با همان کد ثبت‌نام کرد → Invite واقعاً `status=converted` شد.
  4. Root Co یک Coalition واقعی علیه یک تأمین‌کننده تشکیل داد (تخفیف ۱۵٪) → Lead Co پیوست → بسته شد (یک Negotiation واقعی با کمیت تجمیعی ۱۵ و قیمت تخفیف‌خورده باز شد) → تأمین‌کننده پذیرفت → Coalition واقعاً `completed` شد.
  5. گراف شبکهٔ Root Co درست بود: تأمین‌کننده به‌عنوان `direct` (از Negotiation پذیرفته‌شدهٔ Coalition)، Lead Co به‌عنوان `coalition`.
  6. داشبورد Viral Analytics روی همین داده‌های واقعی: K-factor=۱، یک دعوت ارسالی، یک دعوت تبدیل‌شده، نرخ تبدیل ۱۰۰٪ — دقیقاً مطابق محاسبهٔ دستی.
- `php artisan route:list`: هر ۱۳ مسیر جدید (`nexus/growth/{referrals,invites,coalitions*}`, `nexus/network`, `dashboard/nexus/growth`) درست ثبت شده‌اند؛ ترتیب `coalitions/create` قبل از `coalitions/{coalition}` تأیید شد (بدون تداخل روت).
- `git log --oneline`: هر ۵ مرحلهٔ Phase 5 (M1 تا M5) کامیت شده‌اند.

**کامیت:** `docs(nexus): Phase 5 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 5 (Viral Growth Engine) — تکمیل شد

| دامنه | Entity/Service اصلی | Action‌های کلیدی | MCP Capability | تست |
|---|---|---|---|---|
| Growth (Referral) | `ReferralCode`, `ReferralSignup` | Issue, RecordSignup, GetStatus | `nexus.referral.status` | ۲۰ |
| Growth (Invite) | `Invite` | SendAgentInvite, ListSent | `nexus.invite.send` | ۱۶ |
| Growth (Coalition) | `Coalition`, `CoalitionMember` | Create, Join, Leave, Close, Cancel, ListOpen | `nexus.coalition.{create,join,list,close,leave,cancel}` | ۳۰ |
| Marketplace (Network) | — (read model) | GetBusinessNetwork | `nexus.marketplace.network` | ۱۰ |
| Analytics (Growth) | — (read model) | GetGrowthDashboard | — | ۷ |
| **مجموع** | | | | **~۸۳ تست نوشته‌شده / خالص +۸۰ در کل سوییت** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **دامنهٔ جدید فقط وقتی که هیچ‌کدام از ۱۰ دامنهٔ اولیه جا نمی‌افتد** — همان استثنایی که Phase 4 برای `Llm` گرفت، این‌بار برای `Growth` (Referral/Invite/Coalition) تکرار شد؛ read-modelهای صرف (Network، Viral Analytics) به‌جای دامنهٔ جدید، به دامنه‌های موجود اضافه می‌شوند.
2. **پاداش/تبدیل همیشه روی یک رویداد واقعی و قابل‌اثبات پرداخت می‌شود، نه یک نیت** — پاداش معرفی روی Verified، تبدیل Invite روی ثبت‌نام واقعی با همان کد — هیچ‌کدام روی «ایمیل باز شد» یا «کلیک شد» (که این کدبیس اصلاً نمی‌تواند ردیابی کند).
3. **وقتی هدف یک Action دیگر دقیقاً یک نوع داده را نیاز دارد، VO آن دامنه را دوباره‌استفاده کن، کپی نکن** — Coalition مستقیماً `Money`/`CatalogItemType` خودِ Negotiation را استفاده کرد، برخلاف الگوی معمول «هر دامنه Money خودش را می‌سازد» (Phase 1 خلاصه، قاعدهٔ ۴) — چون Coalition ذاتاً به یک Negotiation واقعی ختم می‌شود، نه یک مقدار پولی مستقل.
4. **نبود یک ستون/رویداد، دلیلی برای ساختن یکی ساختگی نیست** — Cohort بر اساس هفتهٔ ثبت‌نام (نه تأیید، چون `verified_at` وجود ندارد)؛ لغو Coalition از حالت Negotiating دستی ماند (چون `NegotiationWasRejected` وجود ندارد) — هر دو مستند، نه سهل‌انگاری.
5. **محدودیت شناخته‌شدهٔ تکرارشونده (از Phase 2/3):** حل اختلاف/لغو دستی هنوز به هر دو طرف اجازه می‌دهد (Coalition اینجا هم همین الگو را دارد)؛ سخت‌گیرانه‌کردن آن کاندید فازهای بعدی باقی می‌ماند.

**آماده برای Phase 6 (Trust & Reputation)** طبق `docs/nexus-roadmap.md`.

---

## بین Phase 5 و Phase 6 — رفع دغدغه‌های مستند تا Phase 5

**دستور:** «هر چی لازم داره که دغدغه است به نظرت تا فاز 5 حلش کن» — بدون کدنویسی تازه برای Phase 6، سه مورد که خود این لاگ به‌صراحت به‌عنوان «محدودیت شناخته‌شده، کاندید فاز بعد» چندبار تکرار کرده بود، انتخاب و حل شدند.

**۱) تنگ‌کردن Pending Approval negotiation (تکرارشده در Phase 2/M4، Phase 3 §7، Phase 5 §5):** `Negotiation` تا این لحظه هیچ رکوردی از اینکه کدام طرف باعث توقف در `pending_approval` شده نداشت — `isParty()` به هر دو طرف اجازه Approve/Reject می‌داد. ستون جدید nullable `pending_approval_business_id` روی `negotiations` اضافه شد؛ `AcceptDealAction` همان لحظه‌ای که `requestApproval()` را صدا می‌زند (جایی که `$actingBusinessId` را از قبل در دست دارد) این مقدار را ست می‌کند. `ApprovePendingNegotiationAction`/`RejectPendingNegotiationAction` حالا به‌جای `isParty()` دقیقاً همین فیلد را چک می‌کنند. Viewer (کنترلر/DTO/Blade/ترجمه‌ها) هم به‌روزرسانی شد: طرفی که آستانه‌اش رد نشده، دیگر دکمه‌های تأیید/رد را نمی‌بیند — پیام «در انتظار تأیید طرف مقابل» می‌بیند.

**۲) تنگ‌کردن Escrow Release (تکرارشده در Phase 3/M4، Phase 3 §7، Phase 5 §5):** بررسی نشان داد در تمام کدبیس (هر تست، هر جریان واقعی) قرارداد `initiator = خریدار`/`counterparty = فروشنده` است، هرچند `InitiateNegotiationAction` صراحتاً این را enforce نمی‌کند. `ReleaseEscrowAction` («تأیید تحویل») حالا `NegotiationRepositoryInterface` را تزریق می‌کند و فقط `initiatorBusinessId()` (خریدار) را می‌پذیرد — چون فقط خریدار می‌تواند صادقانه تأیید کند که چیزی دریافت کرده؛ ریسک واقعی این بود که فروشنده بتواند escrow را یک‌طرفه به نفع خودش آزاد کند. `DisputeEscrowAction` عمداً دست‌نخورده ماند (هر دو طرف)، چون اعتراض برخلاف Release یک ادعا است، نه یک انتقال دارایی — منطقاً هر دو طرف باید بتوانند مطرحش کنند. دکمهٔ «تأیید تحویل» در Viewer هم فقط برای initiator نمایش داده می‌شود.

**۳) وصل‌کردن هزینهٔ واقعی LLM به Net Revenue (Phase 3/M6's docblock صراحتاً این را «قلمرو Phase 4» خوانده بود، ولی Phase 4 که تمام شد هیچ‌کس این را وصل نکرده بود):** متد جدید `LLMUsageQuery::sumRealCostUsdForRange()` (هم‌شکل `RevenueQuery::applyRange`) اضافه شد — عمداً `real_cost_usd` را جمع می‌زند، نه `charged_cost_usd`: بررسی کدبیس نشان داد `charged_cost_usd` (شامل markup) هیچ‌جا واقعاً به هیچ Businessی شارژ نمی‌شود (هیچ CostGate/`SpendCreditsForActionAction`ای به آن ارجاع نمی‌دهد)، پس تنها هزینهٔ واقعی پلتفرم همان `real_cost_usd` (چیزی که واقعاً به provider پرداخت شده) است. `GetRevenueDashboardAction` این را با همان الگوی تبدیل ارز `LLMBudgetGuard` (`× usd_to_irt_rate`) به تومان تبدیل می‌کند و از `grossRevenue` کم می‌کند؛ کلید جدید `llmCost` (`amountUsd`/`amountIrt`) به خروجی اضافه شد. داشبورد ادمین (`resources/views/dashboard/nexus/revenue/index.blade.php`) این رقم را نمایش می‌دهد.

**فایل‌های اصلی:** migration جدید `..._add_pending_approval_business_id_to_negotiations_table.php`، `Negotiation` entity/Model/Repository، `AcceptDealAction`/`ApprovePendingNegotiationAction`/`RejectPendingNegotiationAction`، `NegotiationData` DTO، `ReleaseEscrowAction`، `resources/views/nexus/negotiations/show.blade.php`، `LLMUsageQuery`، `GetRevenueDashboardAction`، `resources/views/dashboard/nexus/revenue/index.blade.php`، کلیدهای ترجمهٔ جدید در `lang/{fa,en}/messages.json`.

**تست:** ۴ تست جدید (۲ روی محدودیت Pending Approval، ۱ روی محدودیت Escrow Release، ۱ روی صحت Net Revenue با هزینهٔ واقعی LLM — شامل اثبات صریح که `charged_cost_usd` عمداً استفاده نمی‌شود)، به‌علاوه به‌روزرسانی چند تست موجود که قبلاً روی رفتار سهل‌گیرانهٔ قدیم تکیه کرده بودند (`NegotiationTest` روی امضای جدید `requestApproval()`، `EscrowActionsTest`/`GetRevenueDashboardActionTest` روی release توسط seller). سوییت کامل: **۱۲۲۹ pass / ۲۸۳ fail** — بدون رگرشن (baseline Phase 5: ۱۲۲۵ pass؛ خالص +۴).

**کامیت:** `fix(nexus): tighten pending-approval/escrow authorization, wire real LLM cost into net revenue`.

**محدودیت شناخته‌شدهٔ باقی‌مانده که عمداً دست‌نخورده ماند:** حل واقعی Dispute (evidence/mediation/arbitration) هنوز فقط `RefundEscrowAction` دستی ادمین است — این صراحتاً قلمرو Phase 6 (Trust & Reputation) است، نه یک دغدغهٔ جامانده از فازهای قبل.

---
