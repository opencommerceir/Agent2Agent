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
