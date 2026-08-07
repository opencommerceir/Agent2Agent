← [Databases & Performance](02-databases-and-performance.md) | Next: [Domain-Driven Design (DDD)](04-domain-driven-design.md) →

# 3. Software Architecture & Design Principles

So far we've covered "how a request arrives" and "how data gets stored." This chapter tackles the bigger question: when you have thousands of lines of code and dozens of modules, how do you organize them so they're still understandable and changeable a year later? This project's answer (and most serious projects' answer) is one word: **architecture**.

## What is software architecture?

**Simple definition:** architecture is a set of decisions about "where code lives and why" — not the business logic itself, but the rules for organizing that logic.

**Why it matters:** a project with no architecture eventually turns into "spaghetti code" — everything is connected to everything, and changing one line breaks a dozen others.

📍 **In this project:** `CLAUDE.md` (the project's own guide file at the repository root) defines this as its first and most important rule: **"Architecture Over Speed."**

## Layered Architecture

**Simple definition:** you split code into distinct horizontal layers, each with one clear responsibility — e.g. "pure business logic" is one layer, "talking to the database" is another, "the HTTP interface" is a third.

**Why it matters:** each layer only needs to know how to talk to its neighboring layer, not to everything — meaning you can completely replace one layer (e.g. swap the database engine) without the business logic even noticing.

📍 **In this project:** every module has exactly 4 layers — `Domain`, `Application`, `Infrastructure`, `Interfaces` (main series, file 3) — and this structure repeats, without exception, in every module.

## Clean Architecture

**Simple definition:** a specific, strict version of layered architecture with one golden rule: **dependencies must always point inward**. The "central" layer (pure business logic) must never know anything about outer layers (the database, the web framework).

**Why it matters:** if your business logic directly depends on Laravel or MySQL, testing it becomes hard (you'd need to boot the whole framework) and swapping the framework becomes nearly impossible.

📍 **In this project:** the `Domain` layer of every module is deliberately **completely framework-independent** — you won't find a single `use Illuminate\...` in it. Classes like `PricingService` or `WorkflowEvaluator` are tested with pure PHPUnit, with no Laravel booted at all.

## Modular Monolith

**Simple definition:** unlike Microservices (where every part is a fully separate, independent service with its own database), a Modular Monolith runs everything in one single application, but **internally** splits it into fully distinct, independent modules that only talk to each other through a well-defined contract.

**Why it matters:** real Microservices carry heavy operational cost (multiple databases, multiple servers, network coordination) that's premature for a lot of projects. A Modular Monolith gives you the "clean, separated code" benefit without that cost — and if you ever need to move to Microservices later, it's much easier because the boundaries already exist.

📍 **In this project:** this is exactly the style `CLAUDE.md` chose — 10 domain modules (Commerce, CRM, Finance, ...) all run inside one single Laravel application, but each maintains its own fully independent boundaries.

## The SOLID Principles

Five well-known object-oriented design principles — this project actually applies all five:

| Letter | Principle | Meaning |
|---|---|---|
| **S** | Single Responsibility | A class should have only one reason to change |
| **O** | Open/Closed | You should be able to add new behavior without changing existing code |
| **L** | Liskov Substitution | Any implementation of an interface should be swappable for another without breaking anything |
| **I** | Interface Segregation | Small, focused interfaces are better than one giant interface |
| **D** | Dependency Inversion | Code should depend on an interface, not a specific implementation |

📍 **In this project:** "D" (Dependency Inversion) is the most heavily used — every Repository has an interface defined in the `Domain` layer, and its real implementation (using Eloquent) lives in the `Infrastructure` layer. "O" is demonstrated by the Connector pattern (chapter 5 of this pre-tutorial) — a new connector (e.g. Shopify) gets added without touching the existing WooCommerce code at all.

## Coupling and Cohesion

**Simple definition:** "Coupling" is how much two pieces of code depend on each other (less is better). "Cohesion" is how much the things inside one class/module actually belong together (more is better).

**Why it matters:** good architecture's ultimate goal is exactly these two words — low coupling between modules (so changing one doesn't break the others) and high cohesion within each module (so each one has one clear responsibility).

📍 **In this project:** the rule "no module ever directly depends on another module's Model or Entity, only on its Interface" (main series, file 7) is exactly a deliberate decision to keep coupling between modules low.

## Dependency Injection and the Container

**Simple definition:** instead of a class building its own dependencies itself (`new SomeClass()`), they're "injected" from outside (usually through the class's constructor parameters). A "container" is an automated system that figures out what each class needs and builds and injects it for you.

**Why it matters:** this means, in a test, you can swap a fake (Mock) version of a dependency in place of the real one, without changing the original code — the foundation of good testability.

📍 **In this project:** every Action (main series, file 3) receives its dependencies (Repository interfaces) through its constructor; Laravel's own container figures out which real implementation to inject, based on what's registered in each module's own `ServiceProvider`.

## Interface / Contract

**Simple definition:** a list of methods with no implementation — it only says "any class that claims to implement this interface must have these methods with this signature," without saying *how*.

📍 **In this project:** `PaymentGatewayInterface` says "every payment gateway must have a `charge()` method" — without saying how Zibal or a Mock exactly do it. This is exactly what lets a fake gateway (`MockPaymentGateway`) stand in for a real one in tests.

---

← [Databases & Performance](02-databases-and-performance.md) | Next: [Domain-Driven Design (DDD)](04-domain-driven-design.md) →
