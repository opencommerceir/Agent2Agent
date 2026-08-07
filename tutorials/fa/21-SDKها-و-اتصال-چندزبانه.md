← [راه‌های اتصال و استفاده‌ی دیگران](20-راه‌های-اتصال-و-استفاده-دیگران.md) | بعدی: [کاربردهای سودآور و مدل‌های درآمدزایی](22-کاربردهای-سودآور-و-مدل‌های-درآمدزایی.md) →

# ۲۱. SDKها و اتصال از زبان‌های برنامه‌نویسی مختلف

فایل قبل گفت که دو راه برای استفاده از این پلتفرم وجود دارد (میزبانی شخصی یا اتصال به زیرساخت میزبانی‌شده). این فایل وارد جزئیات عملی «اتصال» می‌شود: اگر پروژه‌ی شما با PHP/Laravel، Python، Go یا Node.js/TypeScript نوشته شده، دقیقاً چطور به OpenCommerce وصل می‌شوید؟

## پنج SDK رسمی موجود

| زبان | نام پکیج | مسیر در مخزن | وابستگی زمان اجرا |
|---|---|---|---|
| PHP (فریم‌ورک‌مستقل) | `opencommerce/sdk` (Composer) | `packages/opencommerce-sdk` | Guzzle |
| PHP (مخصوص Laravel) | `opencommerce/sdk-laravel` (Composer) | `packages/opencommerce-sdk-laravel` | همان SDK پی‌اچ‌پی بالا + `illuminate/support` |
| Python | `opencommerce-sdk` (PyPI) | `packages/opencommerce-sdk-python` | **هیچ‌کدام** (فقط کتابخانه‌ی استاندارد) |
| Node.js / TypeScript | `@opencommerce/sdk` (npm) | `packages/opencommerce-sdk-js` | **هیچ‌کدام** (فقط `fetch` استاندارد) |
| Go | `github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go` | `packages/opencommerce-sdk-go` | **هیچ‌کدام** (فقط `net/http`) |

نکته‌ی مهم: SDKهای Python، Node.js/TypeScript و Go عمداً **بدون هیچ وابستگی خارجی** ساخته شده‌اند — فقط از کتابخانه‌ی استاندارد همان زبان استفاده می‌کنند. این یعنی نصب‌کردن‌شان هیچ‌وقت یک کتابخانه‌ی HTTP نسخه‌دار را به پروژه‌ی شما تحمیل نمی‌کند. (SDK پی‌اچ‌پیِ اصلی تنها استثناست، چون خودِ PHP هیچ کلاینت HTTP استانداردی در کتابخانه‌ی پایه‌اش ندارد؛ SDK مخصوص Laravel هم طبیعتاً روی همان و روی خودِ فریم‌ورک Laravel تکیه می‌کند، چون دقیقاً برای همان محیط ساخته شده.)

هر پنج SDK دقیقاً همان قرارداد را دنبال می‌کنند: یک شیء تنظیمات (Config)، یک کلاینت با سه عملیات (`discoverCapabilities`/`execute`/`getCapability`)، و یک سلسله‌مراتب خطای معادل با کدهای HTTP سرور.

## Python

```bash
pip install opencommerce-sdk
```

```python
from opencommerce_sdk import MCPClient, MCPConfig

config = MCPConfig(base_url="http://localhost:8000/mcp/v1", token="agent_token")
client = MCPClient(config)

capabilities = client.discover_capabilities()
result = client.execute("commerce.product.search", {"query": "laptop"})
print(result.data)
```

مدیریت خطا با استثناهای معمولی پایتون:

```python
from opencommerce_sdk.exceptions import MCPException, ValidationException

try:
    client.execute("commerce.order.place", {})
except ValidationException as exc:
    print(f"ورودی نامعتبر: {exc}")
except MCPException as exc:
    print(f"خطا ({exc.error_code}): {exc}")
```

