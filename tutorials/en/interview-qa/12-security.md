← [Multi-Tenancy](11-multi-tenancy.md) | Next: [Payments & Fintech](13-payments-fintech.md) →

# 12. Security

Rate limiting (files 02, 03, 05, 11), the marker-interface exception-to-HTTP-status mapping (file 05), and how a webhook's own HMAC signature gets tested (file 06) were already covered from other angles. This file is where they, and everything not yet covered, get looked at specifically as security: the two genuinely separate authentication mechanisms this platform runs, concrete OWASP categories mapped onto real code, secrets management, and a few honestly incomplete pieces this project names rather than hides.

---

### Q1: This platform authenticates two completely different kinds of caller — an AI Agent and a human Dashboard User. Walk through both mechanisms and explain why they were never merged into one.

🎯 **What the interviewer is REALLY testing:**
Whether the candidate treats "authentication" as one generic concept, or actually knows this platform runs two structurally different, deliberately separate mechanisms.

✅ **Model answer:**
"An Agent authenticates with a Bearer token on every single stateless request — `AgentAuthenticationService` reads it, hashes it, and looks up the owning Agent by that hash in `agent_tokens` (file 02, question 3). A Dashboard `User` authenticates with an email/password login that establishes a real, cookie-backed session — `AuthenticateUserAction` verifies the password, then `LoginController` calls `Auth::loginUsingId()` to actually start the session. These were never merged, for a structural reason, not just a stylistic one: an MCP client is a stateless API caller with no browser, no cookie jar, and no concept of 'staying logged in' — a session would be meaningless to it. A human operator, by contrast, genuinely wants to stay logged in across page loads. Forcing one mechanism to serve both would mean either making Agents carry cookies (pointless for a stateless API) or making a human re-send credentials on every click (a terrible UX) — two genuinely different transports for two genuinely different kinds of caller."

🔁 **Likely follow-ups:**
1. "Does that mean the Dashboard needs CSRF protection but the MCP Gateway doesn't?" → Exactly — CSRF is an attack that rides on ambient credentials a browser sends automatically (cookies); a Bearer-token request has no ambient credential a malicious page could trick a browser into sending, so `routes/mcp.php` needs no CSRF token at all, while `/dashboard/*` and `/showcase` (both session-based) sit inside Laravel's standard `web` middleware group specifically to get it.
2. "Could the same real person be both a `User` and control an Agent?" → Yes, incidentally — but the platform has no concept linking the two; they're two completely independent identities that happen to be operated by the same human.

🚩 **Red flags:**
Proposing "just use one unified auth system for both" — that misses the actual reason two exist: they're solving different problems (a stateless API caller vs. a stateful human session), not the same problem solved twice by accident.

---

### Q2: Are Agent Bearer tokens stored in plaintext in the database? What's the actual security property here?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate assumes a naive "store the token, compare strings" design, or actually understands the hash-then-lookup pattern and why it matters.

✅ **Model answer:**
"No — `AgentAuthenticationService` hashes the incoming Bearer token and looks it up by that hash, never storing or comparing the raw token value itself (file 02, question 3). This means a stolen database dump doesn't directly hand out usable tokens the way a plaintext-token table would — the same principle password hashing protects, applied to an API credential instead of a login password. The one deliberate difference from `HashedPassword` (question 4 of this file): a login password is checked rarely (once per session) so it's correct to use `bcrypt`, an algorithm that's intentionally slow to resist brute-forcing. An Agent token is checked on **every single API call** — using a deliberately slow algorithm there would add real, compounding latency to every request this platform serves, so a fast cryptographic hash is the right tool for this specific job, not the same slow one used for passwords."

🔁 **Likely follow-ups:**
1. "So different credentials in this system use different hashing strategies?" → Yes, deliberately — the right hashing strategy depends on how often the value is verified, not a single one-size-fits-all rule.
2. "What happens if a token is compromised?" → There's no token-specific revocation UI covered in this handbook's scope, but the token belongs to exactly one Agent row, so deactivating/rotating that Agent's own token is the real mitigation path.

🚩 **Red flags:**
Assuming a Bearer token is just compared as a plain string against a stored column — that's the exact naive design this hash-then-lookup pattern avoids.

---

### Q3: Trace the Dashboard `User` login end to end. Where's the exact line between "verify this is really them" and "start a real session," and why split there?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate understands *why* credential verification and session establishment are two separate steps, not just that they are.

