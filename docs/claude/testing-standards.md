# Testing Standards

## Coverage Requirements

- Unit tests: 100% of Domain Services
- Feature tests: 100% of API endpoints
- E2E tests: All critical flows
- Overall: minimum 80%

## Test Structure

```php
// Unit Test
public function test_agent_can_propose_deal()
{
    $agent = Agent::factory()->create();
    $result = $agent->propose($terms);
    $this->assertInstanceOf(Proposal::class, $result);
}

// Feature Test
public function test_business_can_register()
{
    $response = $this->post('/api/businesses', $data);
    $response->assertStatus(201);
}

// E2E Test
public function test_full_negotiation_flow()
{
    // Discovery → Proposal → Counter → Contract
}

Required Test Types
Unit tests for all Value Objects
Feature tests for all Actions
Integration tests for MCP capabilities
Load tests for 1000 concurrent negotiations


---

## 💰 مقایسه مصرف توکن

| روش | توکن هر تعامل | هزینه ماهانه (۱۰۰ تعامل/روز) |
|------|----------------|-------------------------------|
| **فایل بزرگ (قدیمی)** | ~۳۰۰۰ توکن | ~۹ میلیون توکن |
| **فایل سلسله‌مراتبی (جدید)** | ~۵۰۰ توکن | ~۱.۵ میلیون توکن |
| **صرفه‌جویی** | **۸۳٪ کمتر** | **۷.۵ میلیون توکن کمتر** |

---

## 🎯 نحوه استفاده

### ۱. شروع کار
به Claude بگویید:
> "Read CLAUDE.md and start Phase 0, Milestone 0.1"

### ۲. وقتی درباره LLM صحبت می‌شود
Claude خودش می‌گوید:
> "Let me read the LLM strategy details from `docs/claude/llm-strategy.md`"

### ۳. وقتی درباره UI صحبت می‌شود
Claude خودش می‌گوید:
> "Let me check the UI design system in `docs/claude/ui-design-system.md`"

---

## ✅ مزایای این روش

| مزیت | توضیح |
|------|-------|
| **۸۳٪ صرفه‌جویی توکن** | فقط اطلاعات مرتبط لود می‌شود |
| **سرعت بیشتر** | Claude سریع‌تر پاسخ می‌دهد |
| **نگهداری آسان** | هر بخش مستقل است |
| **مقیاس‌پذیر** | می‌توانید فایل‌های جدید اضافه کنید |
| **هوشمند** | Claude فقط وقتی لازم است فایل‌ها را می‌خواند |

---