جزئیات کامل: `packages/opencommerce-sdk-python/README.md`

## Node.js / TypeScript

```bash
npm install @opencommerce/sdk
```

```ts
import { MCPClient, MCPConfig } from "@opencommerce/sdk";

const config = new MCPConfig({ baseUrl: "http://localhost:8000/mcp/v1", token: "agent_token" });
const client = new MCPClient(config);

const capabilities = await client.discoverCapabilities();
const result = await client.execute("commerce.product.search", { query: "laptop" });
console.log(result.data);
```

این پکیج کاملاً از جاوااسکریپت خالص هم قابل‌استفاده است — TypeScript فقط برای امنیت نوع (Type Safety) است، هیچ الزامی برای نوشتن پروژه‌ی مصرف‌کننده به TypeScript وجود ندارد.

جزئیات کامل: `packages/opencommerce-sdk-js/README.md`

## Go

```bash
go get github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go
```

```go
import opencommerce "github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go"

config := opencommerce.NewConfig("http://localhost:8000/mcp/v1", "agent_token")
client := opencommerce.NewClient(config)
ctx := context.Background()

capabilities, err := client.DiscoverCapabilities(ctx)
result, err := client.Execute(ctx, "commerce.product.search", map[string]interface{}{"query": "laptop"})
fmt.Println(result.Data)
```

`go.mod` این SDK مسیر واقعیِ خودِ همین مخزن Monorepo را اعلام می‌کند (`github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go`) — نه یک مسیر موقت محلی. نسخه‌گذاری از طریق تگ‌های Git به شکل `packages/opencommerce-sdk-go/vX.Y.Z` انجام می‌شود؛ به‌محض این‌که چنین تگی روی این مخزن پوش شود، دستور `go get` بالا از طریق `proxy.golang.org` برای هرکسی کار می‌کند، بدون نیاز به هیچ اکانت یا انتشار جداگانه.

**یک تفاوت آگاهانه نسبت به سایر SDKها**: هر متد Go، یک `context.Context` به‌عنوان اولین پارامتر می‌گیرد — این دقیقاً همان الگوی استاندارد خود زبان Go برای مدیریت Timeout و لغو عملیات است، نه یک ناهماهنگی. مدیریت خطا هم به‌جای Exception، از `error` استاندارد Go استفاده می‌کند (`errors.As` برای تشخیص نوع دقیق خطا).

جزئیات کامل: `packages/opencommerce-sdk-go/README.md`

## PHP (فریم‌ورک‌مستقل)

SDK اصلی و اولین SDK این پلتفرم — همان چیزی که هر سه SDK بالا (Python، Node.js/TypeScript، Go) دقیقاً از روی آن الگوبرداری شده‌اند (فایل ۳ و ۱۸). فریم‌ورک‌مستقل است — به هیچ‌وجه نیازی به Laravel ندارد و در هر اسکریپت خام PHP هم کار می‌کند.

```bash
composer require opencommerce/sdk
```

```php
use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\MCPClient;

$config = new MCPConfig(baseUrl: 'http://localhost:8000/mcp/v1', token: 'agent_token');
$client = new MCPClient($config);

$capabilities = $client->discoverCapabilities();
$result = $client->execute('commerce.product.search', ['query' => 'laptop']);
print_r($result->getData());
```

مدیریت خطا با Exception های معمولی PHP:

```php
use OpenCommerce\SDK\Exceptions\{MCPException, ValidationException};

try {
    $client->execute('commerce.order.place', []);
} catch (ValidationException $e) {
    echo "ورودی نامعتبر: {$e->getMessage()}\n";
} catch (MCPException $e) {
    echo "خطا ({$e->errorCode}): {$e->getMessage()}\n";
}
```

