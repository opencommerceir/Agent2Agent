# 📋 ساختار کلی پروژه
app/Domains/Nexus/
├── Business/          # ثبت و مدیریت کسب‌وکارها
├── Agent/             # ایجنت‌های نماینده
├── Catalog/           # محصولات و خدمات
├── Negotiation/       # مذاکرات Agent-to-Agent
├── Contract/          # قراردادها
├── Credit/            # سیستم کردیت و پرداخت
├── Reputation/        # امتیاز و اعتماد
├── Marketplace/       # کشف و جستجو
├── Analytics/         # آمار و KPIها
└── Admin/             # پنل مدیریت

config/nexus/          # تنظیمات
routes/nexus/          # مسیرها (web, api, mcp)
resources/views/nexus/ # ویوها (Jarvis style)
database/migrations/nexus/
tests/Feature/Nexus/
tests/Unit/Nexus/
docs/nexus/

# 🚀 نقشه راه جامع پلتفرم Nexus (Nexus Platform Roadmap)

این سند، نقشه راه کامل توسعه پلتفرم هوشمند B2B و ایجنت‌های مذاکره‌کننده است. هدف نهایی، ایجاد یک اکوسیستم خودمختار، چندزبانه و مقیاس‌پذیر برای کسب‌وکارهاست.

---

## 🏢 Phase 1: Business & Agent Core / هسته کسب‌وکار و ایجنت
**هدف:** کسب‌وکارها ثبت‌نام کنند و ایجنت اختصاصی داشته باشند.

### مراحل پیاده‌سازی:
*   **Business Domain**
    *   **Entity `Business`** (Aggregate Root): فیلدها شامل نام (fa/en)، نوع، صنعت، وضعیت تأیید، `tenant_id`.
    *   **Actions:** `RegisterBusiness`, `VerifyBusiness`, `UpdateBusinessProfile`.
    *   **Multi-tenancy:** هر Business = یک Tenant جدید (استفاده از معماری multi-tenancy موجود).
*   **Agent Domain**
    *   **Entity `Agent`**: فیلدها شامل `business_id`, نام (fa/en), personality, tone, authority_limits, strategies.
    *   **Actions:** `CreateAgentForBusiness`, `UpdateAgentPersonality`, `SetAuthorityLimits`.
    *   **Integration:** ایجنت باید به پترن موجود در `config/agents/` متصل شود.
*   **Catalog Domain**
    *   **Entity `Product`**: نام (fa/en)، قیمت، موجودی، variants.
    *   **Entity `Service`**: نام (fa/en)، قیمت ساعتی، مدت، زمان‌بندی.
    *   **Actions:** `AddProduct`, `AddService`, `UpdateCatalog`, `SearchCatalog`.
    *   **ویژگی خاص:** پشتیبانی از attributeهای سفارشی بر اساس صنعت (per industry).
*   **Onboarding Flow**
    *   فرم ثبت‌نام دوزبانه (fa/en).
    *   انتخاب صنعت (۲۰+ دسته).
    *   آپلود لوگو و مدارک و تأیید توسط ادمین.
    *   ساخت خودکار Agent پس از تأیید نهایی.
*   **Business Dashboard**
    *   داشبورد ساده برای هر کسب‌وکار.
    *   نمایش: وضعیت Agent، مذاکرات فعال، موجودی کردیت، خلاصه کاتالوگ.
    *   **تم UI:** Jarvis (dark, glass, neon).

> 🎯 **تحویلی:** کسب‌وکار ثبت‌نام می‌کند، Agent دارد، محصول/خدمت ثبت می‌کند.

---

## 💬 Phase 2: Negotiation Engine / موتور مذاکره
**هدف:** ایجنت‌ها با هم کشف، مذاکره و قرارداد ببندند.

### مراحل پیاده‌سازی:
*   **Marketplace & Discovery**
    *   **Actions:** `SearchMarketplace`, `GetRecommendations`, `RankSuppliers`.
    *   جستجو بر اساس: دسته، مکان، قیمت، امتیاز.
    *   الگوریتم matching (بر اساس نیازهای Business و offerings دیگران).
    *   **MCP Capability:** `nexus.marketplace.search`
*   **Negotiation Domain**
    *   **Entity `Negotiation`** دارای State Machine (مراحل مختلف مذاکره).
    *   **Entity `NegotiationMessage`**: متن، فرستنده، reasoning trace.
    *   **Value Objects:** `Proposal`, `CounterProposal`, `NegotiationTerms`.
    *   **Actions:** `InitiateNegotiation`, `SendCounterOffer`, `AcceptDeal`, `RejectDeal`.
