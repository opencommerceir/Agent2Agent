# Nexus Platform — AI Agent Context

## Project Identity
**Nexus** = First Agent-to-Agent Economy Platform  
**Base:** OpenCommerce Platform (github.com/opencommerceir/opencommerce-platform)  
**Goal:** Businesses own AI Agents that autonomously discover, negotiate, transact

## Critical Rules (ALWAYS follow)

### 🏗️ Architecture
- **NEVER rebuild** OpenCommerce components — EXTEND them
- Available: MCP Gateway, Agent Orchestrator, Multi-Tenancy, Payments (Zibal/Stripe)
- Tech: Laravel 12, PHP 8.2+, MySQL, Redis

### 🎨 UI/UX: "Personal AI Like Jarvis"
- Dark-first, glassmorphism, neon accents (cyan #00F0FF, purple #A855F7)
- Bilingual (FA/EN) with RTL support — Persian is PRIMARY
- Responsive: mobile-first, tablet, desktop
- Animations: pulse-glow, data-stream, negotiation-flow

### 🧠 LLM Strategy
- **Hybrid approach:** Rule Engine (80%) + Small LLM (15%) + Large LLM (5%)
- **Default:** Local models (Qwen 2.5 14B, Llama 3.2 8B) — ZERO cost
- **Switchable:** Admin can change LLM provider per feature (hot-reload)
- **Read** `docs/claude/llm-strategy.md` for full details

### 💰 Monetization
- Credit-based economy (businesses buy credits to run Agents)
- Admin sets margins on LLM costs, transactions, subscriptions
- **Read** `docs/claude/monetization.md` for pricing model

### 🔒 Security
- Agent CANNOT autonomous high-value actions (human-in-loop)
- Immutable audit trail for all Agent actions
- Tenant isolation (each business = separate Tenant)

### 🧪 Testing
- Minimum 80% coverage, 100% on critical paths
- Unit + Feature + E2E tests required
- **Read** `docs/claude/testing-standards.md` for standards

## Key Features

### Business Onboarding
User registers → Business profile → Product/Service catalog → Agent generated → Live

### Agent-to-Agent Negotiation
Discovery → Match → Proposal → Counter → Agree → Contract → Execute → Review


### Admin Panel Must-Haves
1. LLM Switcher (per feature, hot-reload)
2. Margin Settings (profit on LLM, transactions)
3. Credit Management (balances, packages)
4. Live Negotiation Monitor
5. Revenue Dashboard
6. Audit Logs

## When Building Features

**ALWAYS check:**
- [ ] Bilingual (FA/EN)?
- [ ] RTL support?
- [ ] Responsive?
- [ ] Credit cost tracked?
- [ ] Audit logged?
- [ ] Tests written?

## Reference Files (read only when needed)

- **LLM Details:** `docs/claude/llm-strategy.md`
- **UI Components:** `docs/claude/ui-design-system.md`
- **Pricing Model:** `docs/claude/monetization.md`
- **API Patterns:** `docs/claude/api-patterns.md`
- **Testing Rules:** `docs/claude/testing-standards.md`
- **Roadmap:** `docs/agent-community-roadmap.md`

## Quick Start Command

When starting work, say:
> "Read CLAUDE.md and start Phase 0, Milestone 0.1 from the roadmap"

---

**Built for the Agent Economy** 🚀