✅ **Model answer:**
"`AuthenticateUserAction` (Application layer) does the actual credential check — it loads the `User` by email and calls `HashedPassword::verifyPassword()` against the submitted password, returning `UserData` only on success, or throwing `InvalidCredentialsException` otherwise. `LoginController` (the HTTP layer) only calls Laravel's own `Auth::loginUsingId()` — the thing that actually writes the authenticated-session cookie — *after* that Action has already succeeded. This is the exact same 'verify identity, then separately adapt it to this specific transport' split `AgentAuthenticationService` already established for MCP (question 1 of this file), applied one layer over. The reason it matters for security specifically: password verification is genuine business logic that deserves to be tested in complete isolation from Laravel's session machinery — and, just as importantly, nothing ever starts a real, cookie-backed session on a credential check that hasn't fully succeeded yet."

🔁 **Likely follow-ups:**
1. "Could `AuthenticateUserAction` accidentally start a session by itself?" → No — it has no dependency on `Auth`/`Session` at all; it's a plain Application-layer class that only ever returns data or throws, the same framework-independence every other Action in this codebase has.
2. "What does an invalid login actually look like to the caller?" → A translated, generic error message (`InvalidCredentialsException`) that deliberately doesn't distinguish 'wrong email' from 'wrong password' — the same information-minimization principle question 5 of this file covers for cross-tenant lookups.

🚩 **Red flags:**
Describing login as "just call `Auth::attempt()`" without recognizing that this project deliberately keeps the real credential-verification logic in a framework-independent Action first — a subtle but real architectural distinction.

---

### Q4: Why does `HashedPassword` call PHP's own `password_hash()`/`password_verify()` directly, instead of Laravel's `Hash` facade?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can connect a security decision to this project's own architectural discipline, rather than treating it as an arbitrary choice.

✅ **Model answer:**
"Every Domain class in this codebase is deliberately framework-free — testable with plain PHPUnit, no Laravel container required (`PricingService`, `WorkflowEvaluator`, and every other Domain Service already follow this). `HashedPassword` was the first Value Object that ever needed real cryptographic hashing, and reaching for Laravel's `Hash` facade would have made it the first Domain-layer exception to that rule — for no actual benefit, since PHP's own standard library (`password_hash()`/`password_verify()`, using bcrypt under the hood) already does exactly what's needed, with zero framework dependency. This keeps `HashedPassword` unit-testable in complete isolation, the same as every other Value Object in this project, while still using a real, industry-standard, adaptive hashing algorithm underneath."

🔁 **Likely follow-ups:**
1. "Is `password_hash()`'s bcrypt implementation actually as safe as Laravel's `Hash` facade?" → Yes — Laravel's `Hash` facade is itself a thin wrapper around the exact same PHP functions; there's no real security difference, only a framework-coupling one.
2. "Would this decision change for a Value Object with a genuinely Laravel-only requirement?" → No — the project's rule is 'stay framework-free unless there's truly no equivalent,' and this VO is exactly the case where a framework-free equivalent already existed.

🚩 **Red flags:**
Claiming `password_hash()` is "less secure" than a framework facade — a real misunderstanding of what the facade actually wraps.

---

### Q5: Give me two concrete, real mechanisms in this codebase that defend against OWASP's "Broken Access Control" category.

🎯 **What the interviewer is REALLY testing:**
Whether OWASP knowledge is grounded in this specific codebase, not recited abstractly.

✅ **Model answer:**
"First, the 404-not-403 pattern on every cross-tenant lookup by id (file 11, question 5) — an Agent that guesses a real record id belonging to a different Tenant gets exactly the same response as if that id never existed at all, so no response ever confirms 'this resource is real, you're just forbidden from it.' Second, the deliberate ordering inside the real MCP request pipeline itself (file 02, question 3): authentication, then rate limiting, then authorization (`CheckPermissionAction`) — and only *after* that succeeds does input validation even run. That ordering is a real access-control decision, not an arbitrary sequence: an Agent that lacks permission for a capability never learns anything about whether its input would have been valid or not, because validation never runs for a request that was never authorized to begin with. Both mechanisms share the same underlying principle — never let an unauthorized caller learn more about a resource or a request than 'no.'"

🔁 **Likely follow-ups:**
1. "Is authorization checked once per request, or per capability?" → Per capability, every time — `CheckPermissionAction` checks the specific `requiredPermissions` the *called* capability declares, not a coarse 'is this Agent logged in at all' gate.
2. "Does this ordering ever cost something in the happy path?" → No — validating input for a request that turns out to be unauthorized would have been wasted work anyway; checking authorization first is both the safer and the cheaper ordering.

