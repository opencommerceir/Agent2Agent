# NEXUS UI — دیزاین‌سیستم به سبک Jarvis

> **نسخه:** 1.0.0 · **تم:** Sci-Fi HUD / Glassmorphism  
> رابط کاربری الهام‌گرفته از Jarvis: زمینهٔ تیرهٔ عمیق، خطوط سایان درخشان، انرژی بنفش و پنل‌های شیشه‌ای.

---

## فهرست

1. [اصول طراحی](#1-اصول-طراحی)
2. [پالت رنگ](#2-پالت-رنگ)
3. [تایپوگرافی](#3-تایپوگرافی)
4. [فاصله‌گذاری و گرید](#4-فاصله‌گذاری-و-گرید)
5. [کامپوننت‌ها](#5-کامپوننت‌ها)
6. [انیمیشن‌ها](#6-انیمیشن‌ها)
7. [ریسپانسیو](#7-ریسپانسیو)
8. [دسترس‌پذیری](#8-دسترس‌پذیری)
9. [شروع سریع](#9-شروع-سریع)

---

## 1. اصول طراحی

- **تاریکی عمیق، نور دقیق** — پس‌زمینه تقریباً مشکیِ سرمه‌ای؛ نور فقط جایی که معنا دارد.
- **شیشه و عمق** — پنل‌ها با `backdrop-filter` و شفافیت لایه‌ای.
- **حرکتِ زنده** — همه‌چیز نفس می‌کشد: پالس، جریان داده، خطوط در حال ترسیم.
- **داده در مرکز** — اعداد و وضعیت‌ها با فونت مونو، مثل تله‌متری واقعی.

---

## 2. پالت رنگ

### توکن‌های اصلی

| توکن | مقدار | کاربرد |
|---|---|---|
| `--nexus-cyan` | `#00F0FF` | رنگ اصلی، فوکوس، خطوط فعال |
| `--nexus-purple` | `#A855F7` | رنگ دوم، انرژی ایجنت، گرادیان‌ها |
| `--nexus-dark` | `#0A0E27` | پس‌زمینهٔ پایه |
| `--nexus-glass` | `rgba(255,255,255,0.05)` | سطح پنل‌ها |
| `--nexus-text` | `#E2E8F0` | متن اصلی |

### توکن‌های تکمیلی

| توکن | مقدار | کاربرد |
|---|---|---|
| `--nexus-surface-1` | `#0D1330` | سطح یک (کارت‌ها) |
| `--nexus-surface-2` | `#131B42` | سطح دو (مودال، پالت) |
| `--nexus-text-muted` | `#94A3B8` | متن کم‌رنگ |
| `--nexus-border` | `rgba(0,240,255,0.15)` | حاشیهٔ پیش‌فرض |
| `--nexus-success` | `#34D399` | وضعیت موفق |
| `--nexus-warning` | `#FBBF24` | هشدار |
| `--nexus-error` | `#F87171` | خطا |

### گرادیان‌ها

```css
--nexus-gradient: linear-gradient(135deg, var(--nexus-cyan), var(--nexus-purple));
--nexus-gradient-soft: linear-gradient(180deg, rgba(0,240,255,.08), transparent);
```

---

## 3. تایپوگرافی

| نقش | فونت | توضیح |
|---|---|---|
| نمایشی / هدینگ | **Space Grotesk** | حس تکنیکال و مدرن |
| داده / کد | **JetBrains Mono** | اعداد، تله‌متری، لاگ‌ها |

### مقیاس

| توکن | سایز | وزن | کاربرد |
|---|---|---|---|
| `--text-display` | `3.5rem` | 700 | عنوان صفحه |
| `--text-h1` | `2.25rem` | 600 | هدینگ بخش |
| `--text-h2` | `1.5rem` | 600 | عنوان کارت |
| `--text-body` | `1rem` | 400 | متن اصلی |
| `--text-mono` | `0.875rem` | 400 | داده و وضعیت |

---

## 4. فاصله‌گذاری و گرید

مبنای اسپیسینگ: `4px`

| توکن | مقدار | | توکن | مقدار |
|---|---|---|---|---|
| `--space-1` | `4px` | | `--space-6` | `24px` |
| `--space-2` | `8px` | | `--space-8` | `32px` |
| `--space-3` | `12px` | | `--space-12` | `48px` |
| `--space-4` | `16px` | | `--space-16` | `64px` |

شعاع‌ها: `--radius-sm: 8px` · `--radius-md: 14px` · `--radius-lg: 20px`

---

## 5. کامپوننت‌ها

### 5.1 `x-nexus-panel` — پنل شیشه‌ای

```html
<x-nexus-panel class="p-6" glow="cyan" corner="cut">
  Content here
</x-nexus-panel>
```

| Prop | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `glow` | `cyan \| purple \| none` | `cyan` | رنگ هالهٔ حاشیه |
| `corner` | `round \| cut` | `round` | گوشهٔ گرد یا برش‌خورده (HUD) |
| `interactive` | `boolean` | `false` | افکت hover و focus |

```css
x-nexus-panel {
  background: var(--nexus-glass);
  backdrop-filter: blur(16px);
  border: 1px solid var(--nexus-border);
  border-radius: var(--radius-md);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.06);
}
x-nexus-panel[glow="cyan"] { border-color: rgba(0,240,255,.35); }
x-nexus-panel[corner="cut"] {
  clip-path: polygon(0 0, calc(100% - 18px) 0, 100% 18px, 100% 100%, 0 100%);
  border-radius: 0;
}
```

---

### 5.2 `x-agent-pulse` — ضربان ایجنت (حالت تنفس)

```html
<x-agent-pulse status="thinking" label="در حال پردازش..." />
```

| وضعیت | رنگ | حرکت |
|---|---|---|
| `idle` | `--nexus-text-muted` | تنفس خیلی آرام (4s) |
| `thinking` | `--nexus-purple` | تنفس متوسط (2s) |
| `active` | `--nexus-cyan` | `pulse-glow` سریع (1.2s) |
| `warning` | `--nexus-warning` | پالس دوتایی |
| `error` | `--nexus-error` | چشمک سریع (0.6s) |

```css
x-agent-pulse::before {
  content: "";
  width: 12px; height: 12px;
  border-radius: 50%;
  background: currentColor;
  animation: breathe 2s ease-in-out infinite;
}
```

---

### 5.3 `x-negotiation-line` — خط مذاکره (انیمیشن SVG)

نمایش جریان پیام‌ها بین ایجنت‌ها به‌صورت مسیر در حال ترسیم.

```html
<x-negotiation-line :messages="$messages" :duration="1200" animated />
```

| Prop | نوع | توضیح |
|---|---|---|
| `messages` | `Array<{id, from, text, status}>` | لیست پیام‌ها |
| `duration` | `number` (ms) | سرعت ترسیم مسیر |
| `animated` | `boolean` | فعال‌سازی `stroke-dashoffset` |

```css
x-negotiation-line path {
  stroke: var(--nexus-cyan);
  stroke-width: 2;
  fill: none;
  stroke-dasharray: 1000;
  stroke-dashoffset: 1000;
  animation: draw-line var(--duration, 1200ms) ease-out forwards;
  filter: drop-shadow(0 0 4px var(--nexus-cyan));
}
```

---

### 5.4 کامپوننت‌های پشتیبان

| کامپوننت | توضیح |
|---|---|
| `x-command-palette` | پالت دستورات دسکتاپ — `Ctrl/Cmd + K` |
| `x-status-badge` | برچسب وضعیت با نقطهٔ پالس |
| `x-data-stream` | نوار جریان داده با انیمیشن `data-stream` |
| `x-metric-card` | کارت عدد HUD با فونت مونو و واحد |

---

## 6. انیمیشن‌ها

### کی‌فریم‌ها

```css
@keyframes pulse-glow {
  0%, 100% { box-shadow: 0 0 5px var(--nexus-cyan); }
  50%      { box-shadow: 0 0 20px var(--nexus-cyan); }
}

@keyframes data-stream {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}

@keyframes breathe {
  0%, 100% { transform: scale(1);   opacity: .6; }
  50%      { transform: scale(1.3); opacity: 1;  }
}

@keyframes draw-line {
  to { stroke-dashoffset: 0; }
}
```

### توکن‌های حرکت

| توکن | مقدار | کاربرد |
|---|---|---|
| `--ease-glow` | `cubic-bezier(.4, 0, .2, 1)` | ترنزیشن عمومی |
| `--dur-fast` | `150ms` | هاور، فوکوس |
| `--dur-base` | `300ms` | ورود کارت‌ها |
| `--dur-slow` | `1200ms` | ترسیم خطوط |

> ⚠️ با `prefers-reduced-motion: reduce` همهٔ انیمیشن‌ها باید غیرفعال یا به fade ساده تبدیل شوند.

---

## 7. ریسپانسیو

| شکست | عرض | چیدمان | ناوبری | ویژگی اختصاصی |
|---|---|---|---|---|
| 📱 موبایل | `< 768px` | تک‌ستونه | Bottom Nav | — |
| 💊 تبلت | `768–1279px` | دوستونه | سایدبار جمع‌شونده | Swipe برای باز/بسته شدن |
| 🖥 دسکتاپ | `≥ 1280px` | سه‌ستونه | سایدبار ثابت + تاپ‌بار | Command Palette با `⌘K` |

```
Mobile            Tablet                 Desktop
┌────────┐        ┌───┬──────────┐       ┌────┬──────────┬────┐
│ Header │        │ S │          │       │    │          │    │
├────────┤        │ i │  2 cols  │       │ S  │  3 cols  │ P  │
│        │        │ d │          │       │ i  │          │ a  │
│ 1 col  │        │ e │          │       │ d  │          │ n  │
│        │        │ b │          │       │ e  │          │ e  │
├────────┤        │ a │          │       │ b  │          │ l  │
│ Nav ▼  │        │ r │          │       │ a  │          │    │
└────────┘        └───┴──────────┘       └────┴──────────┴────┘
```

```css
.layout { display: grid; grid-template-columns: 1fr; gap: var(--space-4); }
@media (min-width: 768px)  { .layout { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1280px) { .layout { grid-template-columns: repeat(3, 1fr); } }
```

---

## 8. دسترس‌پذیری

- نسبت کنتراست `--nexus-text` روی `--nexus-dark`: **~14:1** ✅
- سایانِ متن روی پس‌زمینه تیره استفاده **نشود** (فقط برای خطوط و آیکون).
- وضعیت‌ها فقط با رنگ بیان نشوند — همیشه `label` یا آیکون همراه باشد.
- فوکوس کیبورد: `outline: 2px solid var(--nexus-cyan)` با `outline-offset: 2px`.
- احترام کامل به `prefers-reduced-motion`.

---

## 9. شروع سریع

```css
:root {
  --nexus-cyan: #00F0FF;
  --nexus-purple: #A855F7;
  --nexus-dark: #0A0E27;
  --nexus-glass: rgba(255,255,255,0.05);
  --nexus-text: #E2E8F0;

  --nexus-surface-1: #0D1330;
  --nexus-surface-2: #131B42;
  --nexus-text-muted: #94A3B8;
  --nexus-border: rgba(0,240,255,0.15);
  --nexus-success: #34D399;
  --nexus-warning: #FBBF24;
  --nexus-error: #F87171;

  --nexus-gradient: linear-gradient(135deg, var(--nexus-cyan), var(--nexus-purple));
}

body {
  background: var(--nexus-dark);
  color: var(--nexus-text);
  font-family: "Space Grotesk", sans-serif;
}
code, .mono { font-family: "JetBrains Mono", monospace; }
```

---

**NEXUS UI v1.0.0** — ساخته‌شده برای سیستم‌های ایجنت‌محور. ⚡