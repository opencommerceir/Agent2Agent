# Git Workflow & Collaboration Guidelines

## Overview
To maintain a clean, understandable, and scalable codebase, all contributors must follow this Git workflow. Consistency in version control is as important as consistency in code.

---

## Branching Strategy
We use a simplified feature-branch workflow. The `main` branch always contains production-ready code.

### Branch Naming Conventions
Branch names must be lowercase, use hyphens (`-`) as separators, and start with a type prefix:

- `feature/` : New features or capabilities (e.g., `feature/capability-registry`)
- `fix/` : Bug fixes (e.g., `fix/authentication-flow`)
- `docs/` : Documentation updates (e.g., `docs/update-architecture`)
- `refactor/` : Code restructuring without changing behavior (e.g., `refactor/core-module`)
- `chore/` : Maintenance tasks, dependency updates (e.g., `chore/update-laravel-13`)

---

## Commit Message Guidelines
We strictly use **Conventional Commits**. This allows for automated changelog generation and clear history.

**Format:** 
type(scope): short description


**Types:**
- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Changes that do not affect the meaning of the code (white-space, formatting)
- `refactor`: A code change that neither fixes a bug nor adds a feature
- `test`: Adding missing tests or correcting existing tests
- `chore`: Changes to the build process or auxiliary tools

**Examples:**
- `feat(mcp): implement capability discovery endpoint`
- `fix(auth): resolve tenant_id validation error`
- `docs(architecture): update UCP data flow diagram`
- `refactor(commerce): extract pricing logic to CreateOrderAction`

---

## Pull Request (PR) Process
Every PR must be reviewed before merging into `main`. The PR description must include:

1. **Problem Description**: What issue does this solve?
2. **Solution Description**: How was it solved?
3. **Architecture Impact**: Does this introduce new dependencies, change database schema, or affect multi-tenancy?
4. **Testing Information**: How was this tested? (Unit/Feature tests added?)
5. **Migration Notes**: Are there any `php artisan migrate` steps or `.env` changes required?

*Note: Documentation MUST be updated if the PR changes architecture, public APIs, or introduces new modules (Decision 014).*

---

## Code Review Principles
Reviewers should focus on:
- **Architecture Quality**: Does it respect Modular Monolith and DDD boundaries?
- **Security**: Is input validated? Are permissions checked? Is `tenant_id` enforced?
- **Maintainability**: Is the code explicit over magic? Are controllers thin?
- **Simplicity**: Prefer simple, readable solutions over clever, complex abstractions.

Do not focus solely on syntax; focus on design decisions.