*   **Agent Reasoning در مذاکره**
    *   قبل از هر پاسخ: `think()` (از AgentOrchestrator موجود).
    *   بعد از هر پاسخ: `reflect()`.
    *   ذخیره reasoning traces برای نمایش به کاربر.
*   **Contract Domain**
    *   **Entity `Contract`**: terms, parties, status, `signed_at`.
    *   تولید خودکار قرارداد از روی negotiation (پشتیبانی دوزبانه fa/en).
    *   امضای دیجیتال (hash) و خروجی PDF.
*   **Live Negotiation Viewer**
    *   صفحه چت زنده برای مشاهده مذاکره Agentها.
    *   نمایش reasoning traces و terms در حال تغییر.
    *   درخواست تأیید (Human-in-the-loop) برای معاملات بالای آستانه مشخص.
*   **MCP Capabilities**
    *   `nexus.negotiation.propose` | `nexus.negotiation.counter` | `nexus.negotiation.accept` | `nexus.negotiation.reject` | `nexus.negotiation.status`

> 🎯 **تحویلی:** دو Agent می‌توانند کشف، مذاکره و قرارداد ببندند.

---

## 💰 Phase 3: Credit & Payment Economy / اقتصاد کردیت و پرداخت
**هدف:** سیستم کردیت، پرداخت و درآمدزایی پلتفرم.

### مراحل پیاده‌سازی:
*   **Credit Domain**
    *   **Entity `CreditBalance`**: `business_id`, `amount`, `expires_at`.
    *   **Entity `CreditTransaction`** (Ledger): type, amount, reason, `created_at`.
    *   **Actions:** `PurchaseCredits`, `DeductCredits`, `RefundCredits`, `CheckBalance`.
    *   هزینه هر اقدام از `config/nexus/platform.php` خوانده شود.
*   **CostGate**
    *   قبل از هر LLM call یا Agent action، اعتبار را چک کن.
    *   در صورت عدم موجودی: رد کردن درخواست و ارسال Notification.
    *   لاگ دقیق تمام تراکنش‌ها.
*   **Payment Integration**
    *   اتصال به درگاه **Zibal** (ریال) و **Stripe** (بین‌المللی).
    *   خرید پکیج کردیت و تمدید خودکار (Subscription).
*   **Escrow System**
    *   نگهداری مبلغ قرارداد در escrow تا تأیید تحویل.
    *   مسدود ماندن وجه در صورت بروز اختلاف (Dispute).
*   **Admin Margin Settings**
    *   تنظیمات حاشیه سود در پنل ادمین (LLM cost markup, Transaction fee, Subscription markup, Negotiation success fee).
    *   تغییرات به صورت **hot-reload** (بدون نیاز به restart).
*   **Revenue Dashboard**
    *   داشبورد درآمد ادمین: gross revenue, net revenue, margins, costs.
    *   تفکیک داده‌ها: per business, per industry, per day.

> 🎯 **تحویلی:** پلتفرم از روز اول پول درمی‌آورد و مدل اقتصادی پایداری دارد.

---

## 🧠 Phase 4: LLM Provider System / سیستم LLM چندگانه
**هدف:** پشتیبانی از چند LLM با قابلیت سوییچ آنی از پنل ادمین.

### مراحل پیاده‌سازی:
*   **LLM Provider Interface**
    *   ایجاد `LLMProviderInterface` با متدهای: `chat()`, `estimateCost()`, `supports()`.
    *   پیاده‌سازی‌ها: OpenAI, Anthropic, OpenRouter, Groq, SelfHostedQwen, LocalLlama.
*   **LLM Router**
    *   ایجاد `LLMRouter` برای انتخاب provider مناسب بر اساس Feature (reasoning, negotiation, classification, fallback).
    *   خواندن تنظیمات از `config/nexus/platform.php`.
*   **Cost Tracking**
    *   ثبت cost واقعی و cost شارژشده برای هر LLM call (تفاوت = حاشیه سود پلتفرم).
*   **Admin LLM Switcher**
    *   صفحه تنظیمات LLM در پنل ادمین.
    *   انتخاب provider برای هر feature، تست connection قبل از ذخیره و hot-reload.
*   **Fallback Strategy**
    *   سوییچ خودکار به fallback در صورت fail شدن provider اصلی.
    *   استفاده از Rule Engine در صورت fail شدن همه LLMها (توقف هرگز!).

