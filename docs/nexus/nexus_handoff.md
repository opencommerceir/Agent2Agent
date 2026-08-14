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

# Phase 6 — Trust & Reputation

**دستور:** «برو سراغ فاز 6» (بعد از تأیید رفع دغدغه‌های Phase 5). قبل از کدنویسی، دو Explore agent موازی روی الگوهای موجود (Notification/MCP/NexusServiceProvider یکی، Dispute/Fraud/Verification دیگری) اجرا شد. یافتهٔ کلیدی مشترک هر دو: **هیچ کد review/rating/reputation/moderation از قبل در کدبیس وجود نداشت** — این فاز کاملاً از صفر ساخته شد. یافتهٔ دوم: `ReleaseEscrowAction` (Phase 3) هیچ‌وقت event دیسپچ نمی‌کرد — نبود یک نقطهٔ اتصال دقیق برای «معامله واقعاً کامل شد» که این فاز به آن نیاز داشت.

## Phase 6 / M1 — Reviews & Ratings (Reputation domain core)

**تصمیم کلیدی (نقطهٔ محرک):** بازتریگر Review نه `NegotiationWasAccepted` (که فقط یعنی توافق شد، نه لزوماً تحویل شد) بلکه یک event جدید `EscrowWasReleased` است — دیسپچ‌شده از `ReleaseEscrowAction` درست بعد از `$escrow->release()`. این صادقانه‌ترین نقطهٔ «معامله واقعاً تمام شد» است، دقیقاً همان استانداردی که Phase 3/M6's Revenue Dashboard قبلاً برای «released نه held» تثبیت کرده بود.

`Review` یک state machine کوچک است (`published` → `flagged` → `published|removed`، `removed` نهایی) — نه یک رکورد ساده مثل `CreditTransaction`، چون محتوا (rating/comment) بعد از ثبت هرگز تغییر نمی‌کند ولی وضعیت (برای Moderation) باید بتواند تغییر کند. یک محدودیت یکتای دیتابیس (`negotiation_id`+`reviewer_business_id`) + یک چک صریح در `SubmitReviewAction` مانع دو-بار-نظردادن یک طرف روی یک معامله می‌شود. `FlagReviewAction` فقط به‌دست کسی که review دربارهٔ اوست قابل فراخوانی است (نه reviewer)، `RestoreReviewAction`/`RemoveReviewAction` ادمین‌محورند.

**فایل‌های اصلی:** `app/Domains/Nexus/Reputation/{Domain,Application,Infrastructure,Interfaces}/**` (`Review`, `ReviewRepositoryInterface`, `SubmitReviewAction`, `FlagReviewAction`, `RestoreReviewAction`, `RemoveReviewAction`, `ListReviewsForBusinessAction`)، `app/Domains/Nexus/Contract/Domain/Events/EscrowWasReleased.php`، migration `nexus_reviews`، capability‌های `nexus.review.{submit,list}`، فرم review روی `negotiations/show.blade.php`.

**تست:** ۲۱ تست جدید (Unit روی state machine، Feature روی Submit/Moderation/MCP/Viewer) — همه پاس.

**کامیت:** `feat(nexus): add Reviews & Ratings (Reputation domain core)`.

---

## Phase 6 / M2 — Reputation Score (0-1000، badges) به‌عنوان یک read-model محاسبه‌شده

**تصمیم کلیدی:** هیچ جدول `reputation_scores` ساخته نشد — امتیاز کاملاً زنده از داده‌های موجود محاسبه می‌شود (`ReputationQuery` هم‌الگوی `RevenueQuery`/`GrowthAnalyticsQuery`)، دقیقاً همان فلسفهٔ «Query class روی داده‌های موجود، نه تکرارش در یک ردیف mutable جدید». فرمول: `successRate × ۵۰۰ + (میانگین امتیاز/۵) × ۴۰۰ + (طول عمر/۱۲ماه، سقف‌دار) × ۱۰۰`، وزن‌ها در `config('nexus.platform.reputation.weights')` قابل تنظیم. کامپوننت امتیاز وقتی هیچ review ای وجود ندارد **صفر** است، نه یک میانگین خنثی ۲.۵ — امتیاز باید کسب شود، رایگان شروع نمی‌شود.

**Badges مشتق‌شده‌اند، ذخیره نمی‌شوند:** `verified` مستقیماً `Business::isVerified()` (Phase 1) را دوباره‌استفاده می‌کند (نه یک مفهوم دوم verified)، `top_rated`/`gold_partner` صرفاً چک آستانه روی همان اعدادِ همین‌جا محاسبه‌شده‌اند.

**عمداً حذف‌شده و مستند:** سیگنال «زمان پاسخ‌گویی» که roadmap نام می‌برد ساخته نشد — هیچ‌چیزی در کدبیس تأخیر پاسخ واقعی یک طرف را صادقانه ثبت نمی‌کند (یک پیام جدید فقط ثابت می‌کند «کسی» جواب داد، نه اینکه طرف درست پاسخگو بود)؛ همان صداقت «نبود ستون/رویداد دلیلی برای ساختن یکی ساختگی نیست» که Phase 5/M5 قبلاً برای `verified_at` رعایت کرده بود.

**فایل‌های اصلی:** `ReputationQuery`، `CalculateReputationScoreAction`، `ReputationScoreData` DTO، بخش جدید `reputation` در `config/nexus/platform.php`، capability رایگان `nexus.reputation.score`، نمایش امتیاز/badge روی داشبورد کسب‌وکار و کنار نام طرف مقابل در Negotiation Viewer.