🚩 **Red flags:**
Only naming "we check permissions" without the specific 404-vs-403 and ordering details — a generic answer that doesn't demonstrate real, codebase-specific understanding.

---

### Q6: Give me concrete examples of this project getting OWASP's "Cryptographic Failures" category right — and one place it's honestly still incomplete.

🎯 **What the interviewer is REALLY testing:**
Balance — can the candidate cite real wins without pretending the project is flawless.

✅ **Model answer:**
"Three real, concrete wins: bcrypt for every Dashboard `User` password (question 4), hashed — never plaintext — Agent Bearer tokens (question 2), and `hash_equals()` used for every timing-sensitive string comparison this codebase actually performs — both the Showcase demo's own passcode gate and `StripeWebhookVerifier`'s HMAC signature check use it specifically to avoid a timing-attack leak from a naive `===` comparison, which can reveal how many leading characters matched through response-time differences. The honest incomplete piece: external connector credentials (WooCommerce, and by the same shape the payment gateways) live once in plain `.env` values per deployment, not encrypted-at-rest in a per-Tenant credentials table — file 11 (question 10) covers this gap in full as a multi-tenancy limitation, and it's the identical gap from a pure secrets-management angle: today, reading that `.env` file is reading every connected Tenant's own credentials at once."

🔁 **Likely follow-ups:**
1. "Why `hash_equals()` specifically, instead of `===`?" → `===` on strings short-circuits at the first mismatched byte, so its execution time leaks how many correct leading characters an attacker's guess had — `hash_equals()` runs in constant time regardless of where the mismatch is, closing that side channel.
2. "Is bcrypt still considered strong enough today?" → Yes, with an appropriately tuned cost factor — it remains a standard, accepted choice; the real risk in this area is more often a missing `hash_equals()` somewhere than bcrypt itself being weak.

🚩 **Red flags:**
Not knowing why `hash_equals()` exists at all, or claiming this project has *no* cryptographic gaps — both a missed technical detail and a credibility problem with an interviewer who already knows the codebase has honestly documented limitations.

---

### Q7: `StripeWebhookVerifier` checks a timestamp tolerance, not just whether the HMAC signature matches. What specific attack does that defend against?

🎯 **What the interviewer is REALLY testing:**
Understanding replay attacks specifically — a signature alone is not enough, and knowing exactly why.

✅ **Model answer:**
"A valid HMAC signature, by itself, only proves a payload was genuinely signed by Stripe at some point — it says nothing about *when*. Without a timestamp check, an attacker who ever captured one legitimately-signed webhook request (a network log, a compromised proxy) could resend that exact same request indefinitely, and the signature would still mathematically check out every time — a classic replay attack. `StripeWebhookVerifier` builds the signed payload as `"{timestamp}.{raw_body}"` and rejects anything older than a 300-second tolerance window, so even a perfectly-valid, correctly-signed replay of an old request gets rejected purely on staleness. Two more details worth knowing: it accepts a match against **any** `v1` signature Stripe includes (Stripe sends multiple during its own secret-rotation window, so only requiring one to match keeps rotation safe), and it deliberately ignores a `v0` signature entirely — a documented downgrade-attack decoy Stripe's own docs describe, since accepting a weaker, older scheme when a stronger one is present in the same payload is exactly how a downgrade attack works."

🔁 **Likely follow-ups:**
1. "How is this actually tested without a real Stripe account?" → File 06 (question 12) covers this directly — a real, self-signed test payload using the identical formula, including a case proving a tampered signature is rejected.
2. "Why 300 seconds specifically?" → Stripe's own documented recommendation — long enough to tolerate normal network/clock skew, short enough that a captured request is useless to replay shortly after.

🚩 **Red flags:**
Saying "the signature check alone is enough" — missing that a signature proves authenticity, not freshness, which is exactly the gap a timestamp check closes.

---

### Q8: The public payment callback and webhook routes use a deliberately tenant-*unscoped* lookup (`findByIdUnscoped()`). Doesn't that contradict everything file 11 established about tenant isolation?

🎯 **What the interviewer is REALLY testing:**
Whether the candidate can reason about a genuine, deliberate exception to a rule they just learned, rather than either blindly defending or blindly rejecting it.

