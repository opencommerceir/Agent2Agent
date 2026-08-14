# AgentIO

> **اولین اکوسیستم اقتصاد ایجنت-به-ایجنت (Agent-to-Agent Economy)** — جایی که هر کسب‌وکار یک ایجنت هوش مصنوعی اختصاصی دارد که به نمایندگی از او کشف، مذاکره، و معامله می‌کند.

---

## سه نام، یک شرکت

این مخزن سه لایه‌ی متفاوت را کنار هم نگه می‌دارد که ارزش دارد از همین ابتدا روشن شوند:

| لایه | نقش | مخاطب اصلی |
|---|---|---|
| **AgentIO** | محصول نهایی، تجربه‌ی کاربری، برند بازار | کسب‌وکارهای خریدار/فروشنده |
| **Nexus** | موتور مذاکره، اعتماد، اقتصاد کردیت، و هوش مصنوعی که AgentIO را به حرکت درمی‌آورد (`app/Domains/Nexus/`) | تیم فنی، توسعه‌دهندگان ثالث، مشتریان سازمانی API |
| **OpenCommerce** | شرکت مالک این مخزن و زیرساخت پایه‌ای که Nexus روی آن ساخته شده | سرمایه‌گذاران، شرکا |

به زبان ساده: **AgentIO محصولی است که فروخته می‌شود، Nexus موتوری است که ساخته شده، و OpenCommerce شرکتی است که هر دو را می‌سازد.** جزئیات کامل این جداسازی در [`tutorials/base/01-introduction/02-what-is-agentio.md`](./tutorials/base/01-introduction/02-what-is-agentio.md) آمده.

> **یادداشت لایسنس:** کد پایه‌ی OpenCommerce Platform تحت [MIT](./LICENSE) باقی می‌ماند؛ کد اختصاصی Nexus (`app/Domains/Nexus/` و پیکربندی/روت/ویوهای مرتبط) تحت شرایط پیش‌نویس [`LICENSE-NEXUS.md`](./LICENSE-NEXUS.md) دو-لایسنسه شده است.

---

## مشکلی که حل می‌کند

تجارت B2B هنوز به‌صورت انسانی و کند اجرا می‌شود: پیدا کردن طرف مقابل مناسب، رد و بدل پیام برای قیمت و شرایط، جلسات و ایمیل‌های متعدد، و در نهایت اعتماد به این‌که طرف مقابل واقعاً به تعهدش عمل می‌کند. کسب‌وکارهای کوچک و متوسط وقت و نیروی انسانی کافی برای مذاکره‌ی مداوم با ده‌ها تأمین‌کننده و خریدار بالقوه ندارند.

**AgentIO** این چرخه را خودکار می‌کند: هر کسب‌وکاری که ثبت‌نام می‌کند، صاحب یک **ایجنت هوش مصنوعی** می‌شود — نماینده‌ای دیجیتال با هویت، مجوز، و اختیار مشخص. این ایجنت‌ها یکدیگر را در بازار پیدا می‌کنند، بر سر قیمت و شرایط مذاکره می‌کنند، توافق را به‌صورت یک قرارداد رسمی (PDF دوزبانه با امضای هش‌شده) ثبت می‌کنند، و معامله را از طریق یک لایه‌ی امانی (Escrow) به سرانجام می‌رسانند — همه در دقیقه‌ها، نه روزها. تصمیم‌های پرریسک همیشه با یک قانون واضح («انسان در حلقه») به تأیید صاحب کسب‌وکار برمی‌گردد؛ ایجنت هرگز به‌تنهایی از سقف اختیار تعیین‌شده عبور نمی‌کند.

شرح کامل چشم‌انداز، مدل اقتصادی، و سفر کامل مشتری/ادمین در مجموعه‌ی آموزشی [`tutorials/base/`](./tutorials/base) آمده است.

---

## معماری: توسعه، نه بازسازی

Nexus روی زیرساخت موجود **OpenCommerce Core** (هسته‌ی Identity/Auth، چندمستأجری، Permissions، MCP Gateway، Event Bus) ساخته شده — بدون این‌که هیچ‌کدام از این لایه‌ها بازنویسی یا دوباره اختراع شوند. هر قابلیت تجاری Nexus از طریق **Model Context Protocol (MCP)** به شکل «قابلیت‌های» گسسته (`nexus.negotiation.propose`، `nexus.marketplace.search`، ...) در دسترس ایجنت‌ها قرار می‌گیرد، دقیقاً همان الگویی که ماژول‌های پایه‌ای Commerce/CRM/Finance از قبل استفاده می‌کردند.

### دامنه‌های پیاده‌سازی‌شده (`app/Domains/Nexus/`)

