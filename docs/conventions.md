# OpenCommerce Platform Conventions

## Overview

This document defines the coding, architecture, naming, and development conventions used in the OpenCommerce Platform project.

The purpose of these conventions is to maintain:

- Consistent code quality
- Clean architecture
- Long-term maintainability
- Developer productivity
- Easy contribution for developers and AI coding assistants

All contributors and automated coding agents must follow these conventions.

---

# General Engineering Principles

## Clean Code First

Code should be:

- Easy to read
- Easy to understand
- Easy to maintain
- Easy to test
- Easy to extend

Prefer simple and explicit solutions over complex abstractions.

---

## Explicit Over Magic

Prefer:

- Clear dependencies
- Clear responsibilities
- Explicit workflows
- Predictable behavior

Avoid:

- Hidden logic
- Unnecessary magic
- Global state
- Implicit side effects

---

## Single Responsibility Principle

Every class, method, and module should have one clear responsibility.

A component should not handle multiple unrelated concerns.

Example:

Bad:

Controller:
- Validate request
- Calculate pricing
- Save database
- Call external APIs
- Send notifications

Good:

Controller:
- Receive request
- Call Action

Action:
- Execute business operation

Service:
- Coordinate complex logic

Repository:
- Handle persistence

---

# Naming Conventions

## Classes

Use PascalCase.

Examples:

- CapabilityRegistry
- AgentService
- CreateOrderAction
- ProductConnector
- TenantManager

---

## Methods

Use camelCase.

Examples:

- createAgent()
- findCapability()
- syncProducts()
- validatePermission()

---

## Variables

Use camelCase.

Examples:

- $tenantId
- $agentToken
- $productRepository
- $capabilityData

---

## Constants

Use uppercase snake_case.

Examples:

- MAX_RETRY_COUNT
- DEFAULT_TIMEOUT
- API_VERSION

---

# Laravel Conventions

OpenCommerce follows Laravel conventions with additional architecture rules.

Preferred structure:

app/

Core/

Modules/

Infrastructure/

Interfaces/

---

# Controllers

Controllers must remain thin.

Controllers are responsible for:

- Receiving HTTP requests
- Calling application layer
- Returning responses

Controllers must NOT contain:

- Business logic
- Complex calculations
- Database queries
- External API calls

Example:

Good:

Controller

↓

Action

↓

Domain Service

↓

Repository

---

# Models

Models represent:

- Database entities
- Relationships
- Simple data behavior

Models must NOT contain:

- Complex business workflows
- External API communication
- Large business calculations

Good:

User model:
- Relationships
- Casts
- Simple helpers

Bad:

Order model:
- Payment processing
- Inventory management
- Notification sending

---

# Services

Services coordinate application logic.

Use services when:

- Multiple operations are combined
- External communication exists
- A workflow needs coordination

Examples:

- CapabilityDiscoveryService
- AgentAuthenticationService
- ProductSyncService

Services should not become unlimited containers.

---

# Actions

Actions represent a single business operation.

Examples:

- CreateAgentAction
- RegisterCapabilityAction
- CreateOrderAction
- SyncConnectorAction

Rules:

- One action = one responsibility
- Clear input
- Clear output
- Easy testing

---

# DTO Conventions

DTOs transfer structured data between layers.

DTOs should:

- Represent data only
- Avoid business logic
- Be predictable

Examples:

- AgentData
- CapabilityData
- ProductData
- OrderData

---

# Interface Conventions

Interfaces define contracts.

Use interfaces when:

- Multiple implementations may exist
- External systems are involved
- Testing requires abstraction

Examples:

- ConnectorInterface
- CapabilityRepositoryInterface
- AgentAuthenticationInterface

---

# Repository Conventions

Repositories handle data access.

Repositories are responsible for:

- Queries
- Persistence
- Data retrieval

Repositories must NOT contain:

- Business rules
- Workflow logic
- Authorization decisions

Example:

Good:

ProductRepository:

- find()
- save()
- delete()

Bad:

ProductRepository:

- approveOrder()
- calculateDiscount()
- createInvoice()

---

# Module Structure

Every domain module follows this structure:

ModuleName/

Domain/

Application/

Infrastructure/

Interfaces/

Tests/

---

# Domain Layer Rules

Domain contains:

- Entities
- Value Objects
- Domain Services
- Domain Events
- Business Rules