**تست:** ۸ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Reputation Score (0-1000, badges) as a computed read-model`.

---

## Phase 6 / M3 — Dispute Resolution (evidence، mediation، arbitration)

**تصمیم کلیدی:** `DisputeEscrowAction` خودِ کدش (از Phase 2) صراحتاً نوشته بود «این نقطهٔ اتصال Phase 6 است» — این مرحله دقیقاً همان‌جا وصل شد: یک event جدید `EscrowWasDisputed` دیسپچ می‌شود، و `OpenDisputeCaseOnEscrowDisputedListener` یک `DisputeCase` واقعی باز می‌کند (evidence log، وضعیت `open → mediation → resolved`). `Escrow::ALLOWED_TRANSITIONS` توسعه یافت تا `disputed → released` هم مجاز باشد (قبلاً فقط `disputed → refunded`) — یعنی داور می‌تواند به نفع فروشنده هم رأی دهد.

**`ArbitrateDisputeAction` عمداً `RefundEscrowAction`/`ReleaseEscrowAction` موجود را دوباره‌استفاده نمی‌کند** — چون authorization آن دو (ادمین بدون acting-business / فقط initiator) اینجا صدق نمی‌کند؛ این یک override اداری روی جریان عادی است. وقتی رأی به نفع فروشنده باشد (`release_seller`)، همان `EscrowWasReleased` که M1 برایش گوش می‌دهد دوباره دیسپچ می‌شود — یعنی حتی یک معاملهٔ مورد اختلاف، بعد از حل‌شدن، همان مسیر عادی Review را باز می‌کند.

**اثر روی Reputation:** `ReputationQuery::disputesLostCount()` شمار DisputeCaseهایی که واقعاً علیه یک Business حل شده‌اند را می‌شمارد (نه صرفاً درگیربودن در دعوا) و `CalculateReputationScoreAction` یک جریمهٔ سقف‌دار (`config('...dispute_penalty_per_loss/max')`) بعد از جمع سه مؤلفهٔ اصلی کم می‌کند.

**فایل‌های اصلی:** `app/Domains/Nexus/Contract/{Domain,Application}/**` (`DisputeCase`, `OpenDisputeCaseAction`, `SubmitDisputeEvidenceAction`, `MoveDisputeToMediationAction`, `ArbitrateDisputeAction`)، migration `nexus_dispute_cases`، `NexusDisputeController` ادمین (`/dashboard/nexus/disputes`)، فرم evidence روی Negotiation Viewer.

**تست:** ۲۱ تست جدید (Unit روی state machine، Feature روی زنجیرهٔ رویداد واقعی + هر دو مسیر arbitration + تأثیر روی reputation + کنترلر ادمین) — همه پاس.

**کامیت:** `feat(nexus): add Dispute Resolution (evidence, mediation, arbitration)`.

---

## Phase 6 / M4 — Fraud Detection، تعلیق Business، و Appeal

**تصمیم کلیدی (جداسازی از Verification):** `Business` یک فیلد `status` (`active`/`suspended`) جدید و **کاملاً مستقل** از `verificationStatus` (Phase 1) گرفت — یک Business می‌تواند هم‌زمان Verified و Suspended باشد؛ تقلب هویت تأییدشده را باطل نمی‌کند، فقط اجازهٔ معامله را می‌گیرد. الگوی این جداسازی از پیش در کدبیس وجود داشت: `Agent` هستهٔ Core همین شکل (`active`/`inactive`/`suspended`) را دارد.

**`SuspensionRecord` یک ledger غیرقابل‌تغییر واحد برای هر دو مسیر تعلیق (دستی ادمین و خودکار fraud) است** — نه دو جدول جدا؛ فیلد `triggeredBy` تمایز می‌گذارد. همان فلسفهٔ «بدون AuditLog عمومی، یک ledger مخصوص دامنه» که `CreditTransaction`/`LLMUsageLog` قبلاً تثبیت کرده بودند.

**نقطهٔ بستن دسترسی، یکی و مرکزی است:** `ResolveActingBusinessAction` — همان جایی که هر Capability MCP نکسوس از قبل برای فهمیدن «کدام Business تماس می‌گیرد» از آن عبور می‌کند — حالا اگر Business غیرفعال باشد `PermissionDeniedException` هستهٔ Core را پرتاب می‌کند (که `MCPExceptionHandler` از قبل به ۴۰۳ نگاشت می‌کند) — یعنی با یک تغییر در یک فایل، **هر** Capability نکسوس برای یک Agent تعلیق‌شده مسدود شد، نه یک‌به‌یک.

**Fraud Detection صرفاً Rule-based است** (مطابق پیش‌فرض «Rule Engine ۸۰٪، بدون هزینه» — هیچ زیرساخت ML/anomaly-detection در این کدبیس وجود ندارد): تنها قانون، ۳+ دعوای واقعاً حل‌شده علیه یک Business در ۳۰ روز اخیر است (`FraudSignalQuery`) — نه صرف تعداد دعوا (چون بردن یک دعوا چیزی ثابت نمی‌کند)، نه نرخ رد پیشنهاد (چون رد پیشنهاد بد، مذاکرهٔ سالم است نه تقلب). `DetectFraudSignalsAction` هر ساعت از طریق `Schedule::command()` واقعی در `routes/console.php` اجرا می‌شود (همان زیرساخت واقعی که `ExpireLoyaltyPointsCommand`/`ProcessDueSubscriptionsCommand` از قبل استفاده می‌کنند)، به‌علاوه یک دکمهٔ «اجرای فوری» در پنل ادمین.

**Appeal:** یک state machine کوچک (`pending → approved|denied`)، فقط وقتی Business واقعاً تعلیق است قابل ثبت است. تأیید Appeal مستقیماً `ReactivateBusinessAction` (همان نقطهٔ واحد فعال‌سازی) را صدا می‌زند.

**فایل‌های اصلی:** `Business` entity (+`BusinessStatus`)، `SuspensionRecord`/`SuspensionAppeal` (+repositories)، `SuspendBusinessAction`/`ReactivateBusinessAction`/`SubmitSuspensionAppealAction`/`ResolveSuspensionAppealAction`، `FraudSignalQuery`/`DetectFraudSignalsAction` (دامنهٔ Reputation)، `DetectFraudSignalsCommand` + entry در `routes/console.php`، `NexusFraudController` ادمین (`/dashboard/nexus/fraud`)، بنر تعلیق + فرم appeal روی داشبورد کسب‌وکار.

**تست:** ۲۲ تست جدید (Unit روی `SuspensionAppeal`، Feature روی suspend/reactivate/appeal/۴۰۳-block-روی-MCP/fraud-detection-idempotent/کنترلر ادمین) — همه پاس.

**کامیت:** `feat(nexus): add Fraud Detection, Business suspension, and appeals`.

---

## Phase 6 / M5 — Verification System (Products/Services + اولین صف Business)

**تصمیم کلیدی:** `ListingVerificationStatus` یک enum مستقل در دامنهٔ Catalog است (`pending`/`verified`/`rejected`) — نه دوباره‌استفاده از `VerificationStatus` خودِ Business، طبق قاعدهٔ همیشگی «هر دامنه VO خودش را می‌سازد» (سابقهٔ Money، Phase 1 خلاصه). سومین حالت (`Rejected`) که Business ندارد چون یک محصول می‌تواند صریحاً رد شود بدون اینکه کل Business غیرمعتبر شود.

**یافتهٔ صادقانهٔ غیرمنتظره:** هیچ‌جای کدبیس (کنترلر، روت) `VerifyBusinessAction` را از یک UI ادمین صدا نمی‌زد — تأیید Business از Phase 1 تا امروز فقط از طریق `tinker` دستی در تست‌های E2E انجام می‌شد! این مرحله **اولین UI ادمین واقعی** برای آن ساخت (`NexusVerificationController::verifyBusiness`)، کنار صف محصولات/خدمات — یک شکاف واقعی جامانده از Phase 1 که Phase 6 طبیعتاً بست، نه یک اسکوپ‌کریپ.

**`verified` روی نتایج Marketplace سوار شد:** `BusinessSearchQuery` حالا `verification_status` هر Product/Service را می‌خواند و یک پرچم `verified` boolean به خروجی هر آیتم اضافه می‌کند — یعنی یک Agent قبل از پیشنهاددادن می‌تواند اعتبار خودِ آیتم (نه فقط اعتبار کل Business) را بسنجد.

**فایل‌های اصلی:** `ListingVerificationStatus`، `Product`/`Service` entities (+`verify()`/`reject()`/`isVerified()`)، `VerifyProductAction`/`RejectProductAction`/`VerifyServiceAction`/`RejectServiceAction`، `findByVerificationStatus()` روی هر سه repository (`Product`, `Service`, `Business`)، `NexusVerificationController` (`/dashboard/nexus/verification`)، به‌روزرسانی `BusinessSearchQuery`.

**تست:** ۱۷ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Verification System (Products/Services + first Business queue)`.

---

## Phase 6 / M6 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: همهٔ ۷ migration جدید Phase 6 (`nexus_reviews`, `nexus_dispute_cases`, `businesses.status`, `nexus_suspension_records`, `nexus_suspension_appeals`, `nexus_products/services.verification_status`) تمیز اجرا شدند.
- `php artisan test` کامل: **۱۳۱۸ pass / ۲۸۳ fail** — بدون رگرشن (baseline قبل از Phase 6: ۱۲۲۹ pass از رفع دغدغه‌های بین‌فازی؛ +۸۹ تست پاس دقیقاً برابر ۲۱+۸+۲۱+۲۲+۱۷ تست *واقعاً جدید* M1 تا M5 — برخلاف فازهای قبل، این‌بار هیچ تست موجودی جایگزین نشد، همه‌چیز خالص افزوده است؛ همان ۲۸۳ شکست ثابت ماژول‌های غیرفعال، بدون تغییر).
- تست End-to-End دستی کامل روی دیتابیس واقعی (`php artisan tinker` روی یک اسکریپت PHP، همان جایگزین مستندشدهٔ کلیک مرورگری از Phase 2 به‌خاطر مشکل شناخته‌شدهٔ رزرو پورت ویندوز)، زنجیرهٔ کامل و واقعی:
  1. ثبت‌نام و تأیید دو کسب‌وکار واقعی (Buyer/Seller).
  2. معاملهٔ اول: Propose → Accept → Release → هر دو طرف review دادند (۵/۵) → امتیاز اعتبار فروشنده: **۹۰۰/۱۰۰۰** (successRate=۱ × ۵۰۰ + rating=۵/۵ × ۴۰۰ + longevity=۰ × ۱۰۰)، badge=`verified`.
  3. سه معاملهٔ بعدی: هرکدام Accept → Dispute → Arbitrate با `refund_buyer` (رأی علیه فروشنده) — واقعاً روی Escrow واقعی، نه mock.
  4. امتیاز فروشنده بعد از ۳ دعوای باخته: **۷۵۰/۱۰۰۰** — دقیقاً `۹۰۰ - min(۳×۵۰, ۳۰۰) = ۹۰۰-۱۵۰=۷۵۰`، فرمول جریمه صحیح تأیید شد روی داده‌های واقعی.
  5. `DetectFraudSignalsAction::execute()` فروشنده را با آستانهٔ پیش‌فرض (۳ دعوای باخته در ۳۰ روز) واقعاً auto-suspend کرد؛ Business status واقعاً `suspended` شد.
  6. Appeal واقعی ثبت شد → ادمین تأیید کرد → `ReactivateBusinessAction` واقعاً صدا خورد → Business status واقعاً به `active` برگشت.
  7. یک محصول جدید اضافه و تأیید شد → در نتیجهٔ واقعی `SearchMarketplaceAction` پرچم `verified: true` درست ظاهر شد.
- `git log --oneline`: هر ۵ مرحلهٔ Phase 6 (M1 تا M5) + رفع دغدغه‌های بین‌فازی، کامیت شده‌اند.

**کامیت:** `docs(nexus): Phase 6 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 6 (Trust & Reputation) — تکمیل شد

| دامنه | Entity/Service اصلی | Action‌های کلیدی | MCP Capability | تست |
|---|---|---|---|---|
| Reputation (Reviews) | `Review` | Submit, Flag, Restore, Remove, ListForBusiness | `nexus.review.{submit,list}` | ۲۱ |
| Reputation (Score) | — (read model) | CalculateReputationScore | `nexus.reputation.score` | ۸ |
| Contract (Dispute) | `DisputeCase` | Open, SubmitEvidence, MoveToMediation, Arbitrate | — | ۲۱ |
| Business (Suspension) | `SuspensionRecord`, `SuspensionAppeal` | Suspend, Reactivate, SubmitAppeal, ResolveAppeal | — | ۲۲ |
| Reputation (Fraud) | — (read model + rule) | DetectFraudSignals | — | (در ۲۲ بالا) |
| Catalog (Verification) | `ListingVerificationStatus` | VerifyProduct, RejectProduct, VerifyService, RejectService | — | ۱۷ |
| **مجموع** | | | | **۸۹ (پاس، خالص = دقیقاً کل تست‌های تازه)** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **امتیاز/شهرت = read-model محاسبه‌شده زنده، نه یک ردیف mutable ذخیره‌شده** — همان الگوی `RevenueQuery`/`GrowthAnalyticsQuery`، این‌بار برای یک عدد ۰-۱۰۰۰ کامل با badges. هر سیگنال اعتماد آیندهٔ دیگر (مثلاً امتیاز تأمین‌کننده در Phase 8) باید همین الگو را دنبال کند، نه یک جدول snapshot جدید.
2. **Badge/Trust signal همیشه مشتق است، هرگز ذخیره نمی‌شود** — از داده‌های واقعی همین‌لحظه محاسبه می‌شود تا هیچ‌وقت stale نشود.
3. **نقاط اتصال Phase بعدی باید از قبل در کد فعلی مستند شوند** — دقیقاً همان‌طور که `DisputeEscrowAction` (Phase 2) خودش نوشته بود «اینجا نقطهٔ Phase 6 است»، و این فاز دقیقاً همان‌جا وصل شد؛ این الگو را برای هر گپ آگاهانهٔ آینده تکرار کنید.
4. **یک نقطهٔ مرکزی auth بهتر از N نقطهٔ پراکنده است** — تعلیق Business با تغییر یک فایل (`ResolveActingBusinessAction`) کل سطح MCP را بست، نه با افزودن چک به هر Action.
5. **Fraud Detection Rule-based، نه ML** — تا وقتی سیگنال دیگری غیر از «دعوای واقعاً حل‌شده» به‌طور صادقانه در دیتابیس ثبت شود، همین یک قانون کافی است؛ قانون‌های بیشتر باید از رویدادهای واقعی جدید بیایند، نه فرضیات.
6. **زیرساخت scheduling واقعی از قبل وجود دارد** (`Schedule::command()` در `routes/console.php`، با OS cron واقعی) — فازهای بعدی که به job دوره‌ای نیاز دارند نباید فرض کنند باید از صفر اختراعش کنند (یک اشتباه که این فاز هم در ابتدا کرد و خودش اصلاح شد).
7. **محدودیت شناخته‌شدهٔ تکرارشوندهٔ قدیمی (Phase 2/3/5) دیگر باقی نیست برای Negotiation/Escrow** (بین Phase 5 و 6 حل شد)؛ نمونهٔ مشابه جدید در همین فاز: Release/Dispute در سطح Escrow حل شد، ولی **Dispute در سطح DisputeCase هنوز به هر دو طرف اجازهٔ ثبت evidence می‌دهد** (منطقی و عمدی — evidence یک ادعا است نه یک اقدام یک‌طرفه، برخلاف Release).

**آماده برای Phase 7 (Enterprise Features)** طبق `docs/nexus-roadmap.md`.

---

# Phase 7 — Enterprise Features

**دستور:** «برو سراغ فاز 7» (بعد از تأیید Phase 6). طبق روال هر فاز، قبل از کدنویسی چند Explore agent موازی روی الگوهای موجود اجرا شد. پلن ۱۱ مرحله‌ای (M1–M11) روی پنج بخش roadmap منطبق شد: Multi-Business Accounts (M1–M2)، Approval Workflows (M3–M4)، Private Marketplaces (M5)، SSO & Identity (M6–M8)، Compliance (M9–M10)، به‌علاوه یک مرحلهٔ تأیید نهایی (M11).

## Phase 7 / M1 — دامنهٔ Holding (Multi-Business Accounts)

دامنهٔ جدید `Holding` یک ساختار مادر/زیرمجموعه روی Businessهای موجود می‌سازد (invite/accept/reject/remove/leave)، به‌علاوه یک read-model گزارش‌گیری متمرکز (`GetHoldingDashboardAction`) که وضعیت هر subsidiary را کنار هم نشان می‌دهد. Portal-only است — هیچ MCP capability ندارد، دقیقاً همان توجیهی که Admin/Margin/LLM Settings قبلاً داشتند (این یک نگرانی مدیریتی سطح Business است، نه چیزی که یک Agent صدا بزند).

**فایل‌های اصلی:** `app/Domains/Nexus/Holding/{Domain,Application,Infrastructure}/**`، `HoldingController`، دو migration (`nexus_holdings`, `nexus_holding_subsidiaries`).

**تست:** ۲۶ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Holding domain core (Multi-Business Accounts)`.

---

## Phase 7 / M2 — Shared Credit Pool روی Holding (مسیریابی CostGate)

یک Holding می‌تواند pooling کردیت مشترک را فعال کند؛ Businessهای عضو با یک اقدام صریح (`ContributeToPoolAction`) از موجودی خودشان به pool کمک می‌کنند — چیزی خودکار یا ضمنی نیست. `SpendCreditsForActionAction` (خودِ CostGate از Phase 3/M2) حالا اول چک می‌کند آیا Business عضو یک Holding با pooling فعال است؛ اگر نه، دقیقاً همان رفتار قبلی (کسر از موجودی خودِ Business) بدون هیچ تغییری اجرا می‌شود — یک تغییر رفتار صفر برای هر Businessی که عضو هیچ Holdingی نیست.

**باگ واقعی پیدا و رفع شد:** `HoldingController::show()` هیچ چک عضویتی نداشت، برخلاف تمام Actionهای جهش‌دهندهٔ (mutating) دیگر Holding — یک Business بیرونی می‌توانست جزئیات یک Holding دیگر را ببیند. همراه با ساخت این مرحله رفع شد.

**فایل‌های اصلی:** `HoldingCreditPool`/`HoldingCreditPoolTransaction` (Entity/Repo/Model)، `ContributeToPoolAction`/`DeductFromHoldingPoolAction`/`GetHoldingPoolBalanceAction`/`SetCreditPoolingEnabledAction`، سه migration جدید.

**تست:** ۱۱ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Holding shared credit pool (CostGate routing)`.

---

## Phase 7 / M3 — Business Team Members & Roles

یک Business دیگر محدود به یک لاگین Owner نیست — چند عضو نام‌دار با نقش (`Owner`/`Manager`/`Cfo`/`Staff`) می‌توانند لاگین جدا داشته باشند. این مرحله پیش‌نیاز مستقیم Approval Workflows واقعی (M4) است: یک Manager و یک CFO باید افراد واقعاً جدایی باشند که هرکدام بتوانند سطح خودشان را تأیید کنند. `InviteTeamMemberAction` از ماژول *فعال* `Notifications` برای ایمیل رمز موقت استفاده می‌کند (بدون پایپ‌لاین ایمیل دوم)؛ یک مرحلهٔ اجباری «تغییر رمز عبور» قبل از رسیدن عضو تازه‌دعوت‌شده به داشبورد اجرا می‌شود.

**باگ واقعی Laravel پیدا و رفع شد:** یک session gotcha واقعی مربوط به گارد پیش‌فرض حین این مرحله کشف و مستند شد (زمینه‌ساز باگ بزرگ‌تری که در M6 کامل رفع شد).

**فایل‌های اصلی:** `ChangeTeamMemberRoleAction`/`CompleteForcedPasswordChangeAction`/`InviteTeamMemberAction`/`ListTeamMembersAction`/`RemoveTeamMemberAction`، `TeamMemberRole` enum، `BusinessTeamController`، `BusinessPasswordController`.

**تست:** ۱۳ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Business Team Members & Roles`.

---

## Phase 7 / M4 — Multi-Level Approval Workflows (Agent → Manager → CFO)

دامنهٔ جدید `Approval` مکانیزم موجود Phase 2 (یک آستانهٔ واحد `pending_approval`) را به یک زنجیرهٔ قابل‌تنظیم از سطوح role+threshold تعمیم می‌دهد، بدون دست زدن به خودِ `Negotiation` entity. لحظه‌ای که چک `authority_limits` موجود در `AcceptDealAction` یک معامله را متوقف می‌کند، یک `ApprovalRequest` با snapshot سطوح همان لحظه باز می‌شود (تغییر بعدی policy روی درخواست‌های در حال انجام اثر نمی‌گذارد). گام پایانی تأیید/رد دقیقاً همان `ApprovePendingNegotiationAction`/`RejectPendingNegotiationAction` موجود Phase 2 را بدون تغییر دوباره‌استفاده می‌کند. یک Business بدون policy تعریف‌شده دقیقاً همان رفتار پایهٔ Phase 2 را حفظ می‌کند — صفر رگرسیون رفتاری.

**فایل‌های اصلی:** `app/Domains/Nexus/Approval/{Domain,Application,Infrastructure}/**` (`ApprovalPolicy`, `ApprovalRequest`, `ApprovalDecision`)، `ApprovalPolicyController`، به‌روزرسانی یک‌خطی `AcceptDealAction`.

**تست:** ۱۷ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Multi-Level Approval Workflows (Agent -> Manager -> CFO)`.

---

## Phase 7 / M5 — Private Marketplaces (Invite-only، قیمت‌گذاری محرمانه)

دامنهٔ جدید `PrivateMarketplace`: یک گروه invite-only از Businessها که صاحبش اداره می‌کند، با فهرست‌های قیمت‌گذاری‌شدهٔ محرمانهٔ خودشان — همان الگوی «parent aggregate + child membership table»ی که Coalition (Phase 5) و Holding (M1) قبلاً تثبیت کرده بودند. یک `PrivateMarketplaceSearchQuery` مجزا (نه تغییر در `BusinessSearchQuery` خودِ Marketplace) برای غیرعضو همیشه خالی برمی‌گردد — محرمانگی قیمت با همین گیت عضویت اجرا می‌شود، نه رمزنگاری. دو MCP capability واقعی (`nexus.private_marketplace.{search,list_listing}`) به‌علاوه پورتال کسب‌وکار.

**فایل‌های اصلی:** `app/Domains/Nexus/PrivateMarketplace/{Domain,Application,Infrastructure,Interfaces}/**`، سه migration، سیم‌کشی در `NexusServiceProvider`.

**تست:** ۱۴ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Private Marketplaces (invite-only, confidential pricing)`.

---

## Phase 7 / M6 — SSO OAuth Login (Google)، Connector Registry، رفع باگ Session

اولین لاگین OAuth واقعی برای پرتال Business (Google، از طریق `laravel/socialite`، تنها وابستگی تازهٔ این فاز) — هرگز یک Business تازه نمی‌سازد؛ یک حساب موجود که با ایمیل تطبیق پیدا کند، برای لینک‌شدن به وارد کردن دوبارهٔ رمز عبور نیاز دارد (نه فقط یک کلیک تأیید). `SsoProviderRegistry` ششمین کاربرد الگوی Connector این کدبیس است (بعد از ConnectorRegistry/ShippingProviderRegistry/ChannelSenderRegistry/PaymentGatewayRegistry/LLMProviderRegistry).

**باگ واقعی و جدی Laravel پیدا و رفع شد:** `Illuminate\Session\DatabaseSessionHandler` یک `Guard::class` را از یک singleton محدودشده به request حل می‌کند که همان اولین‌بار که هرکسی آن را بخواهد، روی گارد پیش‌فرض `web` قفل می‌شود — یعنی هر لاگین با گارد business بی‌صدا `sessions.user_id = null` ذخیره می‌کرد (یک UPDATE وسط request از نوشتن نهایی session خودِ فریم‌ورک در پایان request جان سالم به در نمی‌برد). راه‌حل واقعی: rebind همان singleton به گارد business بلافاصله بعد از لاگین. صفحهٔ فهرست/لغو Sessionهای فعال حالا مستقیماً `sessions.user_id` را می‌خواند چون بالاخره درست پر می‌شود.

**فایل‌های اصلی:** `FindBusinessOwnerByOauthIdentityAction`/`LinkOauthIdentityToOwnerAction`/`ListMyActiveSessionsAction`/`RevokeSessionAction`، `BusinessOauthController`، `FinishesBusinessLogin` (concern مشترک لاگین)، `SsoProviderRegistry`/`SsoProviderInterface`، `GoogleSsoProvider`.

**تست:** ۹ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add SSO OAuth login (Google), connector registry, session fix`.

---

## Phase 7 / M7 — MFA (TOTP) برای پرتال Business

پیاده‌سازی دستی RFC 6238 TOTP (در برابر بردارهای تست رسمی RFC خودش با SHA1 صحت‌سنجی شد)، کدهای بازیابی یک‌بارمصرف، و یک چالش زمان‌ورود مشترک بین دو مسیر لاگین (رمز عبور و OAuth) از طریق دو helper تازه روی همان `FinishesBusinessLogin` که M6 ساخت. وضعیت راه‌اندازی روی خودِ ردیف owner نگه داشته می‌شود (`mfa_secret` ست شده ولی `mfa_enabled_at` هنوز null یعنی «در حال انجام») نه در session flash یک ویزارد — یعنی رفرش وسط راه‌اندازی هرگز چیزی که قبلاً اسکن شده را باطل نمی‌کند. غیرفعال‌کردن به رمز عبور فعلی نیاز دارد، همان وزنی که لینک‌کردن حساب OAuth (M6) داشت.

**فایل‌های اصلی:** `EnableMfaAction`/`ConfirmMfaSetupAction`/`DisableMfaAction`/`VerifyMfaChallengeAction`، `TotpService` (framework-free)، `BusinessOwnerRecoveryCode`، `BusinessMfaController`/`BusinessMfaChallengeController`.

**تست:** ۱۵ تست جدید (شامل تست واحد کامل `TotpService` روی بردارهای RFC) — همه پاس.

**کامیت:** `feat(nexus): add MFA (TOTP) for the Business portal`.

---

## Phase 7 / M8 — استاب‌های کانکتور SAML/LDAP + داشبورد ادمین SSO

`SamlSsoProvider`/`LdapSsoProvider` کلاس‌های واقعی‌اند که زیر همان `SsoProviderRegistry`ی که Google استفاده می‌کند ثبت می‌شوند (constructor واقعی به‌شکل config، `nexus.platform.sso.{saml,ldap}.*`)، ولی صادقانه از `redirectUrl()`/`handleCallback()` یک `SsoProviderNotConfiguredException` پرتاب می‌کنند — هیچ پکیج SAML/LDAP نصب نیست، هیچ Identity Provider واقعی در این محیط در دسترس نیست. همان ردهٔ محدودیت مستندشدهٔ Stripe-blocked-for-Toman (Phase 3/M3) و endpointهای LLM محلی غیرقابل‌دسترس (Phase 4/M2). یک صفحهٔ ادمین تازه (`/dashboard/nexus/sso-providers`) وضعیت هر سه provider (زنده/استاب) را نشان می‌دهد.

**فایل‌های اصلی:** `SsoProviderNotConfiguredException`، `SamlSsoProvider`/`LdapSsoProvider`، `NexusSsoProvidersController`، بخش `sso` جدید در `config/nexus/platform.php`.

**تست:** ۱۳ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add SAML/LDAP connector stubs + SSO admin dashboard`.

---

## Phase 7 / M9 — مسیر ممیزی زنجیره‌ای هش (Hash-Chained Compliance Audit Trail)

اولین ledger سرتاسری و بین‌دامنه‌ای این کدبیس — یک بازگشت آگاهانه و مستندشده از قاعدهٔ «بدون AuditLog عمومی، آن scope creep است» که هر ledger مخصوص دامنه (از `CreditTransaction`، Phase 3/M1 به بعد) رعایت کرده بود؛ توجیه این‌بار صریح است چون roadmap خودِ Phase 7 برای اولین بار یک ledger hash-chained را الزام کرده. هر ردیف (`AuditLogEntry`) هش خودش را روی فیلدهای خودش به‌علاوه هش ردیف قبلی محاسبه می‌کند (sha256) — یعنی تغییر یا حذف هر ردیف قدیمی، هش هر ردیف بعد از آن را می‌شکند. `VerifyAuditChainIntegrityAction` کل زنجیره را از داده‌های پایدارشده (persisted) دوباره محاسبه و مقایسه می‌کند.

**تصمیم معماری کلیدی (سیم‌کشی بدون دست‌زدن به Core):** به‌جای افزودن audit logging به `CapabilityExecutionService`/`AbstractMCPGatewayController` خودِ Core (که طبق Decision 007 نباید هیچ منطق تجاری داشته باشند)، یک دکوریتور (`AuditingCapabilityHandlerRegistry`) دقیقاً همان‌جایی که `NexusServiceProvider` هر ~۲۰ هندلر MCP را ثبت می‌کند جایگزین `CapabilityHandlerRegistry` واقعی Core می‌شود — یک نقطهٔ تمرکز واحد، نه ویرایش تک‌تک محل‌های `$handlers->register(...)`. همان درسِ «یک نقطهٔ مرکزی بهتر از N نقطهٔ پراکنده است»ی که چک تعلیق Phase 6/M4 قبلاً با `ResolveActingBusinessAction` تثبیت کرده بود.

**جزئیات صحت‌سنجی‌شده حین ساخت:** یک باگ واقعی همان لحظه‌ای که سشن قبلی وسط کار متوقف شد پیدا و رفع شد — ثابت `GENESIS_HASH` (نشانگر «هیچ‌چیز قبل از این نبود» برای اولین ردیف زنجیره) به‌جای ۶۴ کاراکتر صفر (طول دقیق یک هش هگزادسیمال sha256، طبق کامنت خودِ کد)، ۱۰۶ کاراکتر داشت — با `php -r` دقیقاً ۶۴ کاراکتر تولید و جایگزین شد، و یک تست واحد رگرسیون (`test_genesisHash_isExactly64Characters`) اضافه شد تا هرگز دوباره کشف نشود. یک بهبود طراحی دوم هم حین ساخت اضافه شد: `RecordAuditEntryAction` عمداً Business تماس‌گیرنده را مستقل از چک تعلیق حل می‌کند (نه از طریق آن) تا حتی یک تماس رد‌شده (Business معلق) هم *کدام* Business واقعاً بوده را در ledger ثبت کند، نه `businessId=null` — یک audit trail که نتواند بگوید «چه کسی» رد شده بی‌فایده است.

**فایل‌های اصلی:** `app/Domains/Nexus/Audit/{Domain,Application,Infrastructure}/**` (`AuditLogEntry`, `AuditOutcome`, `RecordAuditEntryAction`, `VerifyAuditChainIntegrityAction`, `AuditingCapabilityHandlerRegistry`)، migration `nexus_audit_log_entries`، `NexusAuditController` (`/dashboard/nexus/audit`، با دکمهٔ «بررسی صحت زنجیره»).

**تست:** ۱۶ تست جدید (۷ Unit روی خودِ زنجیره/هش + تشخیص دستکاری، ۵ Feature end-to-end واقعی روی `/mcp/v1/execute` شامل سناریوی تعلیق→رد→ثبت صحیح businessId، ۴ روی کنترلر ادمین) — همه پاس.

**کامیت:** `feat(nexus): add hash-chained Compliance Audit Trail`.

---

## Phase 7 / M10 — اعلام محل نگهداری داده (Data Residency) + داشبورد Compliance

بخش دوم و پایانی همان خط roadmap که M9 شروع کرد («SOC 2 / ISO 27001 آمادگی و Data residency options»).

**Data Residency:** یک Business می‌تواند از داشبورد خودش یک منطقهٔ ترجیحی (ایران/اتحادیهٔ اروپا/آمریکا/GCC/سایر) اعلام کند. این ستون همیشه nullable است و هرگز مقدار پیش‌فرض نمی‌گیرد — این پلتفرم امروز روی یک پایگاه‌دادهٔ تک‌منطقه‌ای اجرا می‌شود، پس داکبلاک خودِ `DataResidencyRegion` صریح است که این فقط یک ترجیح برای گزارش‌دهی انطباق را ثبت می‌کند، نه جای فیزیکی واقعی داده (زیرساخت منطقه‌ای واقعی، قلمرو «Regional Compliance» در Phase 10 است). همان ردهٔ محدودیت مستندشده‌ی Escrow (Phase 3/M4) و استاب‌های SAML/LDAP (M8).

**داشبورد Compliance (`/dashboard/nexus/compliance`):** یک نمای خودارزیابی، صریحاً برچسب‌گذاری‌شده به همین عنوان نه ادعای گواهی‌نامه. `GetComplianceOverviewAction` زنده از چهار دامنه می‌خواند: Audit (صحت زنجیرهٔ M9)، Business (تعلیق + منطقهٔ اعلام‌شده)، Sso (provider زنده در برابر استاب)، Contract (اختلافات باز) — هر آیتم چک‌لیست دقیقاً با صداکردن همان Action/Query ای که خودِ آن قابلیت استفاده می‌کند تأیید می‌شود، نه یک تیک دستی، پس صفحه هیچ‌وقت نمی‌تواند از واقعیت کدبیس عقب بیفتد.

**فایل‌های اصلی:** `DataResidencyRegion`، `SetDataResidencyRegionAction`، ستون تازهٔ `businesses.data_residency_region`، `ComplianceQuery`/`GetComplianceOverviewAction` (دامنهٔ Analytics)، `NexusComplianceController`.

**تست:** ۱۲ تست جدید (۴ روی Data Residency، ۶ روی `GetComplianceOverviewAction`، ۲ روی کنترلر ادمین) — همه پاس.

**کامیت:** `feat(nexus): add Data Residency declaration + Compliance Dashboard`.

---

## Phase 7 / M11 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: تمام migrationهای Phase 7 (M1 تا M10 — از `nexus_holdings` تا `businesses.data_residency_region`) قبلاً به‌صورت تدریجی هرکدام در نوبت خودشان تمیز اجرا شده بودند؛ این اجرای نهایی «Nothing to migrate» گزارش داد، یعنی هیچ migration جاافتاده‌ای وجود ندارد.
- `php artisan test` کامل: **۱۴۷۱ pass / ۲۸۳ fail** — بدون رگرشن (همان ۲۸۳ شکست ثابت ماژول‌های غیرفعال، بدون تغییر در هیچ‌کدام از M9/M10). سوییت فیلترشدهٔ `--filter=Nexus`: **۵۹۸ پاس**.
- **چک صریح یکپارچگی قبل از هر چیز:** این مرحله دقیقاً از همان‌جایی ادامه یافت که سشن قبلی وسط M9 متوقف شده بود (در حال تولید ثابت `GENESIS_HASH`). قبل از نوشتن هر کد تازه، فایل‌های نیمه‌کاره بازبینی و باگ طول ثابت (۱۰۶ به‌جای ۶۴ کاراکتر) پیدا و رفع شد (جزئیات در M9 بالا) — دقیقاً همان درخواست صریح «خودت یه چک بکن چیزی خراب نشه».
- تست End-to-End دستی کامل روی دیتابیس dev واقعی (`php artisan tinker` روی یک اسکریپت PHP، همان جایگزین مستندشدهٔ کلیک مرورگری از Phase 2 به‌خاطر مشکل شناخته‌شدهٔ رزرو پورت ویندوز)، زنجیرهٔ واقعی سرتاسر تمام ۱۰ میلستون Phase 7 روی هم:
  1. سه Business واقعی ثبت‌نام و تأیید شدند (Parent Co، Sub Co، Outsider Co).
  2. Holding واقعی ساخته شد، Sub Co دعوت و پیوست، credit pooling فعال شد، Parent Co واقعاً ۵۰۰۰ کردیت به pool کمک کرد.
  3. عضو تیم واقعی (نقش Manager) به Parent Co دعوت شد.
  4. **زنجیرهٔ تأیید چندسطحی واقعی:** سقف اختیار Agent روی ۱۰۰۰ تنظیم شد، یک مذاکرهٔ واقعی بالاتر از آن Accept شد → وضعیت واقعاً `pending_approval` شد (نه `accepted`) → Manager واقعی سطح را تأیید کرد → وضعیت واقعاً به `accepted` برگشت.
  5. Private Marketplace واقعی ساخته شد با یک لیستینگ محرمانه؛ جستجوی Outsider Co (غیرعضو) واقعاً ۰ نتیجه برگرداند، جستجوی مالک واقعاً ۱ نتیجه برگرداند — محرمانگی مبتنی بر عضویت تأیید شد. (یک باگ در خودِ اسکریپت تست — نه در پلتفرم — حین این مرحله پیدا شد: `count()` روی shape بازگشتی اشتباه زده بود؛ رفع و دوباره اجرا شد.)
  6. هر سه SSO provider (`google`, `saml`, `ldap`) واقعاً در رجیستری ثبت‌شده بودند.
  7. یک اجرای واقعی Capability (`nexus.credit.balance`) از طریق همان `CapabilityExecutionService`ی که `/mcp/v1/execute` صدا می‌زند اجرا شد → یک ردیف audit واقعی با `businessId` درست ساخته شد، زنجیره از seq=1 به seq=2 رشد کرد.
  8. Outsider Co معلق شد → همان capability call واقعاً با `PermissionDeniedException` رد شد → ردیف audit جدید `status=denied` **و `businessId` واقعی Outsider Co را ثبت کرد، نه null** — دقیقاً رفتار طراحی‌شدهٔ M9. سپس reactivate واقعی انجام شد.
  9. دو Business منطقهٔ محل داده اعلام کردند (Iran، EU) → `GetComplianceOverviewAction` واقعی روی همین داده‌های واقعی: `dataResidencyBreakdown={"eu":1,"ir":1,"undeclared":16}`، `auditChain.intact=true`، تمام آیتم‌های چک‌لیست `YES` بودند به‌جز `sso_identity_federation` (`no`، صادقانه چون هیچ credential واقعی Google در این محیط dev تنظیم نشده — همان صداقت Zibal-only Phase 3/M3، نه باگ).
  10. `VerifyAuditChainIntegrityAction` نهایی: `intact=true`، بدون هیچ شکستگی.
- `git log --oneline`: هر ۱۰ مرحلهٔ Phase 7 (M1 تا M10) کامیت شده‌اند.

**کامیت:** `docs(nexus): Phase 7 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 7 (Enterprise Features) — تکمیل شد

| میلستون | دامنه/ویژگی اصلی | تست |
|---|---|---|
| M1 | Holding (Multi-Business Accounts) | ۲۶ |
| M2 | Holding Shared Credit Pool | ۱۱ |
| M3 | Business Team Members & Roles | ۱۳ |
| M4 | Multi-Level Approval Workflows | ۱۷ |
| M5 | Private Marketplaces | ۱۴ |
| M6 | SSO OAuth (Google) + Connector Registry | ۹ |
| M7 | MFA (TOTP) | ۱۵ |
| M8 | SAML/LDAP Stubs + SSO Admin Dashboard | ۱۳ |
| M9 | Hash-Chained Compliance Audit Trail | ۱۶ |
| M10 | Data Residency + Compliance Dashboard | ۱۲ |
| **مجموع** | | **۱۴۶ تست تازه** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **حتی داخل یک فاز، یک ledger سرتاسری (Audit) می‌تواند آگاهانه از قاعدهٔ «هر ledger مخصوص دامنهٔ خودش» عبور کند** — وقتی روادمپ صریحاً آن را بخواهد (M9)؛ توجیه باید همیشه صریح و مستند باشد، نه پیش‌فرض.
2. **دکوریتور دور یک رجیستری موجود، ارزان‌ترین راه برای یک نگرانی cross-cutting سرتاسری است** — `AuditingCapabilityHandlerRegistry` تمام ~۲۰ محل ثبت handler را بدون تغییر یک‌خط از آن‌ها پوشش داد؛ فازهای بعدی که نیاز به یک نگرانی سرتاسری MCP دارند (rate limiting سفارشی، metrics) باید همین الگو را دنبال کنند، نه ویرایش پراکنده.
3. **وقتی یک Action هم برای authorization و هم برای attribution لازم است، این‌دو ممکن است نیاز به resolve مجزا داشته باشند** — `RecordAuditEntryAction` عمداً Business را جدا از چک تعلیق حل می‌کند تا یک رویداد رد‌شده هنوز بداند «چه کسی» رد شده.
4. **صداقت اسکوپ همچنان الگوی تکرارشوندهٔ این پروژه است** — SAML/LDAP (M8) و Data Residency (M10) هر دو دقیقاً همان ردهٔ محدودیت مستندشدهٔ Escrow/Stripe-blocked را دنبال کردند: یک قابلیت واقعی که صادقانه اعلام می‌کند چه چیزی *نیست*.
5. **جدا نگه‌داشتن authorization از attribution یک الگوی عمومی‌تر است** که فازهای بعدی (خصوصاً هر چیزی شبیه فارنزیک/forensics) باید دوباره از آن استفاده کنند: منابعی که برای امنیت resolve می‌شوند، لزوماً بهترین منبع برای گزارش‌دهی نیستند.
6. **محدودیت شناخته‌شدهٔ باقی‌مانده:** `sso_identity_federation` در چک‌لیست Compliance فقط وقتی «YES» می‌شود که حداقل یک credential واقعی provider تنظیم شده باشد — در این محیط dev هنوز هیچ‌کدام تنظیم نشده (صادقانه، نه باگ)؛ تنظیم واقعی این credentialها یک اقدام عملیاتی است، نه کد.

**آماده برای Phase 8 (Intelligence & Automation)** طبق `docs/nexus-roadmap.md`.

---

# Phase 8 — Intelligence & Automation

**دستور:** «فاز 8 شروع کن به پیاده‌سازی. از من هیچ سوالی نیاز نیست بپرسی و تاییدیه بگیری خودمختار به صورت حرفه‌ای کار خودتو انجام بده» — کاملاً خودمختار، بدون سوال میانی. قبل از کدنویسی، یک Explore agent روی الگوهای موجود مرتبط با Phase 8 (دامنه Analytics، اسکلت‌بندی Query class، الگوی MCP capability، زیرساخت scheduling، پایپ‌لاین Notification، وضعیت LLMRouter، دامنه Marketplace، CostGate، فهرست کامل دامنه‌های موجود، ساختار `config/nexus/platform.php`) اجرا شد و پلن کامل ۶ مرحله‌ای (M1–M6) روی پنج بخش roadmap منطبق شد: Business Analytics (M1)، Market Intelligence (M2)، AI Recommendations (M3)، Automation Workflows (M4)، Predictive Intelligence (M5)، به‌علاوه تأیید نهایی (M6).

**یافتهٔ کلیدی پیش از کدنویسی:** `LLMRouter` (Phase 4) هنوز هیچ caller واقعی نداشت — تصمیم گرفته شد Phase 8 هم آن را متصل نکند؛ طبق فلسفهٔ ثابت «Rule Engine ۸۰٪، بدون هزینه»، هر پنج ویژگی این فاز (بنچمارک قیمت، هوش بازار، پیشنهاد، اتوماسیون، پیش‌بینی) کاملاً rule-based و قطعی پیاده‌سازی شدند — دقیقاً همان الگویی که `NegotiationReasoningService` (Phase 2/M5) و `CalculateReputationScoreAction` (Phase 6/M2) از قبل تثبیت کرده بودند، نه یک LLM caller اول قاچاقی.

## Phase 8 / M1 — Business Analytics

**تصمیم کلیدی:** بجای دوباره‌سازی نرخ موفقیت، `GetBusinessAnalyticsAction` مستقیماً `ReputationQuery::successRate()`/`completedDealsCount()` (Phase 6/M2) را دوباره‌استفاده می‌کند — یک `BusinessAnalyticsQuery` جدید فقط دو چیز واقعاً تازه اضافه می‌کند: **محاسبه‌گر صرفه‌جویی** (تفاوت قیمت لیست‌شدهٔ امروز کاتالوگ در برابر قیمت واقعاً مذاکره‌شده در معاملات پذیرفته‌شده به‌عنوان خریدار) و **بنچمارک قیمت صنعت** (میانگین قیمت خودِ کسب‌وکار در برابر میانگین صنعت، هرگز مقایسهٔ آیتم‌به‌آیتم چون هیچ SKU/taxonomy مشترکی بین کاتالوگ کسب‌وکارهای مختلف وجود ندارد).

**k-anonymity در بنچمارک:** میانگین صنعت فقط وقتی نمایش داده می‌شود که حداقل `analytics.min_benchmark_sample_size` (پیش‌فرض ۳) کسب‌وکار رقیب متفاوت در آن سهیم باشند — وگرنه `null` برمی‌گردد، تا قیمت دقیق یک رقیب تنها هرگز از یک میانگین دونفره قابل‌استخراج نباشد.

**«خروجی گزارش»** یک CSV ساده است (`ExportBusinessAnalyticsReportAction`)، نه یک پایپ‌لاین PDF دوم — تولید PDF قرارداد (Phase 2/M6) برای یک artifact حقوقی امضاشده از یک snapshot ثابت وجود دارد، کاری اساساً متفاوت از یک خروجی داده‌ی خودسرویس که صاحب کسب‌وکار هر زمان دلش خواست دوباره اجرا می‌کند.

**فایل‌های اصلی:** `app/Domains/Nexus/Analytics/{Infrastructure/Queries/BusinessAnalyticsQuery, Application/Actions/{GetBusinessAnalyticsAction,ExportBusinessAnalyticsReportAction}, Interfaces/Http/Controllers/BusinessAnalyticsController, Interfaces/MCP/AnalyticsCapabilities}.php`، `resources/views/nexus/analytics/index.blade.php`، capability رایگان `nexus.analytics.business`.

**تست:** ۱۱ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Business Analytics (success rate, savings calculator, price benchmark, CSV export)`.

---

## Phase 8 / M2 — Market Intelligence

**تصمیم کلیدی:** «صنعت» همیشه یعنی صنعتِ **فروشندهٔ** (counterparty) معامله، نه خریدار — یک ترند قیمت/تقاضا برای یک صنعت دربارهٔ چیزی است که کسب‌وکارهای آن صنعت می‌فروشند. `MarketIntelligenceQuery::priceTrend()`/`demandSignal()` هفتگی از روی داده‌های واقعی `negotiations` محاسبه می‌شوند (تنها سری قیمتِ زمان‌دار موجود در کدبیس؛ قیمت لیست‌شدهٔ امروز فقط یک snapshot است، نه تاریخچه).

**`competitorStats` ناشناس‌سازی‌شده** با همان مکانیزم k-anonymity بنچمارک M1 (نه یک طرح جدا) — تا وقتی تعداد رقبای متفاوت به حد نصاب `analytics.min_market_intelligence_sample_size` نرسد، هر تجمیع (میانگین قیمت، نرخ موفقیت صنعت) به‌جای عدد، `null` برمی‌گردد.

**تصمیم CostGate:** برخلاف `nexus.analytics.business` (رایگان، فقط دادهٔ خودِ caller)، `nexus.analytics.market` هزینه دارد — دقیقاً همان دلیل `nexus.marketplace.search` هزینه دارد: به بیرون از دادهٔ خودِ caller می‌رود.

**فایل‌های اصلی:** `MarketIntelligenceQuery`، `GetMarketIntelligenceAction`، `MarketIntelligenceController`، `resources/views/nexus/analytics/market.blade.php`.

**تست:** ۱۲ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Market Intelligence (price trend, demand signal, anonymized competitor stats)`.

---

## Phase 8 / M3 — AI Recommendations

**کشف صادقانهٔ غیرمنتظره:** `GetRecommendationsAction`/`RankSuppliersAction` (Phase 2/M1) هیچ‌وقت به MCP وصل نشده بودند — این مرحله اولین بار آن‌ها را واقعاً به‌عنوان capability سیم‌کشی کرد، دقیقاً همان الگوی «شکاف جامانده که فاز طبیعی خودش می‌بندد» که Phase 6/M5 برای اولین UI ادمین تأیید Business قبلاً ثابت کرده بود.

**ارتقای رتبه‌بندی به Reputation واقعی:** هر دو Action از قبل در docblock خودشان نوشته بودند «قبل از وجود Reputation، ساده‌ترین سیگنال صادقانه» — این مرحله دقیقاً همان‌جا وصل شد: عضویت هم‌صنعتی/تعداد کاتالوگ به‌عنوان معیار اولیهٔ کاندیدها باقی ماند، ولی رتبه‌بندی نهایی حالا از `CalculateReputationScoreAction` (Phase 6/M2) عبور می‌کند.

**دو Action کاملاً جدید:** `RecommendAlternativeSuppliersAction` («جایگزین‌ها» — هم‌صنعتی‌های تأمین‌کنندهٔ مشخص، به‌جز خودش) و `RecommendNegotiationTimingAction` («زمان‌بندی بهینه» — نرخ پذیرش تاریخی بر اساس روز هفته در برابر یک طرف مشخص، تنها سیگنال زمان‌بندی صادقانهٔ موجود؛ «زمان پاسخ‌گویی» که `ReputationQuery` در Phase 6/M2 به‌عمد رد کرده بود چون قابل جعل است، اینجا هم رد شد).

**فایل‌های اصلی:** `RecommendAlternativeSuppliersAction`، `RecommendNegotiationTimingAction`، `NegotiationTimingQuery`، `RecommendationsController`، ۴ capability جدید MCP.

**تست:** ۱۳ تست جدید، به‌علاوه به‌روزرسانی `GetRecommendationsActionTest` موجود (CostGate تازه اضافه‌شده نیاز به شارژ تست داشت، همان الگوی Phase 3/M2). کل: ۱۳ پاس.

**کامیت:** `feat(nexus): add AI Recommendations (reputation-ranked suppliers, alternatives, negotiation timing)`.

---

## Phase 8 / M4 — Automation Workflows

**دامنهٔ کاملاً جدید `Automation`** ساخته شد — دقیقاً همان استثنایی که Llm (Phase 4) و Growth (Phase 5) قبلاً گرفتند: «سفارش تکرارشونده/هشدار موجودی/هشدار قیمت» در هیچ‌کدام از ۱۰ دامنهٔ اولیه یا دامنه‌های بعدی جا نمی‌افتد.

**سه شکل قانون بسته، نه یک موتور قانون باز:** roadmap دقیقاً سه نوع نام می‌برد؛ `AutomationRuleType` هم دقیقاً همان سه را مدل می‌کند (`recurring_order`/`inventory_alert`/`price_alert`) با یک بستهٔ JSON `config` مخصوص هر نوع (همان الگوی escape-hatch `attributes` کاتالوگ)، نه یک گرامر condition/action باز. «Visual workflow builder» roadmap صادقانه یک فرم راهنمای مخصوص هر نوع است، نه بوم drag-and-drop — هیچ وابستگی diagramming JS در این کدبیس وجود ندارد (همان محدودیت مستندشدهٔ Network Visualization، Phase 5/M4).

**موتور `ProcessAutomationRulesAction`** با همان الگوی دو-نقطهٔ-ورود `DetectFraudSignalsCommand` (Phase 6/M4، که خودِ آن فاز صراحتاً برای استفادهٔ فازهای بعدی مستند کرده بود) — زمان‌بندی‌شده هر ساعت. شکست یک قانون (مثلاً کسب‌وکار بدون کردیت کافی) گرفته و به‌عنوان `Failed` لاگ می‌شود بدون متوقف‌کردن بقیهٔ قانون‌ها.

**بازاستفادهٔ کامل از Actionهای موجود:** سفارش تکرارشونده از `InitiateNegotiationAction` موجود استفاده می‌کند (نه یک مکانیزم «ثبت سفارش» دوم)، هشدار موجودی از `SearchMarketplaceAction` موجود برای auto-search (نه یک موتور جستجوی دوم) — هزینهٔ اعتباری واقعی جستجو هم به همان اندازهٔ جستجوی دستی از کسب‌وکار کسر می‌شود، صادقانه.

**فایل‌های اصلی:** `app/Domains/Nexus/Automation/**` (۴ لایه کامل)، دو migration، `ProcessAutomationRulesCommand` + entry در `routes/console.php`، ۷ capability MCP، صفحهٔ پرتال (`index`/`create`).

**تست:** ۳۶ تست جدید (Unit روی state machine + cooldown، Feature روی هر سه نوع قانون، موتور کامل با سناریوهای شکست/cooldown/pause، MCP، کنترلر) — همه پاس.

**کامیت:** `feat(nexus): add Automation Workflows domain (recurring orders, inventory/price alerts)`.

---

## Phase 8 / M5 — Predictive Intelligence

**سه پیش‌بینی rule-based، صفر ML:** `PredictiveIntelligenceQuery` (دامنهٔ Analytics، مثل هر Query class دیگر این دامنه) سه سیگنال واقعی و زمان‌دار می‌خواند:

1. **پیش‌بینی اعتبار تأمین‌کننده** (`ForecastSupplierReliabilityAction`): نرخ موفقیت دو پنجرهٔ مجاور (اخیر/قبلی) را مقایسه می‌کند تا روند (بهبود/کاهش/پایدار) را تشخیص دهد؛ وقتی هر پنجره‌ای نمونهٔ کافی نداشته باشد صادقانه `insufficient_data` برمی‌گرداند — نه یک روند حدسی.
2. **ریسک‌سنجی معاملات** (`AssessDealRiskAction`): امتیاز ریسک ۰-۱۰۰ از سه عامل توضیح‌پذیر و وزن‌دار (Reputation، دعواهای باختهٔ اخیر، ناهنجاری اندازهٔ معامله نسبت به میانگین تاریخی طرف مقابل).
3. **Scenario planning** (`SimulateNegotiationScenarioAction`): احتمال پذیرش یک قیمت فرضی از نرخ پذیرش واقعی طرف مقابل (به‌عنوان counterparty) و میانگین قیمت واقعاً پذیرفته‌شدهٔ او تخمین زده می‌شود؛ بدون سابقهٔ واقعی، `null` برمی‌گرداند، نه یک حدس ۵۰٪.

**تصمیم CostGate:** `nexus.analytics.forecast` رایگان ماند (هم‌ردهٔ `nexus.reputation.score` — بررسی اعتبار عمومی هر کسب‌وکاری)؛ `risk`/`scenario` هزینه دارند (هم‌ردهٔ `nexus.analytics.market` — ابزار تصمیم‌گیری سنگین‌تر).

**فایل‌های اصلی:** `PredictiveIntelligenceQuery`، `ForecastSupplierReliabilityAction`، `AssessDealRiskAction`، `SimulateNegotiationScenarioAction`، `PredictiveIntelligenceController`، ۳ capability MCP.

**تست:** ۱۹ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Predictive Intelligence (supplier reliability forecast, deal risk, scenario planning)`.

---

## Phase 8 / M6 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: هر دو migration جدید (`nexus_automation_rules`, `nexus_automation_run_logs`) تمیز اجرا شدند.
- `php artisan test` کامل: **۱۵۶۲ pass / ۲۸۳ fail** — بدون رگرشن (baseline قبل از Phase 8: ۱۴۷۱ pass؛ خالص +۹۱ تست پاس اضافه‌شده، دقیقاً برابر مجموع M1 تا M5: ۱۱+۱۲+۱۳+۳۶+۱۹=۹۱؛ همان ۲۸۳ شکست ثابت ماژول‌های غیرفعال، بدون تغییر).
- تست End-to-End دستی کامل روی دیتابیس dev واقعی (`php artisan tinker` روی یک اسکریپت PHP، همان جایگزین مستندشدهٔ کلیک مرورگری از Phase 2 به‌خاطر مشکل شناخته‌شدهٔ رزرو پورت ویندوز)، زنجیرهٔ واقعی سرتاسر هر پنج میلستون روی هم:
  1. دو کسب‌وکار واقعی ثبت‌نام و تأیید شدند (Buyer/Seller)، هرکدام کردیت واقعی گرفتند.
  2. Seller یک محصول واقعی با قیمت لیست ۱۲٬۰۰۰ IRT و موجودی ۲ اضافه کرد.
  3. Negotiation واقعی روی ۱۰٬۰۰۰ IRT × ۲ باز و پذیرفته شد.
  4. `GetBusinessAnalyticsAction` روی Buyer: صرفه‌جویی واقعی محاسبه‌شده = ۴٬۰۰۰ IRT — دقیقاً `(۱۲۰۰۰-۱۰۰۰۰)×۲`.
  5. `GetMarketIntelligenceAction` روی صنعت technology: یک هفتهٔ واقعی priceTrend/demandSignal از همان Negotiation.
  6. `GetRecommendationsAction`/`RecommendAlternativeSuppliersAction`: لیست‌های هم‌صنعتی واقعی برگشتند؛ Seller هرگز در لیست جایگزین‌های خودش ظاهر نشد (چک صریح شد).
  7. `RecommendNegotiationTimingAction` روی Seller: sampleSize واقعی = ۱.
  8. یک قانون Inventory Alert واقعی روی محصول Seller (آستانه ۵، موجودی فعلی ۲) ساخته شد → `ProcessAutomationRulesAction` واقعاً یک قانون را trigger کرد → یک `AutomationRunLog` واقعی با جزئیات موجودی/تعداد کاندیدا ثبت شد.
  9. `ForecastSupplierReliabilityAction` روی Seller: امتیاز شهرت واقعی ۵۰۰، trend صادقانه `insufficient_data` (فقط یک معامله در تاریخچه).
  10. `AssessDealRiskAction` روی Seller: امتیاز ریسک واقعی ۲۵ (سطح `low`).
  11. `SimulateNegotiationScenarioAction` روی قیمت فرضی ۹٬۰۰۰ (زیر baseline ۱۰٬۰۰۰ واقعی): احتمال پذیرش تخمینی = ۱۰۰٪.
  12. موجودی نهایی کردیت Buyer: ۹۹٬۸۰۰ — دقیقاً مطابق مجموع هزینه‌های CostGate طی سناریو.
- `php artisan route:list` روی هر سه پیشوند جدید (`nexus/analytics*`, `nexus/automation*`, `nexus/recommendations`): همهٔ ۱۴ مسیر جدید درست ثبت شده‌اند، بدون تداخل.
- `git log --oneline`: هر ۵ مرحلهٔ Phase 8 (M1 تا M5) کامیت شده‌اند.

**کامیت:** `docs(nexus): Phase 8 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 8 (Intelligence & Automation) — تکمیل شد

| میلستون | دامنه/ویژگی اصلی | تست |
|---|---|---|
| M1 | Business Analytics (success rate, savings, price benchmark, CSV export) | ۱۱ |
| M2 | Market Intelligence (price trend, demand signal, anonymized competitors) | ۱۲ |
| M3 | AI Recommendations (reputation ranking, alternatives, negotiation timing) | ۱۳ |
| M4 | Automation Workflows (recurring orders, inventory/price alerts) | ۳۶ |
| M5 | Predictive Intelligence (reliability forecast, deal risk, scenario planning) | ۱۹ |
| **مجموع** | | **۹۱ تست تازه** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **هر پنج ویژگی این فاز عمداً rule-based ماندند، نه اولین caller واقعی `LLMRouter`** — زیرساخت LLM کامل از Phase 4 آماده و آزمایش‌شده است، ولی وصل‌کردن آن یک تصمیم جدا و مستند برای فاز/بعدفاز دیگری است، دقیقاً همان محدودیت مستندشدهٔ خودِ Phase 4.
2. **k-anonymity یک مکانیزم واحد است، نه طرح جداگانه به‌ازای هر ویژگی** — بنچمارک قیمت (M1) و هوش بازار (M2) هر دو دقیقاً همان الگوی «تجمیع را زیر حد نصاب نمونه سرکوب کن، هرگز میانگین دونفره نشان نده» را به اشتراک می‌گذارند؛ هر aggregate آیندهٔ چندکسب‌وکاری باید همین را دنبال کند.
3. **شکاف‌های جامانده از فازهای قبل (Action ساخته‌شده ولی هرگز به MCP وصل‌نشده) کاندیدهای طبیعی فاز بعدی‌اند** — `GetRecommendationsAction`/`RankSuppliersAction` (Phase 2) نمونهٔ دوم همین الگو بودند، بعد از اولین UI ادمین تأیید Business (Phase 6/M5).
4. **دامنهٔ کاملاً جدید فقط وقتی که واقعاً هیچ دامنهٔ موجودی جا نمی‌افتد و state جدید واقعی نیاز است** — Automation (M4) این آستانه را رد کرد (سه نوع قانون + دو جدول جدید)؛ Predictive Intelligence (M5) رد نکرد (فقط Query روی داده‌های موجود) و در Analytics ماند — همان تمایز «read-model در دامنهٔ موجود در برابر state جدید در دامنهٔ جدید» که Phase 5 برای Growth در برابر Network/Viral Analytics تثبیت کرده بود.
5. **موتور Automation از الگوی دو-نقطهٔ-ورود Phase 6/M4 مستقیماً کپی شد** — `Schedule::command()` واقعی + یک Action مستقل و تست‌پذیر؛ فازهای بعدی که به job دوره‌ای نیاز دارند دیگر نباید این را از صفر اختراع کنند.
6. **محدودیت شناخته‌شدهٔ مستند:** «زمان‌بندی بهینه» (M3) و «ریسک‌سنجی»/«Scenario planning» (M5) هرکدام روی سیگنال‌های واقعاً موجود محدودند (روز هفته، نه ساعت دقیق؛ سه عامل ریسک، نه تحلیل چندعاملی کامل) — گسترش این سیگنال‌ها به داده‌های تازه‌ای نیاز دارد که این کدبیس هنوز صادقانه ثبت نمی‌کند، نه سهل‌انگاری این فاز.

**آماده برای Phase 9 (Ecosystem & API)** طبق `docs/nexus-roadmap.md`.

---

# Phase 9 — Ecosystem & API

**دستور:** «فاز 9 رو شروع کن» (بعد از تأیید Phase 8). قبل از کدنویسی، دو Explore agent موازی روی زیرساخت موجود (پایپ‌لاین MCP/الگوی Connector Registry/AgentToken یکی، الگوهای Agent/Notifications/تست‌های MCP دیگری) اجرا شد و پلن کامل ۹ مرحله‌ای (M1–M9) روی چهار بخش روادمپ منطبق شد: Public API (M1–M2)، Webhooks (M3)، مستندسازی + GraphQL (M4–M5)، Integration Marketplace (M6)، Agent Developer Platform (M7)، SDKها (M8)، تأیید نهایی (M9).

**یافتهٔ کلیدی پیش از کدنویسی:** هیچ سطح REST عمومی مستقلی از MCP وجود نداشت (`routes/nexus/api.php` فقط یک stub خالی Phase 0 بود)، هیچ `ApiKey`/Sanctum ای در کدبیس نبود (ولی `AgentToken::hash()`/`matches()` — دقیقاً همان الگوی هش‌کردن یک‌طرفه — مستقیماً برای یک اعتبارنامهٔ جدید Business-scoped قابل کپی بود)، و هیچ سیستم اشتراک وب‌هوک خروجی‌ای وجود نداشت (فقط `WebhookSender` ماژول *فعال* Notifications، یک کانال ارسال ساده بدون هدر سفارشی یا اشتراک پایدار). الگوی Connector Registry (شش کاربرد قبلی: Payment/LLM/SSO/Shipping/Channel) کاملاً برای Integration Marketplace قابل استفادهٔ مجدد بود.

## Phase 9 / M1 — Developer Platform: هستهٔ کلید API

دامنهٔ کاملاً جدید `Developer` (همان استثنایی که Llm در Phase 4 و Growth در Phase 5 گرفتند — «Public API/Webhooks/Integration/Agent Marketplace» در هیچ‌کدام از دامنه‌های موجود جا نمی‌افت). `ApiKey` دقیقاً همان الگوی `AgentToken` را کپی می‌کند (SHA-256، `hash_equals`، پلین‌تکست فقط یک‌بار در لحظهٔ صدور نمایش داده می‌شود) ولی یک اعتبارنامهٔ Business-scoped جداست، نه یک Agent-scoped — یک کلید API لو رفته بدون دست‌زدن به توکن مذاکرهٔ واقعی Agent قابل لغو است. `ApiKeyScope` یک enum بسته (نه رشتهٔ آزاد) با ۵ مقدار اولیه (`business.read`, `catalog.read`, `marketplace.read`, `negotiation.read`, `credit.read`) — دقیقاً همان دامنهٔ endpointهای M2.

**فایل‌های اصلی:** `app/Domains/Nexus/Developer/{Domain,Application,Infrastructure,Interfaces}/**` (`ApiKey`, `IssueApiKeyAction`, `AuthenticateApiKeyAction`, ...)، migration `nexus_api_keys`، صفحهٔ پرتال `/nexus/developer/api-keys`.

**تست:** ۲۱ تست جدید — همه پاس.

**کامیت:** `feat(nexus): add Developer Platform API key management (Phase 9/M1)`.

---

## Phase 9 / M2 — Public REST API v1

پنج endpoint (`GET /nexus/api/v1/{business,catalog,marketplace/search,negotiations/{id},credit/balance}`)، هرکدام یک کنترلر نازک که دقیقاً همان Action ای را صدا می‌زند که MCP capability معادلش صدا می‌زند (`GetBusinessDashboardAction`, `SearchCatalogAction`, `SearchMarketplaceAction`, `GetNegotiationAction`, `GetCreditBalanceAction`) — بدون هیچ منطق تجاری دوباره‌نویسی‌شده برای این کانال. چون `SearchMarketplaceAction` از قبل خودش `SpendCreditsForActionAction` را صدا می‌زند، جست‌وجو از طریق REST دقیقاً همان هزینهٔ کردیت جست‌وجو از طریق MCP را می‌گیرد — صورت‌حساب مستقل از کانال است.

**تصمیم کلیدی:** `EnsureValidApiKey` per-route با پارامتر scope وصل شد (نه یک‌بار در سطح گروه) — یک نمونهٔ بدون scope در سطح گروه احراز هویت را درست انجام می‌داد ولی هرگز واقعاً چک نمی‌کرد کدام scope برای کدام endpoint لازم است. Rate limiting (`RateLimiter::for('nexus-api', ...)`) بر اساس id کلید API کلید می‌خورد (نه IP)، چون `EnsureValidApiKey` همیشه قبل از `throttle:nexus-api` در آرایهٔ میان‌افزار هر route اجرا می‌شود.

**لمسِ Core:** `MCPExceptionHandler::handles()` برای پوشش‌دادن `nexus/api/*` هم گسترش یافت (دقیقاً همان توجیه افزودن `api/agents/*` در فاز قبل) — `EnsureValidApiKey`/کنترلرهای جدید همان انواع Exception موجود (`PermissionDeniedException`, `InvalidArgumentException`, `ConflictExceptionInterface`) را پرتاب می‌کنند، فقط پیشوند URL فرق دارد.

**فایل‌های اصلی:** `EnsureValidApiKey` middleware، ۵ کنترلر `Api\*ApiController` (هرکدام در دامنهٔ صاحبِ Action خودش، نه در Developer)، `routes/nexus/api.php`، بخش جدید `api` در `config/nexus/platform.php`.

**تست:** ۱۰ تست جدید (احراز هویت، scope، تعلیق، هر ۵ endpoint، rate limit واقعی با override موقت config) — همه پاس.

**کامیت:** `feat(nexus): wire the Public REST API v1 (Phase 9/M2)`.

---

## Phase 9 / M3 — Webhooks

`WebhookSubscription` (Business-scoped، تا ۳ رویداد قابل‌اشتراک: `negotiation.accepted`, `escrow.released`, `contract.generated` — دقیقاً همان رویدادهای واقعی از پیش دیسپچ‌شده در Negotiation/Contract، هرگز یک سیگنال ساختگی) + `WebhookDeliveryLog` (ledger غیرقابل‌تغییر هر تلاش ارسال، همان الگوی `CreditTransaction`/`LLMUsageLog`).

**تصمیم معماری کلیدی (عمداً از WebhookSender ماژول Notifications عبور نکرد):** امضای `WebhookSender::send(string $recipient, string $subject, string $body)` هیچ راهی برای پیوست هدر سفارشی ندارد، و یک وب‌هوک بدون هدر امضا برای گیرنده غیرقابل‌اعتماد است. `DispatchWebhookEventAction` یک دیسپچر Guzzle مستقل و کوچک خودش است (شکل واقعاً متفاوت → آداپتور جدید، همان قضاوتی که Phase 4/M2 برای `LLMProviderInterface` در برابر `LLMClientInterface` گرفت) — هر payload با HMAC-SHA256 امضا می‌شود (`X-Nexus-Signature: sha256=...`) با راز مخصوص هر subscription (رمزنگاری‌شده در دیتابیس، نه هش یک‌طرفه، چون باید در لحظهٔ ارسال دوباره خوانده شود). یک ارسال ناموفق هرگز throw نمی‌کند — هر دو حالت موفق/ناموفق دقیقاً یک ردیف `WebhookDeliveryLog` می‌سازند.

**فایل‌های اصلی:** `WebhookSubscription`/`WebhookDeliveryLog` (Entity/Repo/Model)، `DispatchWebhookEventAction`، سه Listener جدید (`DispatchWebhookOn{NegotiationAccepted,EscrowReleased,ContractGenerated}Listener`) کنار Listenerهای موجود همان رویدادها، دو migration، صفحهٔ پرتال `/nexus/developer/webhooks`.

**تست:** ۱۶ تست جدید — شامل یک زنجیرهٔ کامل واقعی (propose→accept→release، بدون `Event::fake()`، فقط لایهٔ HTTP خروجی mock شده) که هر سه رویداد را واقعاً fire و تحویل می‌دهد.

**کامیت:** `feat(nexus): add Webhooks (Phase 9/M3)`.

---

## Phase 9 / M4 — مستندات عمومی API

صفحهٔ `/nexus/docs` (بدون احراز هویت — عمداً، چون یک توسعه‌دهندهٔ بالقوه باید قبل از داشتن حساب آن را بخواند)، دست‌ساز به‌جای افزودن ابزار OpenAPI/Scribe (هیچ‌کدام از قبل در کدبیس نبود و برای یک سطح ۵-endpoint نامتناسب بود — همان پرهیز «بدون وابستگی جدید وقتی صفحهٔ دست‌ساز کافی است» Network Visualization در Phase 5/M4). فهرست scopeها/رویدادهای وب‌هوک/محدودیت نرخ مستقیماً از همان enumها/config واقعی خوانده می‌شوند، نه کپی دستی — صفحه هرگز نمی‌تواند از واقعیت کدبیس عقب بیفتد.

**کامیت:** `feat(nexus): add public API documentation page (Phase 9/M4)`.

---

## Phase 9 / M5 — GraphQL API (فقط‌خواندنی)

`POST /nexus/api/v1/graphql` با `webonyx/graphql-php` (موتور سبکی که خودِ Lighthouse هم آن را می‌پیچد — نه خودِ فریم‌ورک سنگین Lighthouse، چون سطح این فاز فقط ۵ فیلد Query است). هر فیلد Query دقیقاً همان Action ای را صدا می‌زند که REST endpoint معادلش (M2) صدا می‌زند.

**تصمیم اسکوپ مستند:** ساختارهای تودرتو/ناهمگن (`currentTerms`، `agent`، `reputationScore`، `listings`) یک اسکالر سفارشی `JSON` هستند، نه ObjectType کامل مدل‌شده — ارزش واقعی GraphQL اینجا انتخاب «کدام منبع سطح‌بالا» است، نه sub-selection عمیق در هر شیء تودرتو؛ مدل‌سازی کامل هر DTO کاندید طبیعی فاز بعدی است.

**باگ واقعی پیدا و رفع شد حین نوشتن تست:** فیلدهای Query سطح ریشه ابتدا `Type::nonNull(...)` تعریف شده بودند؛ طبق قاعدهٔ null-propagation خودِ GraphQL، خطای یک فیلد (مثلاً کمبود scope) کل `data` را null می‌کرد، نه فقط همان فیلد را. راه‌حل: فیلدهای ریشه nullable شدند تا خطای هر فیلد مستقل بماند — همان تضمین partial-result که کل طراحی این مرحله ادعا می‌کرد.

**تست:** ۴ تست جدید (تک‌فیلد، چندفیلد در یک درخواست، خطای جزئی-نه-کامل، بدون احراز هویت) — همه پاس.

**کامیت:** `feat(nexus): add read-only GraphQL API (Phase 9/M5)`.

---

## Phase 9 / M6 — Integration Marketplace

`IntegrationConnection` (Business-scoped): یک اتصال عمومی و category-tagged (ERP/CRM/Accounting/Logistics — دقیقاً چهار دستهٔ نام‌برده‌شدهٔ روادمپ) به‌جای چهار یکپارچه‌سازی نام‌دار مخصوص فروشنده — این کدبیس هیچ credential/SDK واقعی SAP/QuickBooks/HubSpot ندارد (همان صداقت SAML/LDAP، Phase 7/M8). `SyncCatalogToIntegrationAction` نیمهٔ واقعی و کارکردیِ این مرحله است: کاتالوگ واقعی کسب‌وکار (همان `SearchCatalogAction`) را با یک نگاشت فیلد پیکربندی‌شده (source→target، همان escape-hatch الگوی `config` JSON خودِ Automation) به یک URL دلخواه POST می‌کند. «No-code builder» روادمپ صادقانه یک فرم key/value محدود است، نه بومِ drag-and-drop. اتصال به Zapier/Make.com عمداً یک مکانیزم دوم نگرفت — سیستم Webhook همین فاز (M3) دقیقاً همان چیزی است که تریگر «Webhook سفارشی» هر دو پلتفرم نیاز دارد.

**فایل‌های اصلی:** `IntegrationConnection` (Entity/Repo/Model)، `SyncCatalogToIntegrationAction` + ۳ Action مدیریتی، دو migration، صفحهٔ پرتال `/nexus/developer/integrations`.

**تست:** ۱۲ تست جدید (شامل mapItem با/بدون نگاشت، sync واقعی با MockHandler که هدر Authorization و بدنهٔ نگاشت‌شده را تأیید می‌کند) — همه پاس.

**کامیت:** `feat(nexus): add Integration Marketplace (Phase 9/M6)`.

---

## Phase 9 / M7 — Agent Developer Platform

**تصمیم اسکوپ کلیدی:** «مارکت‌پلیس ایجنت‌های Third-party» به‌صورت صادقانه یعنی مارکت‌پلیس پیش‌تنظیم‌های قابل‌نصب شخصیت/لحن/استراتژی (`AgentStrategyTemplate`) روی Agent واقعی و از‌پیش‌معتبرِ خودِ هر Business — همان سه فیلدی که `Agent` (Phase 1/M3) از روز اول داشت. یک مارکت‌پلیس از پردازه‌های واقعی Agent شخص‌ثالث به یک sandbox اجرای کد نیاز داشت که این کدبیس هیچ زیرساختی برایش ندارد (بدون container orchestration، بدون اجرای کد نامعتمد در هیچ‌جای دیگر کدبیس). «حساب توسعه‌دهنده» هم چیز دومی نساخت — خودِ `Business` (که از قبل موجودیت واقعی و صاحب‌درآمد است) نقش ناشر را بازی می‌کند.

نصب بین دو کسب‌وکار واقعاً کردیت جابه‌جا می‌کند: نصب‌کننده `priceCredits` می‌پردازد (`DeductCreditsAction`)، ناشر `priceCredits - platformFee` می‌گیرد (`GrantCreditsAction` با نوع تراکنش تازهٔ `AgentTemplateEarning`) — درصد کارمزد یک getter تازه روی همان `MarginSettingsService` موجود (`agentTemplateFeePercent()`, پیش‌فرض ۲۰٪) است، نه یک مکانیزم تنظیمات دوم. نصب قالب خودِ ناشر همیشه رایگان است (هیچ طرف سومی برای تقسیم درآمد وجود ندارد) ولی همچنان یک ردیف `AgentTemplateInstall` (با هر سه مبلغ صفر) می‌سازد تا تاریخچهٔ نصب ناشر کامل بماند.

**«Sandbox اختصاصی» صادقانه یک پیش‌نمایش dry-run است، نه یک Tenant دوم:** `PreviewAgentStrategyTemplateAction` دقیقاً نشان می‌دهد یک قالب چه چیزی را تغییر می‌دهد — بدون هیچ نوشتن دیتابیس، بدون کسر کردیت. ساخت یک Tenant/Agent ایزولهٔ دوم برای همین منظور بررسی و رد شد: هزینهٔ چرخهٔ عمر (provisioning/مالکیت/پاک‌سازی) بدون فایدهٔ اضافه‌ای نسبت به همین پیش‌نمایش، در حالی که این پیش‌نمایش دقیقاً همان ریسکی را که «sandbox» قرار است حذف کند از بین می‌برد: جهش تصادفی Agent واقعی و در حال مذاکرهٔ خودِ ناشر حین آزمایش.

**فایل‌های اصلی:** `AgentStrategyTemplate`/`AgentTemplateInstall` (Entity/Repo/Model)، ۶ Action (`Publish`/`ListMarketplace`/`ListMyPublished`/`Unpublish`/`Preview`/`Install`)، getter تازه در `MarginSettingsService`، case تازه در `CreditTransactionType`، دو migration، صفحهٔ پرتال `/nexus/developer/agent-marketplace`.

**تست:** ۱۳ تست جدید — شامل تأیید دقیق ریاضی تقسیم درآمد (۱۰۰۰ کردیت × ۲۰٪ = ۲۰۰ کارمزد پلتفرم، ۸۰۰ سهم ناشر) روی داده‌های واقعی دیتابیس.

**کامیت:** `feat(nexus): add Agent Developer Platform (Phase 9/M7)`.

---

## Phase 9 / M8 — SDKها (PHP، Node.js، Python، Go)

چهار کلاینت بدون‌وابستگی در `packages/nexus-sdk-{php,node,python,go}` (هنوز در هیچ رجیستری بسته‌ای منتشر نشده — فقط درون همین مخزن)، هرکدام همان قرارداد یکسان را پیاده می‌کنند (۵ متد REST + `graphql()` + `verifyWebhookSignature`). PHP فقط `ext-curl`/`ext-json` می‌خواهد (نه Guzzle)، Node فقط `fetch` بومی (Node ≥18، بدون axios)، Python فقط `urllib` استاندارد (بدون requests) — همان انضباط «کتابخانهٔ HTTP را به مصرف‌کننده تحمیل نکن» که خودِ کنترلرهای REST پلتفرم با پنهان‌نکردن Guzzle در قرارداد عمومی‌شان از قبل رعایت می‌کردند.

**صداقت محیط توسعه:** PHP/Node/Python هرسه در همین محیط تست واقعی اجرا و پاس شدند (`phpunit`: ۶، `node --test`: ۶، `python -m unittest`: ۶). Go نوشته شد ولی اجرا/کامپایل نشد — هیچ toolchain گویی در این محیط توسعه نصب نیست؛ همان صداقتی که SAML/LDAP (Phase 7/M8) و اندپوینت‌های LLM محلی (Phase 4/M2) از قبل رعایت کرده بودند: یک پیاده‌سازی واقعی و دقیق‌نوشته‌شده از چیزی که این محیط نمی‌تواند اجرا کند، نه یک جای‌نگه‌دار.

**کامیت:** `feat(nexus): add official SDKs for PHP, Node.js, Python, and Go (Phase 9/M8)`.

---

## Phase 9 / M9 — تأیید نهایی

- `php artisan migrate --force` روی دیتابیس dev: «Nothing to migrate» — هر ۹ migration فاز (M1، M3، M6، M7) قبلاً هرکدام در نوبت خودشان تمیز اجرا شده بودند.
- `php artisan route:list --path=nexus`: هر ۱۸ مسیر جدید (۵ REST + ۱ GraphQL زیر `nexus/api/v1/*`، ۱۲ صفحهٔ پرتال/مستندات زیر `nexus/developer/*` و `nexus/docs`) درست ثبت شده‌اند، بدون تداخل با ۱۳۶ مسیر موجود.
- `php artisan test --filter=Nexus`: **۷۶۶ پاس** (baseline قبل از فاز: ۶۸۹؛ خالص +۷۷ — دقیقاً برابر مجموع تست‌های Laravel-side این فاز: ۲۱+۱۰+۱۶+۱+۴+۱۲+۱۳=۷۷؛ SDKها تست‌های خودشان را در سوییت‌های جداگانهٔ زبان خودشان دارند، نه در PHPUnit).
- سوییت کامل: **۱۶۳۹ pass / ۲۸۳ fail** — بدون رگرشن (همان ۲۸۳ شکست ثابت ماژول‌های غیرفعال، بدون تغییر در هیچ مرحله).
- **تست End-to-End دستی کامل روی سرور واقعی** (`php artisan serve --port=9500`، همان مشکل رزرو پورت ویندوز فازهای قبل دوباره صادق بود)، این‌بار با یک گیرندهٔ وب‌هوک واقعی و مستقل هم (`php -S 127.0.0.1:9501`) برای اثبات واقعی تحویل HTTP، نه فقط MockHandler تست‌ها:
  1. دو کسب‌وکار واقعی (Buyer/Seller) ثبت‌نام، تأیید، و کردیت گرفتند؛ یک محصول واقعی اضافه شد.
  2. دو کلید API واقعی صادر شد (یکی با همهٔ ۵ scope، یکی فقط `credit.read`) — هر ۵ endpoint REST با curl واقعی زده شدند: پروفایل کسب‌وکار ۲۰۰، عبور از scope روی کلید محدود ۴۰۳ با پیام دقیق، بدون هدر Authorization ۴۰۱، هدرهای `X-RateLimit-*`/`X-API-Version` واقعاً روی پاسخ حاضر بودند.
  3. `POST /nexus/api/v1/graphql` با کوئری چندفیلدی واقعی: با کلید کامل هر سه فیلد (کردیت/کاتالوگ/بازار) پر شدند؛ با کلید محدود، `creditBalance` پر شد ولی `catalog` دقیقاً همان خطای جزئی مورد انتظار را برگرداند — بدون خالی‌شدن کل `data` (اثبات مستقیم رفع باگ M5 روی شبکهٔ واقعی).
  4. یک اشتراک وب‌هوک واقعی برای هر سه رویداد ثبت شد؛ یک مذاکرهٔ واقعی (propose→accept→release) روی زنجیرهٔ رویداد کامل (Negotiation→Contract→Escrow) اجرا شد → گیرندهٔ محلی واقعاً هر سه رویداد را با بدنهٔ صحیح و هدرهای `X-Nexus-Event`/`X-Nexus-Signature` دریافت کرد؛ امضای هر سه با `hash_hmac`/`hash_equals` روی رازِ واقعیِ ذخیره‌شده دوباره محاسبه و **هرسه معتبر تأیید شدند** — اثبات رمزنگاری‌شدهٔ کامل چرخهٔ امضا/تأیید، نه فقط بررسی وجود هدر.
  5. هر ۴ صفحهٔ پرتال جدید (`api-keys`, `webhooks`, `integrations`, `agent-marketplace`) و `/nexus/docs` بدون session واقعاً ۳۰۲/۲۰۰ درست برگرداندند — بدون هیچ ۵۰۰.
- `git log --oneline`: هر ۸ مرحلهٔ Phase 9 (M1 تا M8) کامیت شده‌اند.

**کامیت:** `docs(nexus): Phase 9 complete — final handoff summary`.

---

## 🎯 خلاصه Phase 9 (Ecosystem & API) — تکمیل شد

| میلستون | دامنه/ویژگی اصلی | تست |
|---|---|---|
| M1 | Developer Platform (API Key core) | ۲۱ |
| M2 | Public REST API v1 | ۱۰ |
| M3 | Webhooks (HMAC-signed, delivery log) | ۱۶ |
| M4 | مستندات عمومی API | ۱ |
| M5 | GraphQL API (فقط‌خواندنی) | ۴ |
| M6 | Integration Marketplace | ۱۲ |
| M7 | Agent Developer Platform | ۱۳ |
| M8 | SDKها (PHP/Node/Python/Go) | ۰ (سوییت‌های جدا: ۱۸) |
| **مجموع** | | **۷۷ در PHPUnit / ۱۸ در سوییت‌های SDK** |

تصمیمات معماری ماندگار برای فازهای بعدی:
1. **یک اعتبارنامهٔ جدید Business-scoped، الگوی هش `AgentToken` را کپی می‌کند، نه Sanctum/فریم‌ورک توکن سوم‌شخص** — `ApiKey` (M1) دومین کاربرد این الگوست؛ هر اعتبارنامهٔ جدید آینده باید همین‌جا نگاه کند، نه یک وابستگی احراز هویت تازه.
2. **کنترلر نازک روی همان Action موجود، برای هر کانال تازه (REST، GraphQL)** — هیچ منطق تجاری هرگز دوباره برای یک کانال جدید نوشته نمی‌شود؛ صورت‌حساب/CostGate به‌طور خودکار مستقل از کانال یکسان می‌ماند چون خودِ Action آن را کنترل می‌کند، نه لایهٔ HTTP.
3. **«شکل واقعاً متفاوت» توجیه یک آداپتور موازی است، نه استفادهٔ اجباری از زیرساخت موجود** — `DispatchWebhookEventAction` (M3) عمداً `WebhookSender` را دور زد چون نیاز به هدر سفارشی داشت؛ همان قضاوت `LLMProviderInterface` (Phase 4/M2) در برابر `LLMClientInterface`.
4. **فیلدهای ریشهٔ GraphQL باید nullable باشند مگر دلیل قوی برای nonNull وجود داشته باشد** — قاعدهٔ null-propagation یک فیلد خطادار را به کل پاسخ تسری می‌دهد؛ این یک باگ واقعی این فاز بود (M5)، نه یک ریسک فرضی.
5. **صداقت اسکوپ همچنان الگوی تکرارشوندهٔ این پروژه است، این‌بار در سه شکل جدید:** Integration Marketplace = یک کانکتور عمومی به‌جای یکپارچه‌سازی‌های نام‌دار جعلی (M6)، «مارکت‌پلیس Agent» = پیش‌تنظیم قابل‌نصب به‌جای sandbox اجرای کد (M7)، Go SDK = نوشته‌شده ولی صادقانه اجرا‌نشده (M8).
6. **یک getter تازه روی `MarginSettingsService` موجود، نه یک مکانیزم تنظیمات دوم، برای هر کارمزد پلتفرم تازه** — `agentTemplateFeePercent()` (M7) پنجمین فیلد این سرویس است؛ هر کارمزد آیندهٔ دیگر باید همین الگو را دنبال کند.
7. **محدودیت شناخته‌شدهٔ باقی‌مانده:** مدل‌سازی کامل GraphQL برای ساختارهای تودرتو (به‌جای اسکالر `JSON`) و اتصال واقعی نصب‌وراه‌اندازی SDKها به رجیستری‌های بسته (Packagist/npm/PyPI/pkg.go.dev) هر دو کاندید طبیعی فاز بعدی‌اند، نه سهل‌انگاری این فاز.

**آماده برای Phase 10 (Global Expansion)** طبق `docs/nexus-roadmap.md`.

---

## رفع شکاف: فرم خودسرویس کاتالوگ (بعد از Phase 9، قبل از شروع Phase 10)

**دستور:** «کاتالوگ رو به یک فرم خودسرویس واقعی در پرتال کسب‌وکار وصل کن» — رفع همان محدودیت مستندشدهٔ Phase 1/M4 که تا این لحظه فقط از طریق تینکر/تست/API قابل دسترس بود، نه یک UI واقعی.

**تصمیم کلیدی:** چک مالکیت مستقیماً داخل `UpdateProductAction`/`UpdateServiceAction` اضافه شد (پارامتر تازهٔ `businessId`، مقایسه با `product->businessId()`/`service->businessId()`)، نه فقط در کنترلر — همان انضباط «Action خودش authorization خودش را enforce می‌کند» که کل پروژه (از `ListMyNegotiationsAction` در Phase 2/M7 تا هر Action دیگر) از قبل رعایت می‌کرد. `CatalogController` جدید (`Catalog/Interfaces/Http/Controllers`) فقط یک لایهٔ نازک روی همان چهار Action موجود فاز اول (`AddProduct`, `AddService`, `UpdateProduct`, `UpdateService`, `SearchCatalog`) است — هیچ منطق تجاری تازه‌ای نوشته نشد.

**فایل‌های اصلی:** `CatalogController`، ویوهای `resources/views/nexus/catalog/{index,products/{create,edit},services/{create,edit}}.blade.php`، روت‌های جدید زیر `nexus/catalog` (گارد `business.auth:business`، همان الگوی هر بخش دیگر پرتال)، کلیدهای ترجمهٔ `nexus.catalog.*` در `lang/{fa,en}/messages.json`، لینک ناوبری در داشبورد کسب‌وکار.

**تست:** ۲ تست جدید در `CatalogActionsTest` (مالکیت رد شود اگر آیتم متعلق به کسب‌وکار دیگری باشد) + ۱۰ تست جدید در `CatalogControllerTest` (بدون لاگین ریدایرکت، فهرست فقط آیتم‌های خودش، هرگز کاتالوگ کسب‌وکار دیگر را نشان نمی‌دهد، افزودن/ویرایش محصول و خدمت، ویرایش آیتم کسب‌وکار دیگر ۴۰۳). کل سوییت: **۱۶۵۱ pass / ۲۸۳ fail** — بدون رگرسیون (baseline ۱۶۳۹ + ۱۲ تست جدید؛ همان ۲۸۳ شکست ثابت ماژول‌های غیرفعال).

**اسناد به‌روزشده:** محدودیت مستندشده در `tutorials/base/02-customer-journey/03-catalog-and-discovery.md` و `tutorials/base/01-introduction/05-roadmap-and-future-vision.md` حذف شد (دیگر واقعیت ندارد).

**کامیت:** `feat(nexus): add self-service Catalog portal (product/service create/edit forms, ownership checks)`.

---

## رفع شکاف: پنل ادمین — Live Negotiation Monitor + صفحه‌ی خانه‌ی واقعی + بازچینی سایدبار

**دستور:** «پنل مدیریت رو حرفه‌ای‌تر و کاراتر کن — از دید یک مدیر ارشد کسب‌وکار، باید بشه فهمید ایجنت‌ها الان چه مذاکراتی دارند، چی به هم می‌گویند، و چه قراردادهایی بسته‌اند.» دقیقاً همان قابلیت «Live Negotiation Monitor» که در `CLAUDE.md` («Admin Panel Must-Haves», مورد ۴) از ابتدا خواسته شده بود ولی هیچ‌وقت ساخته نشده بود — فقط نسخه‌ی business-scoped آن (Phase 2/M7) وجود داشت.

**تصمیم کلیدی:** چون ادمین هرگز طرف هیچ Negotiation‌ای نیست، Actionهای موجود (`ListMyNegotiationsAction`/`GetNegotiationAction`/`PollNegotiationMessagesAction`) که همگی چک `isParty()` دارند قابل استفاده مستقیم نبودند. به‌جای دور زدن این چک با یک آرگومان مخفی، یک متد پلتفرم‌محور تازه (`NegotiationRepositoryInterface::findAll()`) اضافه شد و `NexusNegotiationsController` مستقیماً از روی ریپازیتوری‌ها کار می‌کند — دقیقاً همان الگوی نازک/بدون-Action که `NexusEscrowController`/`NexusDisputeController` از قبل برای صفحات فقط-خواندنی ادمین دارند، نه یک لایه‌ی Action موازی تازه.

**نظارت زنده:** صفحه‌ی جزئیات مذاکره از همان الگوی polling هر ۳ ثانیه (Alpine + fetch) که ویوئر business-facing (Phase 2/M7) استفاده می‌کند بهره می‌برد — یک اندپوینت JSON تازه (`dashboard.nexus.negotiations.messages`) که بدون چک `isParty()` مستقیماً `NegotiationMessageRepositoryInterface::findAfter()` را صدا می‌زند.

**صفحه‌ی خانه‌ی واقعی:** `DashboardController::index()` قبلاً به Revenue Dashboard ریدایرکت می‌شد (فیکس موقتی جلسه‌ی قبل) — حالا به یک صفحه‌ی واقعی (`GetPlatformOverviewAction` + `NexusOverviewController`) ریدایرکت می‌شود که ۸ عدد واقعی و کلیک‌پذیر نشان می‌دهد: تأییدهای کسب‌وکار/کاتالوگ در انتظار، اختلافات باز، امانی‌های معترض‌شده، کسب‌وکارهای معلق/درخواست تجدیدنظر، مذاکرات فعال، درآمد ناخالص — هرکدام مستقیم به صفحه‌ی جزئیات خودش لینک می‌شود.

**بازچینی سایدبار:** سایدبار قبلی یک لیست تخت و بدون‌اولویت از ۱۷ لینک بود (تنظیمات LLM هم‌وزن با تأیید کسب‌وکار). به ۶ گروه کاری بازچینی شد: عملیات روزانه (تأیید، تقلب، اختلافات، مذاکرات)، مالی (درآمد، امانی، حاشیه سود)، رشد، پلتفرم (Tenants/Agents/LLM/SSO)، انطباق (Audit/Compliance)، سیستم (اعلان‌ها/عملکرد/تنظیمات). کلیدهای ترجمه‌ی مرده‌ی مربوط به صفحه‌ی KPI قدیمی Commerce (`messages.dashboard.total_*`) هم در همین گذر حذف شدند.

**فایل‌های اصلی:** `NexusNegotiationsController`، `NexusOverviewController`، `GetPlatformOverviewAction`، `NegotiationRepositoryInterface::findAll()` + پیاده‌سازی Eloquent، ویوهای `dashboard/nexus/{negotiations,overview}/*.blade.php`، `layouts/dashboard.blade.php` بازنویسی‌شده.

**تست:** ۱۰ تست Feature جدید (۶ روی Negotiations Monitor شامل چک ۴۰۴ برای مذاکره‌ی ناموجود و polling، ۴ روی Overview شامل شمارش واقعی و ریدایرکت `/dashboard`) — همه پاس. کل سوییت: **۱۶۶۳ pass / ۲۷۳ fail** — بدون رگرسیون (baseline ۱۶۵۳؛ دقیقاً ۱۰ تست تازه).

**کامیت:** `feat(dashboard): add Live Negotiation Monitor, real admin home, grouped nav`.

---

## ابزار کمکی: `nexus:demo-negotiation`

بعد از ساخت Live Negotiation Monitor، کاربر می‌خواست واقعاً یک مذاکره‌ی زنده ببینه. بررسی شد که **هیچ مسیر UI یا خودکاری برای شروع مذاکره وجود نداره** — تنها مسیر واقعی، فراخوانی `nexus.negotiation.propose` روی MCP با Bearer Token یک Agent واقعیه (یعنی یک کلاینت هوش‌مصنوعی بیرونی). تنها استثنای خودکار موجود، قانون Automation از نوع `RecurringOrder` بود (فقط با هدف/طرف‌مقابل از‌پیش‌پیکربندی‌شده، نه matching باز).

دستور `nexus:demo-negotiation {supplier}` ساخته شد که همون Actionهایی رو که یک MCP caller واقعی صدا می‌زنه به‌ترتیب صدا می‌زنه (propose → counter → accept)، با `sleep(4)` بین مراحل (غیرفعال زیر تست) تا در صفحه‌ی Monitor با polling سه‌ثانیه‌ای قابل مشاهده باشه. تست شد روی کسب‌وکار واقعی کاربر (business #23، «پایپ گستر») — مذاکره #12 با موفقیت accepted شد و Contract/Escrow واقعی ساخته شد.

**فایل‌ها:** `app/Console/Commands/DemoNegotiationCommand.php`، `tests/Feature/Console/DemoNegotiationCommandTest.php` (۳ تست).

**کامیت:** `feat(nexus): add nexus:demo-negotiation command`.

⚠️ **این دستور با فاز بعدی (Agent Runtime، زیر) ممکنه رفتارش عوض بشه** — چون بعد از پیاده‌سازی auto-respond، وقتی این دستور مرحله‌ی propose رو می‌زنه، خودِ تأمین‌کننده احتمالاً بلافاصله (به‌صورت خودکار و واقعی) counter/accept/reject می‌کنه، قبل از اینکه دستور برسه مرحله‌ی دستی `SendCounterOfferAction` خودش رو صدا بزنه — که باعث Exception می‌شه (چون negotiation دیگه در وضعیت proposed/countered نیست). این دستور باید به‌روزرسانی بشه (فقط propose کنه و صبر/گزارش بده، دیگه خودش counter/accept رو شبیه‌سازی نکنه) — این کار به‌عنوان بخشی از «Phase D» زیر مستند شده.

---

## در حال ساخت: Autonomous Agent Runtime (شروع‌شده، ادامه لازم دارد)

**دستور کاربر (عیناً):** «باید اتوماتیک پیدا کنند مذاکره کنند... یه وقت هست تو ۳۰ ثانیه باید با هم مذاکره کنند... خودت هر مدل که میدونی حرفه‌ای و جذاب و کاملاً حرفه‌ایه پیاده‌اش کن.»

**زمینه‌ی سوال:** کاربر پرسید چرا Agentهایی که کاملاً داخل خودِ Nexus ساخته می‌شن (نه با SDK بیرونی) خودشون کاری نمی‌کنن. بررسی دقیق (`routes/console.php`، هر ۷ پردازش زمان‌بندی‌شده‌ی کل پلتفرم) تأیید کرد: **هیچ فرآیندی که خودِ پلتفرم اجرا کنه و به‌جای یک Business تصمیم بگیره وجود نداره.** `Agent` فقط یک هویت آماده‌به‌کاره (شخصیت، Token واقعی)، نه یک فرآیند در حال اجرا. این یک خلأ واقعی و مستنده، نه سهل‌انگاری قبلی.

### طراحی نهایی (تصمیم‌گرفته‌شده، قبل از پیاده‌سازی)

**مدل ترکیبی دو‌لایه — دقیقاً برای رفع نگرانی «۳۰ ثانیه در برابر ۱۰-۱۵ دقیقه»:**

**لایه ۱ — پاسخ واکنشی (همزمان/Synchronous، واقعاً آنی، نه صف‌محور):**
- یافته‌ی کلیدی تحقیق: `QUEUE_CONNECTION=database` (نه `sync`) — یعنی اگر از Laravel Queue استفاده می‌شد، بدون یک `php artisan queue:work` واقعاً در حال اجرا، هیچ‌چیز کار نمی‌کرد (خطر بی‌صدا خراب‌بودن). تصمیم: به‌جای صف، از یک Event Listener **همزمان** (بدون `ShouldQueue`) استفاده می‌شه — چون منطق تصمیم‌گیری rule-based و ارزونه (چند کوئری DB + محاسبه‌ی ساده، نه یک فراخوانی LLM شبکه‌ای)، اجرای همزمان درون همون request/command اصلی، در حد میلی‌ثانیه تمام می‌شه — سریع‌تر از هر مدل صف‌محور، بدون نیاز به زیرساخت اضافه.
- رویداد جدید: `NegotiationMessageWasRecorded` — در `InitiateNegotiationAction` و `SendCounterOfferAction` بعد از ذخیره‌ی پیام dispatch می‌شه (نه در Accept/Reject، چون اونا پایانی‌ان).
- Listener جدید: `AutoRespondToNegotiationListener` — طرف *گیرنده*‌ی پیام (`negotiation->otherParty(senderBusinessId)`) رو پیدا می‌کنه، اگه Agentش auto-respond فعال داشته باشه (پیش‌فرض: فعال — پایین ببین)، `AutonomousNegotiationStrategy` رو صدا می‌زنه و بر اساس نتیجه `AcceptDealAction`/`SendCounterOfferAction`/`RejectDealAction` رو به نمایندگی از همون Business صدا می‌زنه.
- **بازگشت طبیعی و محدود:** وقتی Listener خودش `SendCounterOfferAction` صدا می‌زنه، همون Action دوباره رویداد رو dispatch می‌کنه → این‌بار طرف مقابل (اگه اونم auto-respond داشته باشه) واکنش نشون می‌ده. این زنجیره توسط `maxRounds` (پیش‌فرض ۵، از `NEXUS_NEGOTIATION_MAX_ROUNDS`) طبیعتاً محدوده — یعنی یک مذاکره‌ی کامل بین دو Agent خودکار می‌تونه در همون یک فراخوانی اولیه (چه MCP، چه اسکریپت، چه AutoDiscover لایه‌ی ۲) به نتیجه برسه، در حد میلی‌ثانیه تا چند صدم ثانیه.
- **حیاتی:** Listener باید `try/catch` کامل داشته باشه (`InvalidNegotiationStateException`/`NegotiationRoundLimitExceededException`/هرچیز دیگه) و هرگز نباید اجازه بده یک خطا به caller اصلی برسه — چون این کد الان به‌صورت همزمان درون هر request/command ای که یک پیام مذاکره می‌سازه اجرا می‌شه.
- **تنظیم auto-respond per-agent:** به‌جای migration/ستون جدید، از فیلد **از‌قبل‌موجود و کاملاً بلااستفاده‌ی** `Agent::$strategies` (JSON، `setStrategies()`) استفاده می‌شه — دقیقاً همون الگوی escape-hatch که `authorityLimits`/`Product.attributes`/`AutomationRule.config` از قبل دارن. کلید `strategies['auto_respond'] ?? true` (پیش‌فرض فعال — چون کل هدف اینه که از روز اول کار کنه، بدون نیاز کسب‌وکار به تنظیم چیزی) و `strategies['tolerance_percent'] ?? 15` (چقدر از قیمت لیست حاضره کوتاه بیاد).

**منطق تصمیم‌گیری (`AutonomousNegotiationStrategy`, سرویس جدید در Application layer دامنه‌ی Negotiation):**
- کاملاً rule-based (بدون فراخوانی LLM — مطابق پیش‌فرض «۸۰٪ Rule Engine» خودِ CLAUDE.md؛ `NegotiationReasoningService` موجود فقط *توضیح* تولید می‌کنه، نه قیمت — این سرویس باید از صفر منطق «چه قیمتی پیشنهاد بدم» رو بنویسه).
- تشخیص نقش: اگه `catalogItemId` متعلق به همین Business باشه (از `Product`/`Service::businessId()`) → فروشنده (می‌خواد قیمت بالاتر)، وگرنه → خریدار (می‌خواد قیمت پایین‌تر). قیمت لیست به‌عنوان لنگر.
- محدوده‌ی قابل‌قبول: `listPrice ± tolerance_percent`. اگه پیشنهاد رسیده داخل این محدوده‌ست → Accept. اگه دور ولی round باقی مونده → Counter در نقطه‌ی میانی بین پیشنهاد رسیده و لنگر خودی (با یک حداقل گام، برای جلوگیری از چرخه‌ی بی‌پایان). اگه round تموم شده یا خیلی دوره (بیش از ۲ برابر tolerance) → Reject.

**لایه ۲ — کشف پیش‌کنشی (Automation Rule تازه، هر ۵ دقیقه):**
- `AutomationRuleType` یک case چهارم می‌گیره: `AutoDiscover`. برخلاف `RecurringOrder` (طرف‌مقابل/آیتم از‌پیش‌مشخص)، این نوع طرف‌مقابل رو خودش پیدا می‌کنه.
- Config: `industry?`, `catalogItemType`, `maxPriceAmount`, `priceCurrency`, `quantity`.
- الگوریتم در `ProcessAutomationRulesAction::processAutoDiscover()`: از `GetRecommendationsAction` (موجود، هم‌صنعتی‌های رتبه‌بندی‌شده بر اساس شهرت) کاندیدها رو می‌گیره؛ برای اولین کاندیدی که (الف) آیتم منطبق با `catalogItemType` در کاتالوگش داره و (ب) مذاکره‌ی باز (proposed/countered/pending_approval) از قبل با این Business نداره، با قیمت `min(maxPriceAmount, listPrice) × ۰.۹` (برای فضای چانه‌زنی) پیشنهاد می‌ده — یعنی مستقیم `InitiateNegotiationAction` رو صدا می‌زنه، دقیقاً مثل `processRecurringOrder` موجود.
- زمان‌بندی: `ProcessAutomationRulesCommand` از `->hourly()` به `->everyFiveMinutes()` تغییر می‌کنه (برای هر سه نوع rule، بی‌خطر چون هرکدوم cooldown خودشون رو دارن — `canRetriggerAt` که از قبل روی entity هست). AutoDiscover پیش‌فرض cooldown ۱۰ دقیقه‌ای می‌گیره (پارامتر داخلی Action، نه فیلد جدید روی entity).

### چک‌لیست باقی‌مانده (اگر این سشن قطع شد، از اینجا ادامه بده)

- [ ] `NegotiationMessageWasRecorded` event + dispatch در `InitiateNegotiationAction`/`SendCounterOfferAction`
- [ ] `AutonomousNegotiationStrategy` سرویس (تصمیم‌گیری قیمت، rule-based)
- [ ] `AutoRespondToNegotiationListener` + ثبت در `NexusServiceProvider::boot()`
- [ ] `Agent::autoRespondEnabled()`/`negotiationTolerancePercent()` (خواندن از `strategies` موجود، بدون migration)
- [ ] تست‌های Feature: دو Business با auto-respond فعال، یک `InitiateNegotiationAction` صدا زده می‌شه، انتظار می‌ره negotiation خودش به accepted/rejected برسه بدون فراخوانی دستی بعدی
- [ ] `AutomationRuleType::AutoDiscover` + `AutomationRule::forAutoDiscover()` + `CreateAutoDiscoverRuleAction`
- [ ] `ProcessAutomationRulesAction::processAutoDiscover()`
- [ ] `ProcessAutomationRulesCommand` زمان‌بندی → `everyFiveMinutes()`
- [ ] به‌روزرسانی `nexus:demo-negotiation` (فقط propose کنه، دیگه دستی counter/accept نزنه — چون واقعی خودکار انجام می‌شه)
- [ ] UI: تاگل ساده‌ی auto-respond در داشبورد کسب‌وکار (اختیاری اگه زمان کم اومد — پیش‌فرض true یعنی حتی بدون UI هم کار می‌کنه)
- [ ] ترجمه‌های fa/en مربوطه
- [ ] اجرای کامل تست‌ها، commit، push، و به‌روزرسانی همین بخش با نتیجه‌ی نهایی

---