| دامنه | مسئولیت |
|---|---|
| `Business` | ثبت‌نام، تأیید، و پروفایل کسب‌وکار |
| `Agent` | ایجنت اختصاصی هر کسب‌وکار — شخصیت، لحن، محدودیت اختیار |
| `Catalog` | محصولات و خدمات (شامل پرتال خودسرویس در `/nexus/catalog`) |
| `Marketplace` | جست‌وجو، توصیه‌های هوشمند، رتبه‌بندی تأمین‌کنندگان |
| `Negotiation` | موتور مذاکره‌ی Agent-to-Agent (state machine کامل propose→counter→accept/reject) |
| `Contract` | تولید خودکار قرارداد (PDF دوزبانه با امضای هش SHA-256) + لایه‌ی امانی Escrow (hold/release/refund/dispute) |
| `Credit` | اقتصاد کردیت — ledger غیرقابل‌تغییر، خرید کردیت (Zibal/Stripe) |
| `Llm` | Router چند-پروایدری (OpenAI/Claude/OpenRouter/مدل‌های محلی) با fallback خودکار |
| `Growth` | ارجاع (Referral)، خرید گروهی (Coalition)، دعوت خودکار |
| `Reputation` | امتیاز شهرت، نقد و بررسی، تشخیص تقلب |
| `Holding` / `Approval` | حساب‌های چندکسب‌وکاری و گردش‌کار تأییدیه‌ی چندسطحی |
| `PrivateMarketplace` | بازارهای Invite-only سازمانی |
| `Sso` | ورود سازمانی (SAML/LDAP)، جلسات، MFA |
| `Audit` | ثبت رویداد غیرقابل‌تغییر (hash-chained) |
| `Analytics` | تحلیل کسب‌وکار، هوش بازار، پیش‌بینی |
| `Automation` | قوانین خودکار (سفارش تکرارشونده، هشدار موجودی/قیمت) |
| `Developer` | کلید API، وب‌هوک، مستندات عمومی، GraphQL، مارکت‌پلیس یکپارچه‌سازی، مارکت‌پلیس ایجنت |
| `Admin` | تنظیمات پلتفرم و حاشیه سود (`PlatformSetting`, `MarginSettingsService`) — پشت‌صحنه‌ی صفحات ادمین `/dashboard/nexus/*` |

### طراحی رابط کاربری

دیزاین‌سیستم اختصاصی «Jarvis» (`resources/css/nexus.css`, `x-nexus-panel`, `x-agent-pulse`, `x-status-badge`, ...): تیره‌محور، شیشه‌ای (glassmorphism)، افکت‌های نئون (فیروزه‌ای `#00F0FF` / بنفش `#A855F7`)، دوزبانه فارسی/انگلیسی با پشتیبانی کامل RTL — فارسی زبان اصلی است.

### استراتژی هوش مصنوعی هیبریدی

پیش‌فرض: قوانین قطعی و rule-based، بدون هزینه‌ی LLM. `LLMRouter` می‌تواند به‌صورت hot-reload و به‌ازای هر ویژگی، بین OpenAI، Claude، OpenRouter، یا مدل‌های محلی (Qwen 2.5، Llama 3.2) سوییچ کند — هر Fallback خودکار و بدون توقف سرویس است. جزئیات در `docs/claude/llm-strategy.md`.

---

## پشته‌ی فناوری

- **Backend:** Laravel 12، PHP 8.2+، MySQL، Redis
- **معماری:** Modular Monolith، Domain-Driven Design، Clean Architecture، Event-Driven، Model Context Protocol (MCP)
- **پرداخت:** درگاه Zibal (ایران) و Stripe
- **PDF:** dompdf (قرارداد دوزبانه، رندر واقعی فارسی/RTL تأییدشده)
- **API عمومی:** REST v1 + GraphQL فقط‌خواندنی + وب‌هوک HMAC-امضاشده
- **SDK رسمی:** PHP، Node.js، Python، Go (`packages/nexus-sdk-*`)

---

## وضعیت پروژه

فازهای ۰ تا ۹ نقشه‌راه کامل، تست‌شده، و push شده‌اند. جزئیات کامل هر فاز (تصمیم‌های معماری، باگ‌های واقعی پیداشده و رفع‌شده، تست‌های End-to-End دستی) در [`docs/nexus/nexus_handoff.md`](./docs/nexus/nexus_handoff.md) ثبت شده — یک لاگ append-only که هرگز بازنویسی نمی‌شود.

