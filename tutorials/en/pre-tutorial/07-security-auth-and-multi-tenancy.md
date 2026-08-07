← [Backend Infrastructure & Laravel](06-backend-infrastructure-and-laravel.md) | Next: [Software Testing](08-software-testing.md) →

# 7. Security, Auth & Multi-Tenancy

This platform grants access to both humans (the Admin Dashboard) and AI agents (the MCP Gateway) — and it needs to be certain each one can only ever do exactly what it's allowed to do.

## Authentication vs. Authorization

**Simple definition:** these two words look similar but mean completely different things:
- **Authentication** = "who are you?" (proving identity)
- **Authorization** = "now that I know who you are, are you allowed to do this specific thing?"

**Why it matters:** a user can be authenticated (the system knows who they are) but still not be authorized to perform a specific operation — these two steps must always stay separate.

📍 **In this project:** every MCP call is first authenticated by `AgentAuthenticationService` (is this token valid?), then authorized by `CheckPermissionAction` (does this specific Agent have permission for this specific capability?) — two completely separate steps, always in this order.

## Bearer Token

**Simple definition:** a long, random string of text that plays the role of a "key" — whoever holds this string has that identity. It's sent in every request, in the `Authorization: Bearer <token>` header.

**Why it matters:** unlike a Session (below), a token needs no cookie or browser — for an AI agent or a script, this is the most natural approach.

📍 **In this project:** every `Agent` has an `AgentToken`; this is exactly what gets sent with every MCP call. The token itself is never stored raw in the database — only its hash (below) is stored.

## Hashing

**Simple definition:** turning a piece of text (like a password) into a seemingly random string in a way that's **irreversible** — you can't reconstruct the original text from the hash. To verify, you hash the entered password again and compare the two hashes.

**Why it matters:** if the database is ever breached, an attacker never sees users' actual passwords, only irreversible hashes.

📍 **In this project:** `HashedPassword` (for human Admin Dashboard users) uses PHP's own standard function (`password_hash`) — a deliberate choice, since this class lives in the `Domain` layer and shouldn't even depend on Laravel's own encryption facade (chapter 3 of this pre-tutorial, the framework-independence principle).

## Role-Based Access Control (RBAC)

**Simple definition:** instead of granting permissions to each individual user one by one, you group permissions into "Roles" (e.g. "Sales Manager" = a specific set of permissions), and each user gets one or more Roles.

**Why it matters:** managing dozens of permissions for hundreds of users, individually, is practically impossible; grouping them into Roles makes it manageable.

📍 **In this project:** an `Agent` has one or more `Role`s; each `Role` is a set of `Permission`s (like `commerce.orders.create`); before any capability runs, the system checks exactly whether the calling Agent has the required permission through one of its roles.

## Multi-Tenancy

**Simple definition:** one single deployment of software simultaneously serves several "Tenants" — e.g. several fully separate businesses — such that each one only ever sees its own data, as if it had its own dedicated install.

**Why it matters:** this is the foundation of any SaaS model — without it, you'd need to spin up a completely separate installation for every customer.

📍 **In this project:** almost every table has a `tenant_id`; every Repository always filters results by `tenant_id` — this has existed since Phase 1, not a later addition (main series, file 4; this feature's real business use cases are covered in file 22 too).

## Session vs. Token — two completely separate models

**Simple definition:** a "Session" means the server, after login, places an identifier in a cookie on the user's browser; every subsequent request sends that same cookie back, and the server recognizes the user. This model suits humans with browsers. A Bearer token (above) suits programs/agents that have no cookie.

📍 **In this project:** this platform deliberately keeps these two models fully separate, never mixed: the human Admin Dashboard uses a real Laravel Session (a `User` with a password); the MCP Gateway never uses Sessions at all, only tokens. Even the Showcase demo, which has a third model (a simple shared passcode), stays completely separate from both (main series, file 16).

## Rate Limiting

**Simple definition:** limiting how many requests a specific user/token can send within a time window (e.g. "at most 100 requests per minute").

**Why it matters:** preventing abuse (intentional or accidental — like a bug in an agent's code stuck in an infinite loop) that could take down an entire server.

📍 **In this project:** every Agent has a default limit of 100 requests per minute; exceeding it returns a clear `429` error (main series, file 4).

## CSRF (Cross-Site Request Forgery)

**Simple definition:** a security attack where a malicious site tricks a user's browser (which is already logged into your site) into sending a request to your site without the user's knowledge. Common defense: a secret, one-time-use token that only your real page knows.

**Why it matters here:** this attack only makes sense for browsers that keep cookies/Sessions — an AI agent using a Bearer token isn't exposed to this attack at all.

📍 **In this project:** the Admin Dashboard and the Showcase demo (both Session/browser-based) use Laravel's standard CSRF protection; the MCP Gateway doesn't need it at all, since it's stateless (chapter 1) and token-based.

---

← [Backend Infrastructure & Laravel](06-backend-infrastructure-and-laravel.md) | Next: [Software Testing](08-software-testing.md) →
