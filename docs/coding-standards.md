# Coding Standards & Laravel Conventions

## Overview
OpenCommerce follows Laravel conventions enhanced with strict Domain-Driven Design (DDD) and Clean Architecture rules. All code must be easy to read, test, and maintain.

---

## General Principles
1. **Clean Code First**: Prefer simple and explicit solutions over complex abstractions.
2. **Explicit Over Magic**: Avoid hidden logic, global state, and implicit side effects. Clear dependencies are mandatory.
3. **Single Responsibility Principle (SRP)**: Every class, method, and module must have one clear responsibility.

---

## Naming Conventions
- **Classes**: `PascalCase` (e.g., `CapabilityRegistry`, `CreateOrderAction`)
- **Methods**: `camelCase` (e.g., `createAgent()`, `syncProducts()`)
- **Variables**: `camelCase` (e.g., `$tenantId`, `$productRepository`)
- **Constants**: `UPPER_SNAKE_CASE` (e.g., `MAX_RETRY_COUNT`, `API_VERSION`)
- **Database Tables**: `plural snake_case` (e.g., `agent_tokens`, `capabilities`)
- **Database Columns**: `snake_case` (e.g., `tenant_id`, `external_id`)

---

## Laravel-Specific Rules

### 1. Controllers (Interfaces Layer)
Controllers MUST remain thin. They are only responsible for:
- Receiving HTTP requests.
- Calling the Application Layer (Actions/Services).
- Returning formatted responses.

**BAD**: Controller validates complex rules, calculates pricing, and saves to DB.
**GOOD**: Controller receives request → calls `CreateOrderAction` → returns JSON.

### 2. Actions (Application Layer)
Use Actions for single business operations.
- **Rule**: One Action = One Responsibility.
- **Example**: `RegisterCapabilityAction`, `SyncConnectorAction`.
- Must have clear input (DTOs/FormRequests) and clear output.

### 3. Models & Repositories (Infrastructure Layer)
- **Models**: Represent database entities, relationships, and simple data behavior (casts, helpers). MUST NOT contain complex business workflows or external API calls.
- **Repositories**: Handle queries and persistence. MUST NOT contain business rules, workflow logic, or authorization decisions.

### 4. Services
Use Services only when coordinating multiple operations, handling external communication, or managing complex workflows (e.g., `ProductSyncService`). They should not become unlimited "god" classes.

### 5. DTOs (Data Transfer Objects)
Use DTOs to transfer structured data between layers. They should represent data only, avoid business logic, and be predictable (e.g., `AgentData`, `OrderData`).

---

## Database & Migrations
- **Multi-Tenancy**: All business tables MUST include a `tenant_id` column (Decision 011).
- **Foreign Keys**: Format as `model_id` (e.g., `organization_id`, `capability_id`).
- **Migrations**: Must have meaningful names (e.g., `add_status_to_capabilities_table`), be reversible (`down` method), and avoid destructive changes.

---

## API & MCP Conventions
- **Versioning**: All public APIs must be versioned (e.g., `/api/v1/capabilities`).
- **Validation**: Always use Form Request classes. Do not validate complex rules inside controllers.
- **Response Format**:
  - Success: `{ "data": {}, "message": "Success" }`
  - Error: `{ "error": {}, "message": "Failed" }`
- **MCP Tool Naming**: Must follow `domain.action` format (e.g., `commerce.product.search`, NOT `getProducts()`).

---

## Event Conventions
- Events represent facts that **already happened**.
- **Good**: `AgentRegistered`, `OrderCreated`
- **Bad**: `RegisterAgent`, `CreateOrder`
- Events must be immutable, meaningful, and descriptive.

---

## Security Conventions
- **Always**: Validate input, authenticate users/agents, authorize actions, encrypt sensitive data, log security events.
- **Never**: Trust user input, external systems, or AI Agent requests blindly.
- **Configuration**: Never hardcode passwords, API keys, or secrets. Use `.env` and `config` files.