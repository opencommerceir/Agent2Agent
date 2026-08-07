← [Domain-Driven Design (DDD)](04-domain-driven-design.md) | Next: [Backend Infrastructure & Laravel](06-backend-infrastructure-and-laravel.md) →

# 5. Common Design Patterns

A "design pattern" is a repeatable solution to a repeatable problem — a recipe that's proven itself across decades of the software industry. This project deliberately, consistently repeats a handful of specific patterns, over and over.

## The Repository Pattern (revisited)

We saw the concept in the last chapter — here it is as a **pattern**: "define an interface for accessing data; put the real implementation somewhere else." What makes this pattern powerful is that you can swap in a fake, in-memory implementation of that same interface in your tests.

📍 **In this project:** every module has one Repository interface per major Entity — this is one of the few patterns that, across all 10 modules, without a single exception, takes exactly the same shape.

## The Factory Pattern

**Simple definition:** instead of writing `new SomeComplexClass(...)` everywhere in your code, you delegate building that object to a separate method/class (a Factory) that knows exactly how to build it correctly.

**Why it matters:** when building a complex object is tricky (e.g. several validation rules must be honored), repeating that logic in ten different places is dangerous — write it once in the Factory, call it from everywhere.

📍 **In this project:** the static method `Inventory::stock()` is exactly a small Factory — it builds a fresh `Inventory` instance with zero stock, instead of every caller directly invoking the class constructor with default values.

## The Strategy Pattern (and its specific flavor: the Connector Pattern)

**Simple definition:** when there are several "ways" to do one job that all share the same contract shape but are implemented completely differently — you define one shared interface, and each of those "ways" becomes a separate implementation of that same interface. The calling code only ever talks to the interface, never knowing which real implementation is actually running behind it.

**Why it matters:** it means adding a new "way" (e.g. a new payment gateway) never requires changing existing code — you just add a new class.

📍 **In this project:** the "Connector Pattern" is exactly this Strategy pattern, just with its own dedicated name — and it's repeated **four times** across this platform: connecting to external stores (WooCommerce), shipping providers, notification channels (email/SMS/webhook), and real payment gateways (Zibal/Stripe). Every single time, the same fixed three pieces: an interface + a Registry (below) + several real implementations plus one fake one for testing.

## The Registry Pattern

**Simple definition:** a simple lookup book that registers several implementations of a Strategy under a name ("this one is called `zibal`, that one is called `stripe`"), so that at runtime, based on a plain string (e.g. from config or a caller's input), the correct implementation is selected.

📍 **In this project:** `PaymentGatewayRegistry` is exactly such a lookup book — when an Agent says "pay with `zibal`," the Registry returns that exact implementation; adding a fifth gateway means just one new registration line in this book.

## The Facade Pattern

**Simple definition:** you place a simple, unified surface in front of a complex subsystem, so code that uses it never has to know its internal details.

📍 **In this project:** the MCP Gateway itself, at a high level, is a Facade over the entire platform — an AI agent only ever knows one address and one capability name; it never sees the multiple Domain/Application/Infrastructure layers running behind that call.

## The Adapter Pattern

**Simple definition:** when an external system's data shape differs from your internal shape, an Adapter sits between the two and "translates" the data — so the rest of your code never has to deal with the external format directly.

📍 **In this project:** `WooCommerceProductConnector` is exactly an Adapter — it takes WooCommerce's own product structure and translates it into a normalized internal shape (`UCPProduct`, chapter 10 of this pre-tutorial), so the rest of the platform never has to deal with WooCommerce's raw format directly.

## The Observer Pattern (Event Listeners)

**Simple definition:** a practical version of the "Domain Event" we saw in the last chapter — one or more "listeners" watch for a specific event and run automatically when it's dispatched, without the event's publisher ever knowing they exist.

📍 **In this project:** `InventoryLowListener` listens for the "stock got low" event and triggers a Workflow — completely independent of which part of the code caused the stock to drop.

## DTO — Data Transfer Object

**Simple definition:** a simple object whose only job is carrying data between layers — with no business logic inside it. Unlike an Entity, which has behavior, a DTO is just a bundle of fields.

**Why it matters:** the `Domain` layer (Entities/Value Objects) shouldn't leak directly to the outside world (e.g. a JSON response) — a DTO is exactly that middle layer, keeping the "external" shape of data separate from its "internal" shape.

📍 **In this project:** every Entity has its own dedicated `*Data` class (e.g. `ProductData`) that Actions return; the Entity itself is never directly returned as an MCP response.

## The State Machine

**Simple definition:** when something can only ever be in one of a few specific "states" (e.g. an order: pending, paid, shipped, cancelled), and only certain transitions between states are allowed (you can't go directly from "shipped" back to "pending") — this is a state machine.

**Why it matters:** without an explicit state machine, transition rules usually end up scattered across ten different places in the code, and sooner or later, one gets forgotten.

📍 **In this project:** `Shipment`, `WarehouseTransfer`, `DelegationRequest`, and `Subscription` all carry an explicit `ALLOWED_TRANSITIONS` map — any attempt at an illegal transition is rejected on the spot.

---

← [Domain-Driven Design (DDD)](04-domain-driven-design.md) | Next: [Backend Infrastructure & Laravel](06-backend-infrastructure-and-laravel.md) →
