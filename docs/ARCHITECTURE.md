# OpenCommerce Platform Architecture

## Overview

OpenCommerce Platform is an open-source infrastructure layer designed to make business systems AI Agent Ready.

The platform creates a bridge between AI Agents and existing business applications.

OpenCommerce does not replace existing software systems.

Instead, it provides a standardized capability layer that allows AI Agents to discover, understand, and interact with business capabilities securely.

---

# Core Vision

The future of digital interaction will not only be human-driven.

AI Agents will become active participants in digital ecosystems.

OpenCommerce provides the infrastructure required for businesses to expose their capabilities to AI Agents.

The main architecture direction:

AI Agents

↓

OpenCommerce Platform

↓

Business Systems

---

# Architectural Goals

## Agent First

AI Agents are first-class consumers of the platform.

Agents must be able to:

- Discover capabilities
- Understand available actions
- Execute operations securely
- Receive structured responses

---

## Domain Independent Core

The Core platform must never depend on any business domain.

The same Core must support:

- Commerce
- CRM
- ERP
- Finance
- HR
- Healthcare
- Logistics
- Manufacturing

---

## Extensibility

New domains, connectors, and capabilities must be added without modifying existing infrastructure.

---

## Enterprise Ready

The architecture must support:

- Multi-tenancy
- Authentication
- Authorization
- Security
- Auditing
- Scalability
- External integrations

---

# High Level Architecture

The platform consists of these major layers:

AI Agents

↓

MCP Gateway

↓

OpenCommerce Core

↓

Identity / Organization / Tenant / Permission

↓

Capability Registry

↓

Agent Registry

↓

Connection Manager

↓

Domain Modules

↓

External Business Systems

---

# Core Platform

The Core is the foundation of OpenCommerce.

The Core provides infrastructure capabilities that every domain can use.

Core responsibilities:

- Identity
- Authentication
- Authorization
- Organizations
- Tenancy
- Permissions
- Events
- Configuration
- Extension system
- Infrastructure services

---

# Core Restrictions

The Core must never contain business domain logic.

The Core must not know about:

- Products
- Orders
- Customers
- Inventory
- Payments
- Shipping
- Discounts
- Marketing rules

Examples:

Wrong:

Core/ProductService.php

Correct:

Modules/Commerce/ProductService.php

---

# Platform Layers

## Identity Layer

Responsible for:

- Users
- Authentication
- API keys
- Tokens
- Agent identities

Purpose:

Define who is interacting with the platform.

---

# Organization Layer

Responsible for:

- Business accounts
- Organizations
- Membership
- Ownership

Purpose:

Represent businesses using OpenCommerce.

---

# Tenant Layer

Responsible for:

- Tenant isolation
- Tenant configuration
- Tenant context

Initial strategy:

Shared Database with tenant_id isolation.

Future strategy:

Database Per Tenant.

The architecture must support migration between these strategies.

---

# Permission Layer

Responsible for:

- Roles
- Permissions
- Access policies
- Capability authorization

Example:

commerce.products.read

commerce.orders.create

Permissions define what an Agent or User can execute.

---

# Capability Registry

The Capability Registry is the discovery engine of OpenCommerce.

Its purpose is to describe what a business system can do.

A capability contains:

- Name
- Description
- Input schema
- Output schema
- Required permissions
- Available tools

Example:

Capability:

commerce.product.search

Input:

query:string

Output:

products[]

AI Agents interact with capabilities instead of learning every platform-specific API.

---

# Agent Registry

The Agent Registry manages AI Agent identities.

Responsibilities:

- Register agents
- Authenticate agents
- Manage credentials
- Store metadata
- Assign permissions
- Track access

Example:

Shopping Agent

Permissions:

- commerce.products.read
- commerce.cart.create
- commerce.order.status.read

---

# Connection Manager

The Connection Manager handles external system integrations.

Examples:

- Shopify
- WooCommerce
- ERP Systems
- Custom Applications

Responsibilities:

- Connection configuration
- Credential management
- Adapter lifecycle
- Health monitoring

---

# MCP Gateway

The MCP Gateway is the communication layer between AI Agents and OpenCommerce.

Responsibilities:

- Authentication
- Authorization
- Capability discovery
- Tool discovery
- Request validation
- Tool execution
- Response formatting

---

# MCP Boundary

MCP is responsible for communication.

MCP is NOT responsible for:

- Business rules
- Pricing logic
- Inventory rules
- Order workflows

Business logic always belongs inside Domain Modules.

---

# Universal Commerce Protocol (UCP)

Universal Commerce Protocol provides a standardized commerce model.

Different commerce systems expose different structures.

Examples:

Shopify Product

WooCommerce Product

Custom ERP Product

are transformed into:

UCP Product

---

# UCP Responsibilities

UCP defines common commerce concepts:

- Products
- Categories
- Customers
- Inventory
- Cart
- Orders
- Payments
- Commerce Actions

---

# Connector Architecture

Connectors translate external systems into OpenCommerce standards.

Flow:

External System

↓

Connector

↓

UCP Model

↓

Commerce Domain

↓

MCP Gateway

↓

AI Agent

---

# Domain Modules

Business functionality lives inside Domain Modules.

Examples:

- Commerce
- CRM
- ERP
- Finance
- Healthcare

Each domain is isolated.

---

# Commerce Domain

Commerce is the first domain implemented.

Responsibilities:

- Product management
- Categories
- Inventory
- Cart
- Orders
- Customers
- Checkout workflows

Commerce must never leak platform-specific implementation details.

---

# Module Structure

Every module follows this structure:

Module

- Domain
- Application
- Infrastructure
- Interfaces
- Support
- Tests

Consistency is more important than clever implementation.

---

# Event Driven Architecture

Modules communicate through events.

Example:

OrderCreated

↓

InventoryUpdated

↓

NotificationSent

↓

AnalyticsUpdated

Avoid unnecessary direct dependencies.

---

# Data Flow Example

An AI Agent wants to purchase a product.

Flow:

AI Agent

↓

MCP Gateway

↓

Agent Authentication

↓

Capability Discovery

↓

Permission Validation

↓

Commerce Capability

↓

UCP Transformation

↓

Connector

↓

External System

↓

Response

↓

AI Agent

---

# API Architecture

OpenCommerce follows API First principles.

All public capabilities require:

- Stable contracts
- Versioning
- Authentication
- Documentation
- Validation

---

# Security Architecture

Security is a fundamental layer.

Required:

- Authentication
- Authorization
- Permission checks
- Credential encryption
- Audit logging
- Secure communication

---

# Multi Tenant Architecture

OpenCommerce is designed as a SaaS platform.

Tenant isolation must exist across:

- Database
- Cache
- Queue
- Events
- Storage
- Permissions

---

# Architectural Decisions

## Modular Monolith

OpenCommerce starts as a Modular Monolith.

Reasons:

- Faster development
- Easier maintenance
- Clear boundaries
- Future extraction capability

---

## Domain Driven Design

Business concepts must remain isolated.

---

## MCP Gateway

MCP is used as the standard communication layer between Agents and Platform.

---

## UCP

UCP is used as the normalized commerce abstraction layer.

---

## Connector Pattern

Connectors integrate external systems without creating coupling.

---

## Event Driven Communication

Events reduce dependencies between modules.

---

# Future Evolution

Long-term architecture:

OpenCommerce Core

+

Domain Modules

+

Capabilities

+

Connectors

+

AI Agents

The goal is to become an open infrastructure standard for Agent-Ready businesses.