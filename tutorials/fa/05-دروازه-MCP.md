← [هسته Core](04-هسته-Core.md) | بعدی: [ماژول Commerce](06-ماژول-Commerce.md) →

# ۵. دروازه‌ی MCP و مدل قابلیت‌ها

## MCP چیست؟

**MCP (Model Context Protocol)** پروتکلی است که به ایجنت‌های هوش مصنوعی اجازه می‌دهد با ابزارها (Tools/Capabilities) به شکلی استاندارد ارتباط برقرار کنند. در OpenCommerce، MCP Gateway دقیقاً همین نقش را بازی می‌کند: نقطه‌ی ورود واحد برای هر ایجنتی که می‌خواهد کاری روی پلتفرم انجام دهد.

## مسیر یک درخواست، قدم‌به‌قدم

وقتی یک ایجنت درخواست `POST /mcp/v1/execute` می‌فرستد، این توالی دقیق اجرا می‌شود:

```
۱. Authenticate   → توکن Bearer را می‌خواند، Agent واقعی را پیدا می‌کند
۲. Rate Limit     → EnforceRateLimitAction (پیش‌فرض: ۱۰۰ درخواست در دقیقه به ازای هر Agent)
۳. Authorize      → CheckPermissionAction (آیا این Agent مجوز این قابلیت را دارد؟)
۴. Execute        → CapabilityExecutionService (اجرای واقعی Handler ماژول)
```

این توالی امنیتی حیاتی است و **هرگز نباید تکرار یا کپی شود** — به همین دلیل، وقتی نسخه‌ی دوم API (v2) اضافه شد، این چهار مرحله در یک کلاس پایه‌ی مشترک (`AbstractMCPGatewayController`) استخراج شدند تا نسخه‌ی ۱ و ۲ هرگز در این مسیر امنیتی از هم واگرا نشوند (فایل ۱۰).

## بدنه‌ی یک درخواست

```json
POST /mcp/v1/execute
Authorization: Bearer <agent-token>
Content-Type: application/json

{
  "capability": "commerce.product.search",
  "input": { "query": "کفش" }
}
```

## شکل پاسخ (Envelope)

نسخه‌ی v1 (بدون تغییر از ابتدا):
```json
{ "data": { ... }, "meta": { ... } }
```

نسخه‌ی v2 (فرمت جدید‌تر):
```json
{ "result": { ... }, "metadata": { "api_version": "v2", "timestamp": "..." } }
```

**نکته‌ی مهم:** v1 و v2 **یک پلتفرم با دو لباس متفاوت‌اند، نه دو پلتفرم جدا.** همه‌ی قابلیت‌ها، مجوزها، کدهای خطا و روش احراز هویت بین دو نسخه دقیقاً یکسان‌اند؛ فقط شکل پاسخ فرق دارد.

