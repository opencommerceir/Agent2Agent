← [Online Payments & Fintech](11-online-payments-and-fintech.md) | Back to: [Pre-Tutorial Table of Contents](00-table-of-contents.md)

# 12. Professional Engineering & Business Concepts

The last chapter of this pre-tutorial bridges "engineering" and "actual business" — terms used mostly in main series files 19 through 22 (technical debt, integration paths, SDKs, monetization models).

## SaaS (Software as a Service)

**Simple definition:** instead of a customer buying software and installing it on their own server, they use an already-hosted version (maintained by someone else) on a subscription basis.

📍 **In this project:** "Path B," explained in the main series (file 20) — connecting to a hosted instance instead of self-hosting — is exactly the SaaS model, and multi-tenancy (chapter 7 of this pre-tutorial) is its technical prerequisite.

## White-Label

**Simple definition:** selling a product under your own brand name (not the original creator's) — the customer never knows the product was actually built from someone else's ready-made software.

📍 **In this project:** because this project is open-source under the MIT license (below), any agency can host it under its own brand and sell it to several businesses — exactly one of the monetization models in the main series, file 22.

## MVP (Minimum Viable Product)

**Simple definition:** the smallest version of a product that's still genuinely useful and usable — without every "nice-to-have" feature that could be added later.

📍 **In this project:** `DeterministicPlanner` literally describes itself as the "MVP" of a smart planner — fixed, simple rules, built **before** reaching the LLM-based version; built specifically to be replaced later, not to be the final answer.

## Technical Debt

**Simple definition:** any deliberate decision to pick a simpler (not necessarily the most complete) solution in order to move faster, with the awareness that someday you'll have to come back and complete it properly. Like borrowing money — it works right now, but a debt has been recorded that must be paid one day.

**Why it matters:** *documented* technical debt isn't dangerous — what's dangerous is *hidden* technical debt that no one even knows exists.

📍 **In this project:** `HANDOFF.md` records over 100 technical debt items, each with a number, a reason, and a suggested fix — a deliberate cultural decision, not a weakness.

## Roadmap

**Simple definition:** a high-level document showing what's been built, what's currently being built, and what's still just an idea — without deep technical detail.

📍 **In this project:** `docs/roadmap.md` plays exactly this role; `HANDOFF.md` is the extremely detailed, technical version of that same story.

## Semantic Versioning (SemVer)

**Simple definition:** a convention for numbering software versions as `X.Y.Z` — `X` (major) changes when something "breaks" (old code stops working), `Y` (minor) changes when a new feature is added without breaking anything, `Z` (patch) is for small bug fixes.

📍 **In this project:** all five of this platform's SDKs (`opencommerce/sdk`, ...) are versioned under exactly this convention — meaning an external developer can tell, just by looking at the version number, whether updating the SDK is safe or not.

## CI/CD (Continuous Integration / Continuous Delivery)

**Simple definition:** an automated system that, with every code change (e.g. every push to GitHub), immediately runs every test and reports the result — without a human having to do it manually.

**Why it matters:** it means a mistake is caught the moment it enters the code, not weeks later when everyone's already forgotten what changed.

📍 **In this project:** `.github/workflows/tests.yml` is exactly this system — every time code is pushed to GitHub, all 1,156 tests run automatically.

## Open Source and the MIT License

**Simple definition:** "open source" means a piece of software's source code is public and available to everyone. A "license" defines the exact rules for using that code — e.g. whether commercial use is allowed, whether the original author's name must be kept. The **MIT** license is one of the most permissive common open-source licenses — it allows almost any use (including commercial) with very few restrictions.

📍 **In this project:** this project is published under the MIT license — exactly the foundation of "anyone can take this and build a business on top of it," covered in the main series (files 20 and 22).

## SDK (Software Development Kit)

**Simple definition:** a ready-made package that helps a developer work faster with a specific service/platform — instead of writing an HTTP client from scratch themselves, they install one library and use a ready-made class/function.

📍 **In this project:** the five official SDKs (PHP, Laravel, Python, Node.js/TypeScript, Go — main series file 21) do exactly this: instead of a developer needing to understand the exact JSON shape of the MCP protocol, they just call `client.execute('commerce.product.search', {...})`.

## Package Registry

**Simple definition:** a central store/repository where software packages are published so anyone can install them with a simple command (`composer require`, `npm install`, `pip install`, `go get`) — without manually downloading the code themselves.

📍 **In this project:** Packagist (for PHP), npm (for Node.js/TypeScript), PyPI (for Python), and `proxy.golang.org` (for Go) are each their own language's registry — and interestingly, each one has different rules (e.g. a Go package needs no formal "publish" step at all, just a real Git tag on the repository).

## Documentation as Part of Engineering

**Simple definition:** writing down *why* a decision was made — not just *what* was done — in a way that lets someone else (or you, six months later) understand the reasoning behind every decision without having to guess.

**Why it matters:** code only ever says "what it does"; only good documentation can say "why this way, and not some other way."

📍 **In this project:** `HANDOFF.md` is exactly this — not just a feature list, but every architectural correction, every rejected alternative, and the reasoning behind each one; this very tutorial series you're reading follows the same philosophy: not just defining every concept, but showing **exactly where in this real project** it's actually used.

---

That's it! You're now ready to start the main tutorial series — every one of these terms will carry, from here on, a live example you just saw, instead of an abstract definition.

Let's go to the main series 👉 [File 01: Introduction and Vision](../01-introduction-and-vision.md)

---

← [Online Payments & Fintech](11-online-payments-and-fintech.md) | Back to: [Pre-Tutorial Table of Contents](00-table-of-contents.md)
