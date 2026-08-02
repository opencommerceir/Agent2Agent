# OpenCommerce Platform

> **Open-source infrastructure for building Agent-Ready business systems.**

OpenCommerce Platform is an open-source infrastructure that enables businesses to become **AI Agent Ready**.

As AI Agents become a new interface between people and digital services, businesses need a standardized way for agents to discover, understand, and securely interact with their products, services, and capabilities.

Today's software—including commerce platforms, marketplaces, ERP systems, CRM solutions, and custom business applications—was designed primarily for human users. OpenCommerce bridges the gap between these existing systems and the emerging world of AI Agents through open protocols, standardized interfaces, and developer-friendly tooling.

Our mission is to build the open infrastructure that powers the next generation of intelligent business software.

---

## Vision

The internet connected people to information.

Cloud platforms connected businesses to services.

The next evolution is connecting **AI Agents** to business capabilities.

Just as every business became **mobile-ready** and **search-engine friendly**, the next generation of digital businesses must become **Agent Ready**.

OpenCommerce Platform aims to become the open infrastructure that enables AI Agents to securely discover, understand, and execute business capabilities across modern business ecosystems.

---

## Why OpenCommerce?

Modern business software is fragmented.

Every platform exposes different APIs, authentication methods, data structures, and business rules. While APIs already exist, they were never designed for autonomous AI Agents capable of reasoning and performing complex workflows.

AI Agents need to:

- Discover available capabilities
- Understand business semantics
- Execute actions securely
- Navigate permissions
- Work consistently across different platforms

Today, every AI integration is typically built from scratch.

This creates duplicated work, inconsistent implementations, and poor scalability.

OpenCommerce solves this by introducing a unified infrastructure layer between AI Agents and business systems.

---

## Our Solution

OpenCommerce provides a common language between AI Agents and business software.

Instead of creating custom integrations for every platform, businesses expose their capabilities once through OpenCommerce.

AI Agents can then securely discover, understand, and execute those capabilities using standardized protocols.

The result is a scalable ecosystem where businesses become **Agent Ready** without replacing their existing systems.

---

## Core Architecture

OpenCommerce Platform is built around a modular architecture where every layer has a single responsibility.

### OpenCommerce Core

The foundation of the platform.

Responsible for:

- Identity & Authentication
- Organizations
- Multi-tenancy
- Permissions
- API Keys
- Configuration
- Connections
- Event Bus
- Audit Logs

---

### Agent Registry

Maintains information about registered AI Agents, their identities, permissions, supported protocols, and available connections.

---

### Capability Registry

The Capability Registry acts as the discovery layer of the platform.

Every connected business system exposes its capabilities in a standardized format.

Examples include:

- Search Products
- Check Inventory
- Create Orders
- Retrieve Customer Information
- Generate Reports
- Create Invoices

AI Agents discover these capabilities dynamically instead of relying on hardcoded integrations.

---

### MCP Gateway

The **Model Context Protocol (MCP)** Gateway provides the communication layer between AI Agents and OpenCommerce.

Responsibilities include:

- Authentication
- Authorization
- Capability Discovery
- Tool Execution
- Structured Responses

Business logic is never implemented inside the MCP Gateway.

---

### Universal Commerce Protocol (UCP)

The Universal Commerce Protocol (UCP) provides a normalized commerce model.

Different commerce systems—including Shopify, WooCommerce, Magento, Laravel applications, and custom platforms—are transformed into a common structure that AI Agents can understand consistently.

---

### SDK Platform

OpenCommerce provides official SDKs that enable developers to make their applications Agent Ready with minimal effort.

Planned SDKs include:

- PHP SDK
- Laravel SDK
- TypeScript SDK
- Node.js SDK
- Python SDK
- Go SDK

---

### Connectors

Connectors integrate existing business systems without requiring major architectural changes.

Examples include:

- Shopify
- WooCommerce
- Magento
- Laravel Commerce
- ERP Systems
- CRM Systems
- POS Systems
- Custom APIs

---

## First Domain: Commerce

Commerce is the first domain implemented on top of OpenCommerce Platform.

The initial objective is to allow AI Agents to:

- Discover products
- Understand product information
- Search inventory
- Compare products
- Create shopping carts
- Place orders
- Track orders
- Access customer information
- Execute complete commerce workflows

This establishes the foundation for **Agentic Commerce**.

---

## Beyond Commerce

Although Commerce is the first supported domain, OpenCommerce is designed as a general-purpose Agent infrastructure.

Future domains may include:

- CRM
- ERP
- Finance
- Human Resources
- Healthcare
- Logistics
- Manufacturing
- Customer Support
- Marketing Automation
- Analytics

The Core Platform remains domain-independent, allowing new modules to be added without changing the underlying architecture.

---

## Technology Stack

OpenCommerce Platform is built using modern technologies and architectural principles.

### Backend

- Laravel 12
- PHP 8.2+
- MySQL
- Redis
- Queue Workers

### Architecture

- Modular Monolith
- Domain-Driven Design (DDD)
- Clean Architecture
- Event-Driven Architecture
- API-First Design
- Capability-Driven Design
- Model Context Protocol (MCP)

---

## Design Principles

OpenCommerce follows a set of core architectural principles.

- Core is independent of business domains.
- Every capability should be discoverable.
- MCP handles communication, not business logic.
- UCP standardizes commerce data and workflows.
- Components should remain modular and independently replaceable.
- Developer Experience is a first-class priority.
- Extensibility is preferred over customization.
- Existing business systems should require minimal changes to become Agent Ready.

---

## Roadmap

The current roadmap focuses on building the platform foundation.

- [ ] OpenCommerce Core
- [ ] Identity & Authentication
- [ ] Organization Management
- [ ] Agent Registry
- [ ] Capability Registry
- [ ] MCP Gateway
- [ ] Universal Commerce Protocol (UCP)
- [ ] SDK Platform
- [ ] Commerce Connectors
- [ ] Event System
- [ ] Multi-tenant Infrastructure
- [ ] Developer Documentation

---

## Project Status

> 🚧 **Foundation Phase**

OpenCommerce Platform is currently focused on designing and implementing the foundational infrastructure required to build the Agent Economy.

The first milestone is enabling businesses to become **Agent Ready** while providing developers with an open, extensible platform for building AI-native business integrations.

---

## Contributing

OpenCommerce Platform is an open-source project.

We welcome developers, software architects, AI engineers, protocol designers, and contributors who share the vision of building the future of **Agentic Commerce** and **Agent-Ready Business Infrastructure**.

Whether you're improving documentation, implementing SDKs, building connectors, proposing architecture, or contributing code, your participation is always welcome.

---

## License

OpenCommerce Platform is released under the **MIT License**.