## شکل پاسخ خطا

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Order not found: id=42",
    "localized_message": "سفارش پیدا نشد"
  }
}
```

- `message` → متن اصلی انگلیسی، دست‌نخورده
- `localized_message` → ترجمه‌شده بر اساس زبان تشخیص‌داده‌شده (فایل ۹)

کدهای رایج: `NOT_FOUND` (۴۰۴)، `CONFLICT` (۴۰۹)، `VALIDATION_ERROR` (۴۲۲)، `TOO_MANY_REQUESTS` (۴۲۹)، `INTERNAL_ERROR` (۵۰۰).

## کشف قابلیت‌ها (Capability Discovery)

```
GET /mcp/v1/capabilities
```

این مسیر لیست همه‌ی قابلیت‌های قابل‌دسترسی برای این Agent را با شرح، ورودی/خروجی مورد نیاز و مجوز لازم برمی‌گرداند — دقیقاً همان چیزی که یک ایجنت هوش مصنوعی برای «فهمیدن این‌که چه کاری می‌تواند بکند» نیاز دارد.

**نکته:** کشف فقط «مستندسازی» است. مجوزدهی واقعی همیشه، جدا و در لحظه‌ی اجرا (Execution Time) بررسی می‌شود — نمی‌شود با دیدن یک قابلیت در لیست Discovery، مطمئن بود که واقعاً اجازه‌ی اجرایش را دارید.

## قواعد نام‌گذاری قابلیت‌ها

```
domain.resource.action    (دقیقاً سه بخش، جدا شده با نقطه)
```

مثال‌های درست:
```
commerce.product.search
crm.ticket.create
agent.reasoning.explain
```

این محدودیت باعث شده در طول پروژه، بارها یک نام درخواستی (که ۲ یا ۴ بخش داشته) بازنویسی شود. مثلاً:

| نام درخواستی (رد شده) | نام نهایی |
|---|---|
| `crm.ticket.comment.add` (۴ بخش) | `crm.comment.create` |
| `workflow.create` (۲ بخش) | `workflow.definition.create` |
| `commerce.variant.attribute.create` (۴ بخش) | `commerce.attribute.create` |
| `commerce.subscription.plan.create` (۴ بخش) | `commerce.plan.create` |

این الگو آن‌قدر تکرار شده که به یک **قانون استاندارد** تبدیل شده: وقتی نام درخواستی ۴ بخش دارد، معمولاً یکی از میانی‌ها به‌عنوان «منبع (Resource)» مستقل ترفیع داده می‌شود.

## Handler ثبت‌شده در ServiceProvider

هر ماژول در `ServiceProvider` خودش، یک Handler برای هر قابلیت ثبت می‌کند:

```php
// در CommerceServiceProvider::boot()
$registry->register('commerce.product.search', function (array $input, AuthContext $context) {
    return app(SearchProductsAction::class)->execute($input, $context->tenantId);
});
```

**نکته‌ی خیلی مهم که بارها باعث گیج‌شدن شده:** ثبت **Handler** (اجرای واقعی) و ثبت **Description** (برای Discovery/Seeder) دو کار کاملاً جدا هستند. اگر یک قابلیت با پیام «هیچ Handler اجرایی پیدا نشد» خطا بدهد در حالی که مطمئنید در ServiceProvider نوشته‌اید، احتمالاً تست فراموش کرده Seeder مربوطه (`XxxCapabilitiesSeeder`) را اجرا کند.

## چرا منطق کسب‌وکار اینجا نیست؟

MCP Gateway فقط این کارها را انجام می‌دهد: احراز هویت، محدودسازی نرخ، مجوزدهی، اجرا، قالب‌بندی پاسخ. **هیچ تصمیم کسب‌وکاری** (مثل «آیا این تخفیف مجاز است؟») اینجا گرفته نمی‌شود — همه‌ی این‌ها داخل `Action` های ماژول مربوطه است. این تفکیک دقیقاً همان چیزی است که در فایل ۲ به‌عنوان قانون طلایی معرفی شد.

## HTTP معادل برای ماژول عامل‌های هوشمند

از فاز ۶ به بعد، یک مسیر HTTP اضافه (نه به‌جای MCP، بلکه علاوه بر آن) هم وجود دارد:

```
POST /api/agents/{agent_type}      ← اجرای یک هدف با یک پرسونا
GET  /api/agents/executions/{id}   ← نتیجه‌ی یک اجرای قبلی
```

نکته‌ی جالب: این دو مسیر از **همان Action هایی** استفاده می‌کنند که قابلیت‌های `agent.goal.execute` و `agent.execution.get` از طریق MCP صدا می‌زنند. این یعنی یک ماژول می‌تواند هم مستقیم از MCP و هم از HTTP اختصاصی خودش در دسترس باشد، بدون این‌که منطق دوبار نوشته شود — الگویی که در فایل ۱۷ به‌عنوان «الگوی شماره ۱۹» معرفی می‌شود.

## جمع‌بندی

- هر درخواست از چهار مرحله‌ی ثابت عبور می‌کند: احراز هویت → محدودیت نرخ → مجوز → اجرا.
- نام قابلیت همیشه سه‌بخشی است.
- Discovery فقط مستندسازی است؛ enforcement همیشه در لحظه‌ی اجرا رخ می‌دهد.
- هیچ منطق کسب‌وکاری داخل MCP Gateway نیست.
- v1 و v2 فقط در شکل پاسخ فرق دارند، نه در رفتار.

حالا که فهمیدیم درخواست‌ها چطور وارد سیستم می‌شوند، وقت آن است که ببینیم **اولین و بزرگ‌ترین ماژول دامنه** — یعنی Commerce — چه کاری انجام می‌دهد.

---
← [هسته Core](04-هسته-Core.md) | بعدی: [ماژول Commerce](06-ماژول-Commerce.md) →