تنها SDK پایه‌ای که به یک وابستگی خارجی (Guzzle) نیاز دارد — چون خودِ PHP، برخلاف Python/Node.js/Go، هیچ کلاینت HTTP استانداردی در کتابخانه‌ی پایه‌اش ندارد؛ سه SDK بدون‌وابستگی بالا همین دلیل را برای انتخاب کتابخانه‌ی استاندارد خودشان دارند.

جزئیات کامل + اسنیپت Tinker برای ساخت یک توکن Agent واقعی (همان چیزی که فایل ۱۸ همین آموزش هم به آن ارجاع می‌دهد): `packages/opencommerce-sdk/README.md`

## PHP (مخصوص Laravel)

اگر پروژه‌ی مصرف‌کننده خودش یک اپلیکیشن Laravel است، به‌جای ساختن `MCPConfig`/`MCPClient` با دست در هر جایی که لازم دارید، بسته‌ی جداگانه‌ی `opencommerce/sdk-laravel` این کار را یک‌بار برایتان انجام می‌دهد — یک ServiceProvider که یک نمونه‌ی singleton از همان `MCPClient` بالا را از روی `config/opencommerce.php` می‌سازد، به‌علاوه یک Facade به نام `OpenCommerce`. هیچ منطق HTTP جدیدی اضافه نمی‌کند — فقط یک لایه‌ی نازک روی همان SDK پی‌اچ‌پی فریم‌ورک‌مستقل بالاست.

```bash
composer require opencommerce/sdk-laravel
php artisan vendor:publish --tag=opencommerce-config
```

```env
OPENCOMMERCE_HOST=https://api.opencommerce.ir
OPENCOMMERCE_VERSION=v1
OPENCOMMERCE_TOKEN=your-agent-token
```

```php
use OpenCommerce\SDK\Laravel\Facades\OpenCommerce;

$capabilities = OpenCommerce::discoverCapabilities();
$result = OpenCommerce::execute('commerce.product.search', ['query' => 'laptop']);
```

یا با تزریق وابستگی معمولی Laravel:

```php
use OpenCommerce\SDK\MCPClient;

public function __invoke(MCPClient $client)
{
    return $client->execute('commerce.product.search', ['query' => request('q')]);
}
```

مدیریت خطا دقیقاً همان چیزی است که SDK پی‌اچ‌پی بالا دارد — این بسته چیزی به آن اضافه نمی‌کند، فقط ساختنش را ساده‌تر می‌کند.

جزئیات کامل: `packages/opencommerce-sdk-laravel/README.md`

## اگر زبان شما SDK رسمی ندارد چه کنیم؟

