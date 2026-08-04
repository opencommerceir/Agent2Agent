# Module Structure & Guidelines

> This document describes the *shape* every module follows, not which
> modules exist. Ten are built and live today (Commerce, CRM, Finance,
> Workflows, Loyalty, Reporting, Shipping, Notifications, Analytics, plus
> Core itself) — see `docs/roadmap.md`/`HANDOFF.md` for the current list
> and build history.

## Overview
OpenCommerce is built as a **Modular Monolith**. Each business domain (e.g., Commerce, CRM, ERP) or platform capability (e.g., Core, MCP) is isolated into its own module. 

Modules must be highly cohesive and loosely coupled. They should behave like independent systems that can potentially be extracted into microservices in the future (Decision 002).

---

## Standard Module Directory Structure
Every domain module must strictly follow this directory structure:

app/Modules/{ModuleName}/
├── Domain/ # Business logic, Entities, Value Objects, Domain Events
├── Application/ # Actions, Use Cases, DTOs, Application Services
├── Infrastructure/ # Database implementations, External API clients, Jobs
├── Interfaces/ # Controllers, MCP Tools, Webhooks, CLI Commands
├── Tests/ # Unit, Feature, and Integration tests
└── ModuleServiceProvider.php # Registers routes, migrations, and bindings


---

## Layer Responsibilities

### 1. Domain Layer
- **Contains**: Entities, Value Objects, Domain Services, Domain Events, Business Rules.
- **Rules**: 
  - MUST NOT depend on Laravel framework, HTTP, Database, or External APIs.
  - MUST NOT contain infrastructure details.
  - Example: `Order` entity, `CalculateTax` domain service.

### 2. Application Layer
- **Contains**: Actions, Use Cases, DTOs, Application Services.
- **Rules**:
  - Coordinates domain operations.
  - Uses **Actions** for single business operations (e.g., `CreateOrderAction`).
  - Uses **DTOs** for structured data transfer between layers.

### 3. Infrastructure Layer
- **Contains**: Eloquent Models, Repositories, External API Connectors, Queue Jobs.
- **Rules**:
  - Handles persistence and third-party communication.
  - Repositories MUST NOT contain business rules or workflow logic.

### 4. Interfaces Layer
- **Contains**: HTTP Controllers, MCP Tool definitions, Webhook handlers.
- **Rules**:
  - Controllers MUST remain thin (Receive request → Call Action → Return response).
  - MUST NOT contain business logic, complex calculations, or direct database queries.

---

## Inter-Module Communication
Direct dependencies between modules are strictly forbidden. Modules must communicate via:
1. **Event-Driven Architecture**: Publishing and listening to domain events (e.g., `OrderCreated` triggers `InventoryUpdated`).
2. **Capability Registry**: Exposing functionality as capabilities (e.g., `commerce.product.search`) rather than direct method calls.

---

## The Core Module Exception
The `Core` module is domain-independent (Decision 005). It provides infrastructure capabilities (Identity, Tenancy, Permissions, Events) and **MUST NOT** contain any business domain logic (e.g., Products, Orders, Payments).

---

## Adding a New Module
When adding a new module (e.g., `CRM` in Phase 3):
1. Create the standard directory structure.
2. Register the `ModuleServiceProvider` in the main application.
3. Define the module's Capabilities in the Capability Registry.
4. Ensure all database tables include `tenant_id` for multi-tenancy (Decision 011).