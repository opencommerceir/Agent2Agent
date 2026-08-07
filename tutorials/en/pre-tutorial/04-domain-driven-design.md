← [Software Architecture & Design Principles](03-software-architecture-and-design-principles.md) | Next: [Common Design Patterns](05-common-design-patterns.md) →

# 4. Domain-Driven Design (DDD)

If one single concept has shaped the shape of this project's code more than any other, it's this one. DDD is a way of thinking, not just a set of classes — and understanding it is the key to understanding why this project's code looks the way it does.

## What is a "Domain"?

**Simple definition:** the "domain" is the real-world problem the software is meant to solve — not the code itself. A store's domain includes concepts like "product," "order," "inventory"; these concepts existed before a single line of code was ever written.

**Why DDD matters:** DDD's core idea is: code should use the exact same language and concepts a business expert (not a programmer) uses — not some strange technical translation of it. If a salesperson says "finalize this order," the code should have a method with exactly that name, not something like `processRow2()`.

📍 **In this project:** every module (Commerce, CRM, Finance, ...) is exactly one real business domain, not an arbitrary technical division.

## The Domain-Independent Core

**Simple definition:** this project has a central layer (`Core`) that never knows anything about any specific domain (not commerce, not healthcare, not anything) — it only provides generic infrastructure (identity, permissions, events).

**Why it matters:** this means the exact same Core can sit underneath any other domain — this is the foundation of the "fork the project for a completely different industry" idea you saw in the main series (file 22).

📍 **In this project:** this is exactly `CLAUDE.md`'s first principle: **"Infrastructure First, Domains Second."**

## Entity

**Simple definition:** a "thing" that has a stable identity that persists over time, even if its attributes change. A `Customer` is always the same customer, even if their address, name, or status changes — because they have a fixed `id`.

**Why it matters:** an Entity is more than just a database row — it can carry behavior (methods) that enforce real business rules, not just hold raw data.

📍 **In this project:** `Order` is an Entity — and its `cancel()` method only allows execution if the order is still in a cancelable state; the Entity itself enforces this rule, not some external Controller.

## Value Object

**Simple definition:** unlike an Entity, a Value Object has no separate identity of its own — it's defined entirely by its value. Two `Money` objects with the same amount and currency count as **the same** value, even if they're two separate instances in memory. Value Objects are usually **immutable** — built once and never changed; to change one, you build a new instance.

**Why it matters:** instead of keeping a price as a raw number (`int $price`) that anyone could put anything into (even negative, or the wrong unit), you wrap it in a `Money` class that itself guarantees it's always valid.

📍 **In this project:** `Money`, `SKU`, `Email`, `CouponCode` are all Value Objects — each enforcing its own validation rules right in its constructor, meaning an invalid `SKU` **can't even be constructed** in the first place.

## Domain Service

**Simple definition:** sometimes a business rule doesn't belong to any single Entity or Value Object — it combines several of them together, like calculating a final price from several Value Objects. This logic lives in a "Domain Service": a pure, stateless class with no database dependency.

📍 **In this project:** `PricingService` is exactly this — it only combines the numbers handed to it (`Total = Subtotal + Tax − Discount`), never touching the database itself.

## Repository

**Simple definition:** a middle layer between the world of Entities/Value Objects (which know nothing about the database) and the real world of storage. A Repository has an interface ("find a `Product` by `id`") and a real implementation (that actually talks to MySQL).

**Why it matters:** it means business logic never talks directly to Eloquent or SQL — only to a simple interface like "find this, save this."

📍 **In this project:** `ProductRepositoryInterface` is defined in the `Domain` layer; `EloquentProductRepository` in the `Infrastructure` layer actually implements it. The full pattern is covered in the next chapter of this pre-tutorial.

## Domain Event

**Simple definition:** a formal announcement that "an important business event just happened" — e.g. "an order was placed." Any other part of the system interested in this event (without the first part ever knowing they exist) can "listen" and react.

**Why it matters:** this is exactly what lets fully independent modules react to each other without a direct dependency — e.g. the Loyalty module can listen for "an order was placed" and award points, without the Commerce module ever knowing Loyalty even exists.

📍 **In this project:** `OrderWasPlaced` is a Domain Event; `OrderPlacedListener` in the Loyalty module listens for it and awards customer points — the first time this platform ever actually used this pattern (main series, file 7).

## Action / Application Service

**Simple definition:** where a complete business operation is "orchestrated" — e.g. "place an order" means: calculate the price, decrease stock, create a payment record, dispatch an event. An Action coordinates these steps, but delegates the actual logic of each rule to an Entity/Value Object/Domain Service.

**Why it matters:** this layer draws the exact line between "what needs to happen" (which lives in Domain) and "how all these steps run together" (which lives in Application).

📍 **In this project:** every MCP capability is tied to exactly one Action — e.g. `PlaceOrderAction`. Even more interesting: Actions can call other Actions (the "Actions composing Actions" pattern, main series file 17) — e.g. `EarnPointsAction` itself calls `CreateLoyaltyAccountAction` if the customer doesn't have a points account yet.

## Ubiquitous Language

**Simple definition:** an agreement that everyone (developers, business experts, even documentation) uses exactly one word for one concept — not code saying `status_code: 3` while a human says "the order was cancelled."

📍 **In this project:** this is exactly why method and class names always speak the real business language — `cancel()`, not `updateStatus(3)`; `Coupon::isExpired()`, not a scattered condition buried inside some Controller.

---

← [Software Architecture & Design Principles](03-software-architecture-and-design-principles.md) | Next: [Common Design Patterns](05-common-design-patterns.md) →