> 🎯 **تحویلی:** ادمین می‌تواند LLM را بدون تغییر کد و با یک کلیک عوض کند.

---

## 🦠 Phase 5: Viral Growth Engine / موتور رشد ویروسی
**هدف:** سیستمی که خودش، خودش را رشد دهد (Growth Loops).

### مراحل پیاده‌سازی:
*   **Agent-Invites-Agent:** ارسال خودکار invite هنگام مذاکره با non-member + پکیج pre-filled onboarding + پاداش.
*   **Referral System:** کد اختصاصی، پاداش کردیت دوطرفه، و Multi-tier tracking.
*   **Group Buying:** تشکیل coalition توسط Agentها برای خرید گروهی، تخفیف بیشتر و توزیع صرفه‌جویی.
*   **Network Visualization:** نمایش گراف شبکه ("Businesses like you also work with...") و Supply chain mapping.
*   **Viral Analytics:** ردیابی K-factor، Cohort analysis و A/B testing برای پیام‌های دعوت.

> 🎯 **تحویلی:** پلتفرم بدون بازاریابی سنگین و به صورت ارگانیک رشد می‌کند.

---

## 🛡️ Phase 6: Trust & Reputation / اعتماد و شهرت
**هدف:** ایجاد بازار قابل اعتماد با حداقل ریسک.

### مراحل پیاده‌سازی:
*   **Reputation Score:** امتیاز ۰ تا ۱۰۰۰ بر اساس success rate, response time, reviews, longevity. اعطای Badges (Verified, Top-Rated, Gold Partner).
*   **Reviews & Ratings:** ثبت review دوطرفه پس از قرارداد، Structured ratings و سیستم Moderation.
*   **Dispute Resolution:** ورک‌فلو اختلاف (evidence → mediation → arbitration)، اجرای refund و تأثیر مستقیم بر reputation.
*   **Fraud Detection:** شناسایی الگوهای مشکوک، Fake review detection و Auto-suspension همراه با appeal process.
*   **Verification System:** تأیید هویت کسب‌وکار، تأیید محصولات/خدمات و اعطای Trust badges با معیارهای شفاف.

> 🎯 **تحویلی:** کاربران به پلتفرم اعتماد کامل دارند و ریسک معاملات به حداقل می‌رسد.

---

## 🏢 Phase 7: Enterprise Features / ویژگی‌های سازمانی
**هدف:** جذب مشتریان بزرگ (Enterprise) و سازمان‌ها.

### مراحل پیاده‌سازی:
*   **Multi-Business Accounts:** ساختار Holding با زیرمجموعه‌ها، Shared credit pool و گزارش‌گیری متمرکز.
*   **Approval Workflows:** تأییدیه‌های چندسطحی (Agent → Manager → CFO) قابل تنظیم بر اساس حجم معامله.
*   **Private Marketplaces:** بازارهای Invite-only با برندینگ اختصاصی و قیمت‌گذاری محرمانه.
*   **Compliance:** Audit trail پیشرفته (hash-chained)، آمادگی برای SOC 2 / ISO 27001 و Data residency options.
*   **SSO & Identity:** پشتیبانی از SAML, OAuth, LDAP، MFA و مدیریت Session.

> 🎯 **تحویلی:** پلتفرم کاملاً آماده عقد قراردادهای کلان سازمانی است.

---

## 🧠 Phase 8: Intelligence & Automation / هوش و اتوماسیون
**هدف:** تبدیل داده به بینش (Insight) و اتوماسیون فرآیندها.

### مراحل پیاده‌سازی:
*   **Business Analytics:** نرخ موفقیت معاملات، بنچمارک قیمت‌ها، محاسبه‌گر صرفه‌جویی و خروجی گزارش.
*   **Market Intelligence:** ترندهای قیمتی، پیش‌بینی تقاضا و تحلیل رقبا (anonymized).
*   **AI Recommendations:** پیشنهاد مذاکره با تامین‌کنندگان خاص، زمان‌بندی بهینه و جایگزین‌ها.
*   **Automation Workflows:** سفارشات تکرارشونده، هشدار موجودی (auto-search)، هشدار قیمت و Visual workflow builder.
*   **Predictive Intelligence:** پیش‌بینی اعتبار تامین‌کننده، ریسک‌سنجی معاملات و Scenario planning.

