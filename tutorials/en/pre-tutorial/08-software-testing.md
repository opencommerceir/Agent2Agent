← [Security, Auth & Multi-Tenancy](07-security-auth-and-multi-tenancy.md) | Next: [AI, LLMs & AI Agents](09-ai-llms-and-ai-agents.md) →

# 8. Software Testing

This project has 1,156 tests. This chapter explains what that actually means, and why it's not just a decorative statistic.

## What is an Automated Test?

**Simple definition:** instead of a human manually checking, every time, "does this feature still work correctly?", you write a separate piece of code that runs the real code itself and compares the result against what's expected — and it does this in seconds, thousands of times, without ever getting tired.

**Why it matters:** without automated tests, every small code change can silently break some other feature without anyone noticing — this is called a "regression," explained further below.

📍 **In this project:** `HANDOFF.md` repeatedly says a given change was "confirmed by actually running the full test suite" — no claim about code correctness is accepted without actually running the tests.

## Assertion

**Simple definition:** a line inside a test that says "I expect this value to be exactly this" — if it isn't, the test fails.

📍 **In this project:** e.g. `$this->assertEquals(45, $result->successRows)` means "I expect exactly 45 rows to have been imported successfully."

## Unit Test

**Simple definition:** the smallest and fastest kind of test — it tests **one** class or function in complete isolation from the rest of the system (no real database, no framework booted).

📍 **In this project:** because every module's `Domain` layer is completely framework-independent (chapter 3), classes like `PricingService` or `WorkflowEvaluator` can be tested with pure unit tests, with no Laravel booted at all — faster and simpler than any other test type.

## Feature Test / Integration Test

**Simple definition:** unlike a unit test, this kind tests several parts **together**, through a real path (e.g. an actual HTTP request to a real endpoint) — with a real (usually temporary) database.

**Why it matters:** a unit test can't guarantee that every part works correctly **together** — only an integration test can show that.

📍 **In this project:** tests like `ProductVariantCapabilityTest` test one complete, real business scenario from start to finish, through the real MCP Gateway, with a real database.

## Mocks, Stubs, and Test Doubles

**Simple definition:** when your code depends on something "external" and slow/unreliable (like a real payment service, or the internet), in a test you substitute a fake, controllable version in place of the real thing — so the test stays fast, repeatable, and doesn't need real internet access.

**Why it matters:** without this technique, tests either become too slow, actually move real money (!), or fail whenever the internet is down — none of which is acceptable.

📍 **In this project:** `MockPaymentGateway` is exactly this — a fake payment gateway that always succeeds unless you explicitly tell it to fail. Even for the real gateways (Zibal/Stripe), tests inject a `MockHandler` on top of the HTTP library (Guzzle) — meaning even testing the *real* gateways never actually touches the internet.

## TDD (Test-Driven Development)

**Simple definition:** a way of working where you write the test **first** (which naturally fails, since the code doesn't exist yet), then write only just enough code to make that test pass.

📍 **In this project:** the discount calculation engine (`DiscountRuleEvaluator`) was built exactly this way — first tested against the request's own real, complex worked example ("three discount rules with different priorities"), then the Actions that call it were built on top (main series, file 11).

## Regression Test

**Simple definition:** a test written specifically to make sure a specific bug — one that was found and fixed once — **never comes back**.

**Why it matters:** finding a subtle bug is usually time-consuming; once found, you don't want to spend that same time finding it again.

📍 **In this project:** after the inventory-reservation concurrency issue was found (chapter 2 of this pre-tutorial), `InventoryConcurrencyTest` was written exactly so that if that bug ever returns, it's caught immediately.

## Code Coverage

**Simple definition:** the percentage of code lines that were run at least once by the test suite — not necessarily "correctly tested," just "executed."

**Why it matters:** a low number means a large chunk of the code has never been checked by any test at all — but a high number alone isn't a guarantee of test "quality" either, only of "coverage."

📍 **In this project:** an interesting, honest note: this dev environment doesn't have the tools needed to actually measure this number (neither PCOV nor Xdebug is installed) — only CI (chapter 12) can produce the real number; this limitation is explicitly recorded in `HANDOFF.md` rather than hidden.

---

← [Security, Auth & Multi-Tenancy](07-security-auth-and-multi-tenancy.md) | Next: [AI, LLMs & AI Agents](09-ai-llms-and-ai-agents.md) →