| فاز | موضوع | وضعیت |
|---|---|---|
| Phase 0 | زیرساخت پایه (اسکلت دامنه‌ها، دیزاین‌سیستم Jarvis) | ✅ تکمیل |
| Phase 1 | هسته‌ی Business & Agent | ✅ تکمیل |
| Phase 2 | موتور مذاکره (Negotiation Engine) | ✅ تکمیل |
| Phase 3 | اقتصاد کردیت و پرداخت | ✅ تکمیل |
| Phase 4 | سیستم چند-LLM | ✅ تکمیل |
| Phase 5 | موتور رشد ویروسی (Referral/Coalition) | ✅ تکمیل |
| Phase 6 | اعتماد و شهرت (Reputation/Reviews/Dispute) | ✅ تکمیل |
| Phase 7 | ویژگی‌های سازمانی (Holding/Approval/SSO) | ✅ تکمیل |
| Phase 8 | هوش و اتوماسیون (Analytics/Automation/Predictive) | ✅ تکمیل |
| Phase 9 | اکوسیستم و API (REST/GraphQL/Webhooks/SDKs) | ✅ تکمیل |
| Phase 10 | گسترش جهانی (چندارزی/چندزبانه) | ⏳ برنامه‌ریزی‌شده، شروع‌نشده |

علاوه بر فازهای اصلی، شکاف‌های شناخته‌شده‌ی مستند به‌مرور رفع می‌شوند — مثلاً پرتال خودسرویس کاتالوگ که پس از Phase 9 اضافه شد (فرم واقعی افزودن/ویرایش محصول و خدمت در `/nexus/catalog`، به‌جای فقط API/تینکر).

**تست‌ها:** ۱۶۵۱ تست موفق از کل مجموعه (شکست‌های باقی‌مانده هرکدام از ماژول‌های تجاری غیرفعال‌شده‌ی پایه‌اند، نه رگرسیون Nexus)، که ۷۷۸ تای آن مستقیماً مخصوص Nexus است.

نقشه‌راه کامل (شامل چشم‌انداز فازهای ۱۱ و ۱۲ آینده) در [`docs/nexus-roadmap.md`](./docs/nexus-roadmap.md).

---

## شروع سریع

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

پیکربندی اختصاصی Nexus در `.env` (نمونه‌ها):

```bash
# LLM Router
NEXUS_LLM_PROVIDER=openai
NEXUS_OPENAI_API_KEY=
NEXUS_OPENROUTER_API_KEY=      # ساده‌ترین راه برای امتحان رایگان

# اقتصاد کردیت
NEXUS_CREDIT_CURRENCY=IRT
NEXUS_CREDIT_STARTING_BALANCE=0
NEXUS_PLATFORM_FEE_PERCENT=5.0

# مذاکره
NEXUS_NEGOTIATION_MAX_ROUNDS=5
NEXUS_NEGOTIATION_TIMEOUT=300

# دیزاین‌سیستم Jarvis
NEXUS_THEME_MODE=dark
NEXUS_THEME_PRIMARY="#00F0FF"
NEXUS_THEME_SECONDARY="#A855F7"
```

پرتال کسب‌وکار از مسیر `/nexus/business/register` در دسترس است؛ بخش‌های مدیریتی Nexus (تأیید کسب‌وکار، تنظیمات LLM، حاشیه سود، Escrow/Dispute، Audit، Compliance) زیر پنل ادمین موجود پلتفرم در `/dashboard/nexus/*` قرار دارند — همان گارد `admin` هسته، بدون پنل جدا.

اجرای کامل تست‌ها:

```bash
php artisan test                 # کل پلتفرم (پایه + Nexus)
php artisan test --filter=Nexus  # فقط تست‌های Nexus
```

---

## مستندات

- [`CLAUDE.md`](./CLAUDE.md) — قوانین معماری، UI/UX، و استانداردهای پروژه برای هر تغییری
- [`docs/nexus-roadmap.md`](./docs/nexus-roadmap.md) — نقشه‌راه کامل ۱۲ فاز
- [`docs/nexus/nexus_handoff.md`](./docs/nexus/nexus_handoff.md) — لاگ اجرایی append-only هر فاز
- [`docs/claude/`](./docs/claude) — استراتژی LLM، مدل درآمدی، دیزاین‌سیستم، الگوهای API، استانداردهای تست
- [`tutorials/base/`](./tutorials/base) — مجموعه‌ی آموزشی کامل: چشم‌انداز و مدل کسب‌وکار، سفر مشتری، سفر ادمین
- [`HANDOFF.md`](./HANDOFF.md) — لاگ اجرایی پلتفرم پایه‌ی OpenCommerce (پیش از Nexus)

---

## مشارکت

OpenCommerce Platform و لایه‌ی هسته‌ای آن متن‌باز است. کد اختصاصی Nexus تحت شرایط دو-لایسنسه‌ی [`LICENSE-NEXUS.md`](./LICENSE-NEXUS.md) است — پیش از هرگونه مشارکت تجاری روی این بخش، آن فایل را بخوانید.

---

## لایسنس

پلتفرم پایه‌ی OpenCommerce تحت [MIT License](./LICENSE) منتشر شده است. کد اختصاصی Nexus (`app/Domains/Nexus/`) تحت شرایط پیش‌نویس [`LICENSE-NEXUS.md`](./LICENSE-NEXUS.md) دو-لایسنسه شده — پیش از هرگونه استفاده‌ی تجاری، آن سند را بخوانید.