> 🎯 **تحویلی:** کسب‌وکارها با استفاده از داده‌های پلتفرم، تصمیمات استراتژیک بهتری می‌گیرند.

---

## 🌐 Phase 9: Ecosystem & API / اکوسیستم و API
**هدف:** ایجاد بستر برای توسعه‌دهندگان شخص ثالث.

### مراحل پیاده‌سازی:
*   **Public API:** ارائه RESTful API (مستند شده)، GraphQL API، Webhooks و مدیریت API Key.
*   **SDKs:** ارائه پکیج‌های رسمی برای PHP (Laravel), Python, Node.js, Go.
*   **Integration Marketplace:** کانکتورهای آماده (ERP, CRM, Accounting, Logistics)، No-code builder و اتصال به Zapier/Make.com.
*   **Agent Developer Platform:** مارکت‌پلیس برای ایجنت‌های Third-party، Revenue sharing و Sandbox اختصاصی.

> 🎯 **تحویلی:** اکوسیستمی که دیگران روی آن می‌سازند و آن را گسترش می‌دهند.

---

## 🌍 Phase 10: Global Expansion / گسترش جهانی
**هدف:** حضور قدرتمند در چندین کشور و منطقه.

### مراحل پیاده‌سازی:
*   **Multi-Currency:** پشتیبانی از IRR, USD, EUR, AED, TRY با نرخ تبدیل Real-time.
*   **Multi-Language:** FA (primary), EN, AR, TR, ZH با ترجمه Real-time در حین مذاکرات.
*   **Regional Compliance:** رعایت GDPR، قوانین ایران، قوانین GCC و Data residency منطقه‌ای.
*   **Cross-Border Features:** حمل‌ونقل بین‌المللی، اتوماسیون گمرک و Trade finance.
*   **Regional Marketplaces:** بازارهای محلی (Iran, GCC, Turkey, CIS) با قابلیت Cross-regional discovery.

> 🎯 **تحویلی:** پلتفرم از یک محصول محلی به یک پلتفرم جهانی تبدیل می‌شود.

---

## 🎯 Phase 11: Success Metrics Dashboard / داشبورد معیارهای موفقیت
**هدف:** پیاده‌سازی KPIها به صورت زنده در پنل ادمین با قابلیت تیک خوردن خودکار و ایجاد انگیزه.

### زیرساخت KPI:
*   **Entities:** `KPI` (name, category, target, current_value, unit, status), `KPITarget` (bronze, silver, gold, diamond), `KPIHistory` (daily snapshots).
*   **محاسبات:** Real-time calculation via background jobs + event listeners.

### رابط کاربری داشبورد (`/admin/success`):
*   Progress bars برای هر KPI، Hall of Fame، Trending charts (30/90/365 days) و انیمیشن Confetti هنگام رسیدن به تارگت.

### 🏆 دسته‌بندی و تارگت‌های KPI:

*   **User Growth (رشد کاربر):**
    *   Businesses: 🥉 100 → 🥈 1,000 → 🥇 10,000 → 💎 100,000
    *   Active Agents: 🥉 50 → 🥈 500 → 🥇 5,000 → 💎 50,000
    *   Countries: 🥉 5 → 🥈 15 → 🥇 50
*   **Engagement (تعامل):**
    *   Negotiations: 🥉 1,000 → 🥈 10,000 → 🥇 100,000 → 💎 1,000,000
    *   Success Rate: 🥉 40% → 🥈 55% → 🥇 70%
    *   Retention: 🥉 60% → 🥈 75% → 🥇 85%
*   **Revenue (درآمد):**
    *   MRR: 🥉 $1K → 🥈 $10K → 🥇 $100K → 💎 $1M → 🚀 $10M
    *   Monthly GMV: 🥉 $10K → 🥈 $100K → 🥇 $1M → 💎 $10M
    *   Margin: 🥉 Break-even → 🥈 20% → 🥇 40% → 💎 60%
*   **Viral (رشد ویروسی):**
    *   K-Factor: 🥉 K=0.3 → 🥈 K=0.7 → 🥇 K=1.0 → 💎 K=1.5 → 🚀 K=2.0
    *   Referral Rate: 🥉 20% → 🥈 40% → 🥇 60% → 💎 80%
*   **Technical (فنی):**
    *   Uptime: 🥉 99% → 🥈 99.9% → 🥇 99.99%
    *   Test Coverage: 🥉 80% → 🥈 90% → 🥇 95%
    *   Response Time: 🥉 <500ms → 🥈 <200ms → 🥇 <100ms