هیچ مشکلی نیست — دروازه‌ی MCP (فایل ۵) چیزی بیشتر از یک API استاندارد **HTTP + JSON** نیست. هر زبانی (Rust، Java، Ruby، C#، هر چیزی) می‌تواند مستقیماً با کتابخانه‌ی HTTP خودش وصل شود:

```
POST {base_url}/execute
Authorization: Bearer <agent-token>
Content-Type: application/json

{"capability": "commerce.product.search", "input": {"query": "laptop"}}
```

و پاسخ را طبق فرمت استاندارد (فایل ۵) بخوانید: `data`/`meta` برای v1، یا `result`/`metadata` برای v2. نوشتن یک SDK کوچک شخصی برای زبان خودتان — دقیقاً با الگوی همین SDKها (یک Config، یک Client، یک لایه‌ی Transport قابل‌تزریق برای تست) — کاری در حد چند ساعت است، نه چند هفته.

## جدول تصمیم: کدام روش اتصال مناسب من است؟

| موقعیت | راه‌حل پیشنهادی |
|---|---|
| پروژه‌ام یک اپلیکیشن Laravel است | `opencommerce/sdk-laravel` — سریع‌ترین و تمیزترین راه، Facade + تزریق وابستگی |
| پروژه‌ام به PHP (بدون Laravel)/Python/Node.js/TypeScript/Go است | همان SDK رسمی زبان خودم را نصب می‌کنم |
| زبانم SDK رسمی ندارد | مستقیم HTTP+JSON می‌زنم؛ در صورت نیاز، خودم یک کلاینت نازک می‌سازم |
| می‌خواهم یک قابلیت مشخص و از‌قبل‌شناخته‌شده را صدا بزنم (مثلاً «این سفارش را ثبت کن») | سطح ۱: تماس مستقیم با قابلیت (`execute`) |
| می‌خواهم فقط یک هدف متنی بدهم و بگذارم پلتفرم خودش تصمیم بگیرد چه کاری انجام دهد | سطح ۲: مسیر هدف‌محور Agent Orchestrator (فایل ۱۲ تا ۱۵) |

## اسکریپت‌های نمونه‌ی اجرایی

برای هر چهار زبان پایه (PHP، Python، Node.js/TypeScript، Go)، یک اسکریپت کامل و اجراشدنی در پوشه‌ی `examples/` پروژه وجود دارد — همه دقیقاً همان چهار قابلیت دموی یکسان را صدا می‌زنند (`demo.tools.echo`، `demo.tools.time`، `demo.tools.calculator`) و در پایان یک تست منفی عمدی (صدازدن یک قابلیت ناموجود، برای دیدن خطای ۴۰۴ واقعی) هم دارند — تا بتوانید رفتار یکسان را در هر زبان با چشم خودتان مقایسه کنید. SDK مخصوص Laravel اسکریپت نمونه‌ی جداگانه‌ای ندارد، چون دقیقاً همان کلاینت PHP بالا را فقط برای محیط Laravel آسان‌تر می‌کند — نمونه‌های استفاده‌اش در `packages/opencommerce-sdk-laravel/README.md` آمده‌اند:

```
examples/sample-agent.php
examples/sample-agent.py
examples/sample-agent.ts
examples/sample-agent.go
```

نحوه‌ی اجرا (بعد از این‌که طبق فایل ۱۸ سرور را بالا آوردید و یک توکن Agent گرفتید):

```bash
php examples/sample-agent.php <token>
python examples/sample-agent.py <token>
node examples/sample-agent.ts <token>
cd examples && go run sample-agent.go <token>
```

## جمع‌بندی

- پنج SDK رسمی موجود است: PHP، PHP مخصوص Laravel، Python، Node.js/TypeScript، Go — همه یک قرارداد مشترک دارند.
- سه‌تا از آن‌ها (Python، Node.js/TypeScript، Go) عمداً بدون وابستگی خارجی ساخته شده‌اند.
- SDK مخصوص Laravel یک لایه‌ی نازک راحتی روی SDK پی‌اچ‌پی است، نه یک کلاینت مستقل جدید.
- اگر SDK رسمی برای زبان شما نیست، مشکلی نیست — MCP فقط HTTP+JSON استاندارد است.
- بسته به نیاز، یا مستقیم یک قابلیت را صدا بزنید (سطح ۱) یا از موتور هدف‌محور عامل‌های هوشمند استفاده کنید (سطح ۲).

---

یک فایل دیگر مانده: فایل بعدی به یک سؤال کاملاً کاربردی و متفاوت جواب می‌دهد — اگر بخواهید از این پروژه واقعاً درآمد کسب کنید، دقیقاً از کجا شروع می‌کنید؟ برای مطالعه‌ی دقیق‌تر و فنی‌ترِ همه‌ی جزئیات معماری، `HANDOFF.md` در ریشه‌ی پروژه همیشه مرجع نهایی است.

نسخه‌ی انگلیسی همین آموزش در پوشه‌ی `tutorials/en/` موجود است.

← [راه‌های اتصال و استفاده‌ی دیگران](20-راه‌های-اتصال-و-استفاده-دیگران.md) | بعدی: [کاربردهای سودآور و مدل‌های درآمدزایی](22-کاربردهای-سودآور-و-مدل‌های-درآمدزایی.md) →