✅ **Model answer:**
"It looks like a contradiction at first, but it isn't, because these two routes structurally can't carry an `AuthContext` at all — a payment gateway's own browser redirect or server-to-server webhook has no Agent Bearer token to present, so there's no authenticated Tenant to scope a lookup to in the first place. The safety here comes from a completely different mechanism than tenant scoping: `ConfirmRedirectPaymentAction` never trusts anything the caller claims about whether a payment succeeded — it always re-verifies, server-to-server, against the real gateway's own `verify()` API. `findByIdUnscoped()` only ever resolves *which* `PaymentSession` a caller means; at worst, a guessed or wrong session id just wastes one real `verify()` call against a session that isn't the caller's own — it can never mark a payment completed on someone else's behalf, because completion is decided by the gateway's own authoritative answer, never by the presence of a matching id."

🔁 **Likely follow-ups:**
1. "Could this be exploited to leak whether a session id exists?" → Only in the weak sense that a wrong id returns 'not found' rather than triggering a real verify() call — no payment state, amount, or Tenant information is ever exposed by that difference.
2. "Is this the same reasoning as the 404-vs-403 pattern (file 11, question 5)?" → A related but distinct case — that pattern is about *not leaking existence* to an authenticated-but-unauthorized caller; this one is about *not needing tenant scoping at all* because a stronger, independent verification step already decides the real outcome.

🚩 **Red flags:**
Flatly saying "an unscoped Repository lookup is always a security bug" — missing that the actual guarantee here comes from a different, stronger mechanism (server-side re-verification), not from scoping at all.

---

### Q9: How does this project make sure a real credential — an API key, a webhook secret — never ends up committed to git, or buried inside a Domain class?

🎯 **What the interviewer is REALLY testing:**
Whether secrets management is a real, structural discipline in this codebase, or just "we use `.env` like everyone else."

✅ **Model answer:**
"Two layers. First, `.env` itself is git-ignored — confirmed live, not just assumed, when this session's own real OpenRouter credentials were added (`git check-ignore` was actually run against it). Second, and more structurally: no class that calls an external service — `OpenAIClient`, `ClaudeClient`, `OpenRouterClient`, `WooCommerceClient` — ever calls `config()` or `env()` internally. Every credential is resolved exactly once, inside the owning `ServiceProvider::register()`, and handed to the class through its constructor. This means a credential's entire blast radius is one binding closure in one file, and — just as importantly for testing — swapping in a fake for a test is plain constructor injection, never mocking a global `config()` call (file 06, question 3 covers how external services get faked this way). On top of that, every test suite pins safe, keyless defaults (`PLANNER_TYPE=deterministic`, `REASONING_TYPE=simple`) specifically so a missing or misconfigured credential in a fresh clone never causes the test suite itself to attempt a real network call."

🔁 **Likely follow-ups:**
1. "What if a class did call `env()` directly?" → It would still work locally, but would break the 'inject a fake for a test' pattern entirely, and no test could prove that class's behavior without either a real credential or monkey-patching a global function — exactly the maintainability cost this discipline avoids.
2. "Has a real credential ever accidentally leaked in this project's own history?" → No known incident — this is a preventative discipline, not a post-incident fix.

🚩 **Red flags:**
"We just don't commit the `.env` file" as the whole answer — missing the deeper, more important discipline of never letting a credential leak into a class's own internals in the first place.

---

### Q10: This codebase ships with a real, seeded default Dashboard login (`admin@opencommerce.test` / `password`). Isn't that a security hole?

🎯 **What the interviewer is REALLY testing:**
Honesty under pressure — can the candidate acknowledge a real, documented weakness without either panicking or dismissing it.

✅ **Model answer:**
"Yes, if it's ever deployed unchanged — and this project says so explicitly, not quietly: `HANDOFF.md` flags it directly as something to 'change or remove before any real deployment.' It exists for a real, legitimate reason: `DatabaseSeeder` needs a working login in every fresh environment — local dev, CI, a freshly spun-up demo — with zero manual setup. This is the same tradeoff nearly every framework or CMS scaffold ships with a well-known default credential for; it's acceptable specifically *because* it's documented and flagged, not hidden as if it were a real secret. What makes it a slightly sharper gap here than in some other projects: there's currently no self-service password-reset flow at all (question 11 of this file) — today, rotating this credential means a direct database update or a fresh re-seed, not a 'forgot password' link."