Domain must not depend on:

- Database
- HTTP
- Laravel framework
- External APIs

---

# Application Layer Rules

Application layer contains:

- Actions
- Use Cases
- DTOs
- Application Services

Application coordinates domain operations.

---

# Infrastructure Layer Rules

Infrastructure contains:

- Database implementations
- External API clients
- Queue workers
- File storage
- Third-party services

---

# Interface Layer Rules

Interfaces expose system capabilities.

Examples:

- REST API
- MCP Tools
- Webhooks
- CLI Commands

---

# API Conventions

## Versioning

All public APIs must be versioned.

Example:

/api/v1/capabilities

---

## Request Validation

Always use Form Request classes.

Example:

CreateCapabilityRequest

Do not validate complex rules inside controllers.

---

## Response Format

All APIs should return consistent responses.

Success:

{
    "data": {},
    "message": "Success"
}

Error:

{
    "error": {},
    "message": "Failed"
}

---

# Event Conventions

Events represent facts that already happened.

Good:

- AgentRegistered
- CapabilityPublished
- OrderCreated

Bad:

- RegisterAgent
- PublishCapability
- CreateOrder

Events should be:

- Immutable
- Meaningful
- Descriptive

---

# Queue Conventions

Use queues for:

- Long-running operations
- External synchronization
- Notifications
- Heavy processing

Jobs must be:

- Retryable
- Observable
- Small
- Independent

Examples:

- SyncProductsJob
- ImportCustomersJob

---

# Database Conventions

## Table Names

Use plural snake_case.

Examples:

- users
- tenants
- organizations
- capabilities
- agent_tokens

---

## Column Names

Use snake_case.

Examples:

- tenant_id
- organization_id
- external_id
- created_at

---

## Foreign Keys

Format:

model_id

Examples:

- tenant_id
- agent_id
- capability_id

---

# Migration Rules

Migrations must:

- Have meaningful names
- Be reversible
- Avoid destructive changes

Good:

add_status_to_capabilities_table

Bad:

update_table

---

# Configuration Rules

Never hardcode:

- Passwords
- API keys
- Tokens
- Secrets

Use:

.env

config files

---

# Security Conventions

Always:

- Validate input
- Authenticate users and agents
- Authorize actions
- Encrypt sensitive data
- Log security events

Never trust:

- User input
- External systems
- AI Agent requests

---

# AI Agent Capability Conventions

Capabilities exposed to AI Agents must contain:

- Name
- Description
- Input schema
- Output schema
- Required permissions

Example:

commerce.product.search

Not:

getProducts()

Capabilities describe business abilities, not technical implementation.

---

# MCP Tool Naming

MCP tools follow:

domain.action

Examples:

commerce.product.search

commerce.order.create

crm.customer.lookup

---

# Connector Conventions

All external integrations must use connectors.

Examples:

- ShopifyConnector
- WooCommerceConnector
- ERPConnector

Connectors are responsible for:

- Communication
- Data translation
- Authentication

Connectors must not contain domain logic.

---

# Git Commit Conventions

Use Conventional Commits.

Format:

type(scope): description

Examples:

feat(core): add tenant management

feat(mcp): implement capability discovery

fix(auth): fix token validation

docs: update architecture documentation

---

# Branch Naming

Feature:

feature/capability-registry

Bug Fix:

fix/authentication-flow

Documentation:

docs/update-architecture

Refactor:

refactor/core-module

---

# Pull Request Conventions

Every pull request should include:

- Problem description
- Solution description
- Architecture impact
- Testing information
- Migration notes

---

# Testing Conventions

Tests should cover:

- Business rules
- Security rules
- Public APIs
- Critical workflows

Preferred:

- Unit Tests
- Feature Tests
- Integration Tests

---

# Documentation Conventions

Documentation must be updated when:

- Architecture changes
- New modules are created
- Public APIs change
- New decisions are made

Documentation is part of development.

---

# Code Review Principles

Review:

- Architecture quality
- Security
- Maintainability
- Performance
- Simplicity

Do not review only syntax.

Review design decisions.

---

# Final Principle

Consistency creates scalability.

Every contribution should feel like it belongs to the OpenCommerce Platform.

The codebase must remain understandable for:

- Core developers
- External contributors
- AI coding assistants
- Future maintainers