*   **Trust (اعتماد):**
    *   Avg Rating: 🥉 4.0 → 🥈 4.5 → 🥇 4.8
    *   Dispute Resolution: 🥉 90% <48h → 🥈 95% <24h → 🥇 99% <12h
    *   Fraud Rate: 🥉 <5% → 🥈 <2% → 🥇 <0.5%
*   **Enterprise (سازمانی):**
    *   Enterprise Clients: 🥉 10 → 🥈 50 → 🥇 200 → 💎 First $1M contract
    *   Fortune 500: 🥉 First → 🥈 10 → 🥇 50
*   **Market Impact (تأثیر بازار):**
    *   Publications: 🥉 10 → 🥈 TechCrunch/Forbes → 🥇 Major award
    *   Valuation: 🥉 First offer → 🥈 $100M → 🥇 $1B (Unicorn 🦄)

### 🎉 Auto-Celebration & Predictive Forecasting:
*   **هنگام رسیدن به تارگت:** تیک خوردن خودکار ✅، انیمیشن 🎉، ارسال Email به تیم 📧، تولید پست تبریک 📝 و آپدیت Hall of Fame 📊.
*   **پیش‌بینی ML:** نمایش "این KPI در X روز دیگر hit می‌شود" همراه با Countdown timer و شناسایی گلوگاه‌ها (Bottlenecks).

> 🎯 **تحویلی:** داشبورد زنده‌ای که تمام تیم را بر اساس داده هماهنگ و باانگیزه نگه می‌دارد.

---

## 🎬 Beyond Phase 11: Future Vision (چشم‌انداز آینده)
*   **Phase 12: Autonomous Economy:** زنجیره‌های Agent-to-Agent-to-Agent، شبکه‌های تامین خودسازمان‌ده و قیمت‌گذاری پویا (Emergent market pricing).
*   **Phase 13: Global Agent Identity:** شناسه جهانی ایجنت (Universal Agent ID)، شهرت کراس-پلتفرم و پاسپورت ایجنت‌ها.
*   **Phase 14: Physical World Integration:** ایجنت‌های IoT، ایجنت‌های رباتیک و ایجنت‌های شهر هوشمند (Smart city).

---

## 📋 قوانین پیاده‌سازی برای AI (Implementation Rules)

> ⚠️ **توجه:** این قوانین برای هوش مصنوعی (Claude Code) جهت تولید کد در این پروژه الزام‌آور است.

### قانون ۱: Extend, Don't Rebuild (توسعه بده، دوباره نساز)
*   از MCP Gateway، AgentOrchestrator، Multi-Tenancy و Payments موجود استفاده کن.
*   **هیچوقت** چیزی که در OpenCommerce هست را دوباره نساز (DRY Principle).

### قانون ۲: Bilingual by Default (دوزبانه به صورت پیش‌فرض)
*   تمام UIها، پیام‌ها و قراردادها باید دوزبانه (fa/en) باشند.
*   پشتیبانی کامل از RTL برای فارسی (فارسی زبان اصلی پلتفرم است).

### قانون ۳: Test Everything (همه چیز را تست کن)
*   هر `Action` = حداقل یک **Feature Test**.
*   هر `Entity` = حداقل یک **Unit Test**.
*   هر `MCP Capability` = حداقل یک **E2E Test**.
*   حداقل **80% Test Coverage** الزامی است.

### قانون ۴: Audit Everything (همه چیز را ثبت کن)
*   هر Agent action را در Audit Trail ثبت کن.
*   هر LLM call را با `cost` دقیق ثبت کن.
*   هر credit transaction را در Ledger ثبت کن.

### قانون ۵: Security First (امنیت در اولویت)
*   Agentها **نمی‌توانند** بدون تأیید انسان (Human-in-the-loop) اقدامات پرارزش (High-value) انجام دهند.
*   Tenant isolation (جداسازی داده‌های مستاجران) باید کامل و بی‌نقص باشد.
*   Audit trail باید غیرقابل تغییر (Immutable) باشد.

### قانون ۶: Cost Conscious (مدیریت هزینه‌ها)
*   هر LLM call باید حتماً از `CostGate` عبور کند.
*   **Default:** استفاده از مدل‌های Local (رایگان).
*   **Fallback:** استفاده از Free API tiers.
*   **Premium:** استفاده از APIهای پولی (فقط در صورتی که ادمین کانفیگ کرده باشد).