🔁 **Likely follow-ups:**
1. "How would you actually fix this before a real launch?" → Either force a password change on first login for the seeded admin, or stop seeding a real, usable credential at all in any environment where `APP_ENV=production`.
2. "Is a documented default credential meaningfully different from an undocumented one?" → Yes, significantly — an undocumented one is a silent trap for whoever deploys this without reading `HANDOFF.md`; a documented one is a known, actionable checklist item.

🚩 **Red flags:**
Either "this is totally fine, it's just for dev" (ignoring that nothing technically stops it from reaching production) or "this project is insecure" (ignoring that it's honestly flagged, not hidden) — the strong answer holds both truths at once.

---

### Q11: `UserRole::Operator` exists but grants identical access to `Admin` today. Is that a real vulnerability, or an acceptable incremental gap?

🎯 **What the interviewer is REALLY testing:**
Precise judgment — distinguishing "a documented, known incompleteness" from "an actual security bug," which are not the same thing.

✅ **Model answer:**
"An acceptable incremental gap, not a vulnerability — and the distinction matters. A vulnerability would be `Operator` *promising* narrower access (a UI implying 'view-only') while silently allowing full `Admin`-level writes underneath — that would be a real, dangerous mismatch between stated and actual behavior. What actually exists is more honest: `UserRole::Operator` is a real, modeled enum case, but every Dashboard route this codebase has built so far is gated by the same single `admin` middleware alias, with no narrower check layered on top yet — documented directly in `HANDOFF.md`'s own technical-debt list as unbuilt, not silently broken. Nothing about today's behavior contradicts what's promised; the gap is that a genuinely narrower 'view but don't edit' role simply doesn't exist as a real, enforced distinction yet."

🔁 **Likely follow-ups:**
1. "How would you build the real distinction?" → A second, narrower middleware alias (or a capability-style permission check on `UserRole` itself) that specific write-routes require and `operator` routes don't — the same shape `CheckPermissionAction` already gives tenant-scoped Agents, one layer over for platform Users.
2. "Would you prioritize this before the seeded-credential gap (question 10)?" → No — question 12 of this file walks through exactly that prioritization, and the seeded credential is the cheaper, more concrete near-term risk.

🚩 **Red flags:**
Calling this "a critical vulnerability" without distinguishing it from an actual promise-vs-behavior mismatch — a real security review needs that precision, not just a reflexive "any gap is critical" reaction.

---

### Q12: If you had to run a real security review of this platform before its first paying multi-tenant customer, what's your prioritized list — pulling from this file and file 11 together?

🎯 **What the interviewer is REALLY testing:**
A closing, synthesizing answer across two files' worth of material — can the candidate rank real risks against each other, not just recite them in file order.

✅ **Model answer:**
"In order: first, rotate or remove the seeded default admin credential and put a real password-reset flow behind it (questions 10 and 11 of this file) — cheap, already flagged, and blocks nothing else, so there's no reason it shouldn't be first. Second, tenant-scoped connector credentials (file 11, question 10) — this is the one that actually blocks the *next real customer*, the moment a second Tenant wants their own WooCommerce store or their own payment gateway account connected. Third, a real `Operator` vs. `Admin` access distinction (question 11) — genuinely useful, but it only starts to matter once more than one kind of Dashboard user actually exists in practice. Last, Tenant-level rate-limit fairness (file 11, question 9) — a real, later-stage concern that only bites once a single Tenant is already provisioning enough Agents to meaningfully out-compete another. The ranking principle is the same one this project already applies throughout its own technical-debt list: prioritize by what's cheap-and-already-known first, then by what blocks the very next real customer, then by what only matters at genuine multi-tenant scale — not by which fix sounds architecturally most impressive."

🔁 **Likely follow-ups:**
1. "Would you add anything not covered in either file?" → A dependency/CVE audit of `composer.lock`/`package-lock.json` — not covered in this handbook at all, and a real, standard pre-launch step for any production deployment.
2. "What would make you re-order this list?" → A real incident or a specific compliance requirement (e.g. a Tenant needing physical data isolation) would immediately promote file 11's database-per-tenant question above everything on this list.

🚩 **Red flags:**
Reordering this list by "technical interestingness" (e.g. leading with database-per-tenant) instead of real, near-term impact — exactly the mistake file 11's own closing question already warned against, one file later.

---

← [Multi-Tenancy](11-multi-tenancy.md) | Next: [Payments & Fintech](13-payments-fintech.md) →
