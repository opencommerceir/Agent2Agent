← [DDD Strategic](08-ddd-strategic.md) | Next: [CQRS & Read Models](10-cqrs-read-models.md) →

# 09. Event-Driven & Messaging

File 07 of this handbook saw *where* in the code an event gets dispatched. This file reaches the bigger questions: sync or async? Where does eventual consistency actually apply? And — an honest question a lot of self-described "Event-Driven" projects dodge — is this actually Event Sourcing?

---

### Q1: How is event-driven design defined in this project? What's the real difference from a direct method call?

🎯 **What the interviewer is REALLY testing:**
Understanding that event-driven design is first a *coupling* decision, then a *timing* decision.

✅ **Model answer:**
"The main difference is dependency direction, not execution speed. If `PlaceOrderAction` called `LoyaltyService::awardPoints()` directly, Commerce would have to know Loyalty exists — exactly the coupling rejected in file 02 of this handbook. Instead, `PlaceOrderAction` just dispatches `event(new OrderWasPlaced($order))` and stays completely unaware of whether anyone's listening — `OrderPlacedListener` in Loyalty listens for it independently. Today this call runs synchronously (question 2), but even being synchronous doesn't change the dependency direction: Commerce still never calls Loyalty directly."

🔁 **Likely follow-ups:**
1. "So if you removed the listener one day, would Commerce break?" → No — that's exactly the value of this separation; the publisher is never dependent on a consumer existing.
2. "Can several listeners react to one event?" → Any number, with none of them aware the others exist — `ShipmentStatusChangedListener` and any future listener can both independently listen for the same event.

🚩 **Red flags:**
"Event-driven means async" — a common misconception; event-driven is first about decoupling, async is a separate, later decision (question 2).

---

### Q2: Why do events run sync today, not async? Doesn't that contradict event-driven principles?

🎯 **What the interviewer is REALLY testing:**
A real trade-off between simplicity and scalability, not the slogan "async is always better."

✅ **Model answer:**
"It's not a contradiction — the same HTTP request that dispatches the event also waits for every listener to fully finish, but this is a deliberate scale decision, not a limitation of the pattern. `SendNotificationAction` (which has its own retry logic) runs inside this same synchronous cycle; that class's own docs explicitly say converting it to async only needs wrapping this same call in a Job — meaning the architecture is already ready for that move, it just hasn't been needed yet. The cost of staying sync is real: if a listener is slow, the entire HTTP response gets slow too; for today's scale, the simplicity of debugging (one single stack trace, not distributed tracing) justified that cost."

🔁 **Likely follow-ups:**
1. "Which listener is the first real candidate for async?" → `WebhookSender`, since it connects to a real external server — exactly the same answer as file 03 of this handbook (question 7).
2. "What does `QUEUE_CONNECTION=sync` mean architecturally?" → Even real Jobs (like `ProcessBulkImportJob`) run immediately and synchronously in tests — the real async-shaped code genuinely exists, its behavior is just synchronous under this configuration.

🚩 **Red flags:**
"You should have built everything async from the start" — with no understanding that async carries a real cost (a queue, a worker, distributed observability) that has to be justified.

---

### Q3: Where does eventual consistency actually apply in this project?

🎯 **What the interviewer is REALLY testing:**
Understanding that since everything runs sync today, this project technically has "immediate consistency," not eventual consistency — and whether the candidate knows the difference.

✅ **Model answer:**
"An honest answer: today, since every listener runs synchronously inside the same HTTP transaction, this project effectively has immediate consistency, not eventual consistency — by the time the HTTP response returns, every synchronous listener has already fully run (or failed). *Real* eventual consistency would only apply once this code converts to async (question 2) — at that moment, a real time window would open between 'the order was placed' and 'the customer's points were awarded,' which would need explicit handling (e.g. 'points may take a few seconds to appear'). Until then, this project makes no eventual-consistency claim at all — an honest distinction it maintains."

🔁 **Likely follow-ups:**
1. "So what happens if a listener fails right now?" → Question 6 of this file covers exactly that.
2. "Is there nowhere in this project with real eventual consistency today?" → The closest thing is queued Jobs (like a CSV import) — their result becomes ready after the initial HTTP response returns; the client has to explicitly poll for status (`commerce.bulk.get`), exactly the eventual-consistency pattern.

🚩 **Red flags:**
Claiming "we have eventual consistency" without recognizing that today's sync behavior is, technically and precisely, immediate consistency — exactly the kind of unfounded claim this project avoids.

---

### Q4: What's the Outbox pattern? Does this project use it? If not, why not?

🎯 **What the interviewer is REALLY testing:**
An honest "do you actually see this real gap" question — the Outbox pattern is a standard solution to a specific problem this project hasn't reached yet.

✅ **Model answer:**
"The Outbox pattern solves the 'dual-write problem': when you need to both save a database change and send a message to a real message queue (like RabbitMQ), those two operations can't be one atomic transaction — if the database succeeds but the message send fails (or vice versa), the system becomes inconsistent. The Outbox solution: write the message to a table inside that same database transaction; a separate process later reads that table and actually sends the message. This project doesn't need this pattern today — because events run sync, in-memory (question 2), there's never a real message send to an external system happening separately from the database transaction itself. This is a real, honestly acknowledged gap: if this project ever moves toward real microservices (file 02, question 7) and events become a real message queue, the Outbox pattern becomes necessary at exactly that moment."

🔁 **Likely follow-ups:**
1. "Is there any risk today without it?" → Not today — because everything (the database write and the listener execution) happens in one single PHP process, and usually one database transaction, the dual-write problem doesn't exist at all.
2. "Where in this project is the closest thing to this problem?" → Outbound webhooks (`WebhookSender`) — a real external HTTP call that can fail independently of the database's own success; today this is handled with in-request retry (file 03 of this handbook, question 7), not a real Outbox.

🚩 **Red flags:**
Suggesting "we should build an Outbox right now" without understanding this pattern solves a problem this project, in its current architecture, doesn't actually have — preemptively adding infrastructure for a nonexistent problem.

---

### Q5: Why does an event sometimes carry the whole Entity (OrderWasPlaced) and sometimes just an identifier (InventoryWasCommitted)?

🎯 **What the interviewer is REALLY testing:**
A real design rule for an event's payload shape, not an arbitrary choice.

✅ **Model answer:**
"The rule: if this event's real, known listeners need full detail and re-fetching it from the Repository would be a needless extra cost, the event carries the full Entity — `OrderWasPlaced` carries the entire `Order` because both `OrderPlacedListener`/`OrderPlacedNotificationListener` immediately need full detail. If the event represents a more general change that various, possibly future listeners might only need part of, it carries only an identifier — `InventoryWasCommitted` only carries `productId`/`tenantId`, and `InventoryLowListener` re-reads from the Repository itself. This second decision is deliberately more conservative: a lighter event stays less tightly coupled to an Entity's exact shape at one specific moment."

🔁 **Likely follow-ups:**
1. "Which approach is safer?" → 'ID only' is safer for long-term evolution (the Entity can change later without breaking the event's shape), but costs an extra query in every listener.
2. "Where else does this decision repeat?" → `AgentMessage`/Delegation events in the Agent Orchestrator carry the exact same trade-off — light events for things that happen frequently, heavier events for things that happen rarely but need immediate detail.

🚩 **Red flags:**
"Always just send the id, it's lighter" or the opposite, "always send the full Entity, it's simpler" — both are false universal rules; this decision should be based on the real needs of the listener.

---

### Q6: What happens if a listener throws an exception? Are other listeners affected too?

🎯 **What the interviewer is REALLY testing:**
Real understanding of error behavior in a synchronous event-driven system — a detail only discoverable through actual testing.

✅ **Model answer:**
"Laravel's default behavior is that if a listener throws an exception, the entire HTTP request's execution stops — meaning if `OrderPlacedListener` (Loyalty) fails, `OrderPlacedNotificationListener` (which hasn't run yet) never runs at all, and the user sees a 500 error, even though the order itself was already really placed. This project explicitly wraps listeners carrying this risk in their own try/catch — e.g. `SendNotificationAction` never lets a real notification-service failure break the whole request; instead, it records a `Failed` status and dispatches `NotificationFailed`, **never throwing at all**. This is a general project rule: any listener that might connect to a genuinely unreliable external service must swallow the failure itself, never let the event publisher get broken by it."

🔁 **Likely follow-ups:**
1. "Do all listeners have this protection?" → Honestly, no — only the ones genuinely connecting to an unreliable external service; `OrderPlacedListener` (which only writes to the same database) carries less of this risk, since its failure usually means a real bug that needs to surface, not ordinary network flakiness.
2. "Why not build this protection at the Laravel/infrastructure level instead of per listener?" → Because deciding whether a failure should stop the whole request is a Domain decision (does this failure genuinely deserve immediate attention?), not a uniform infrastructure decision for everyone.

🚩 **Red flags:**
Assuming a failed listener is automatically and silently ignored — Laravel's default behavior is exactly the opposite; the protection has to be built explicitly.

---

### Q7: How do you make sure a listener never runs twice, or that an event never gets lost?

🎯 **What the interviewer is REALLY testing:**
The At-Least-Once/At-Most-Once/Exactly-Once distinction — and what these guarantees inherently look like today, in a sync system.

✅ **Model answer:**
"Because events run synchronously today, inside the same PHP process, an event 'getting lost' has exactly one real cause: the whole HTTP request fails before it ever reaches `event()` — which means the primary operation itself (like placing an order) already failed too, so nothing should have been dispatched anyway. 'Running twice' today only happens if the calling code explicitly calls `event()` twice — a logic bug, not a messaging-system feature. This project has no event-level idempotency-key mechanism for exactly this reason — the current synchronous architecture inherently guarantees 'exactly once' with zero extra infrastructure. This is exactly what disappears the moment we move to real async (question 12) and has to be explicitly replaced."

🔁 **Likely follow-ups:**
1. "So does this project have no idempotency at all?" → It does, just not at the event level — the webhook/API level (file 05, question 8 of this handbook) is where 'the same thing arriving twice' genuinely can happen, since input there comes from a real, uncontrollable external system.
2. "What about a Job that fails and gets retried?" → Question 10 of this file covers exactly that — that's where idempotency genuinely matters, since a Job can actually, not just theoretically, run again.

🚩 **Red flags:**
Claiming "our event system guarantees at-least-once" without any real queue/retry infrastructure behind events — that's a technical claim that has to be backed by code, not just stated.

---

### Q8: What is Event Sourcing? Does this project use it? Why or why not?

🎯 **What the interviewer is REALLY testing:**
An honest, commonly asked Architect-level question — a lot of projects confuse "we have Domain Events" with "we are Event Sourced"; this question tests exactly that distinction.

✅ **Model answer:**
"A direct, honest answer: **no, this project is not Event Sourced.** Event Sourcing means an Aggregate's real state is never stored directly — instead, the complete sequence of events that happened to it is stored, and the current state is recomputed every time by 'replaying' all of those events. This project is exactly the opposite — the standard state-based persistence pattern: `Order` has a real row in the `orders` table directly representing its *current* state; `OrderWasPlaced` is only a **side notification**, dispatched after that state was already successfully saved, to inform the rest of the system — the event itself is never the source of truth, only the database is. If you deleted every listener today, no data would be lost, because none of them were ever the source used to reconstruct state."

🔁 **Likely follow-ups:**
1. "So is there nowhere that resembles Event Sourcing?" → The closest thing is `PointTransaction` (an append-only ledger) — question 9 of this file covers exactly the difference from real Event Sourcing.
2. "Where would Event Sourcing actually have made sense?" → An honest answer: where the complete change history itself has real business value on its own (like a real accounting ledger) — this project hasn't yet reached the level of real need that would justify Event Sourcing's added complexity.

🚩 **Red flags:**
Claiming "yes, this project is Event Sourced because it uses Domain Events" — exactly the common mistake this question is designed to expose; having Domain Events is a necessary condition for Event Sourcing, not a sufficient one.

---

### Q9: Why are PointTransaction/WorkflowLog/TrackingEvent a "Ledger" pattern (append-only, immutable) but not real Event Sourcing?

🎯 **What the interviewer is REALLY testing:**
A subtler distinction — these *look like* Event Sourcing (append-only, immutable rows), but aren't structurally the same thing.

✅ **Model answer:**
"These are a 'Ledger' — append-only, immutable historical records, exactly like `OrderItem` (file 07 of this handbook). But the fundamental difference from Event Sourcing is: `LoyaltyAccount.current_balance` is maintained directly and independently (`earn()`/`redeem()` both create a new `PointTransaction` *and* directly update `current_balance`) — that number is **never** derived by re-summing every historical `PointTransaction`. In real Event Sourcing, this would be exactly reversed: `current_balance` wouldn't have its own independent column at all, it would always be computed from a full replay of the ledger. This project made a deliberate decision: keep **both** a full historical ledger (for reporting/auditing) **and** an independent, fast current-state field (for ordinary reads) — not sacrificing one for the other."

🔁 **Likely follow-ups:**
1. "Does that mean these two could drift apart?" → Theoretically yes, if a bug only updates one of the two — exactly the risk real Event Sourcing (with one single source of truth) inherently eliminates; this project accepts that small risk in exchange for read simplicity and speed.
2. "Give me another example of this same pattern." → `WorkflowLog` has exactly this shape — a full historical ledger of every time a Workflow ran, but the `Workflow`'s own current state (active/inactive) is stored directly on it, never computed from the log.

🚩 **Red flags:**
Treating "an append-only log table" and "Event Sourcing" as the same thing — these look similar but are fundamentally different in terms of where the *source of truth* actually lives.

---

### Q10: When a Job (like ProcessBulkImportJob) fails, how does the state get recovered?

🎯 **What the interviewer is REALLY testing:**
Real understanding of error handling in a genuine queue system — where idempotency actually matters (unlike question 7, which didn't apply at the sync event level).

✅ **Model answer:**
"Every Job runs each chunk of up to 100 rows inside its own `DB::transaction()`, with a separate try/catch per row *inside* that same transaction — meaning an ordinary single-row failure never rolls back the rest of that chunk's rows, it's just recorded as a failed outcome. A separate, outer try/catch around the entire Job's `handle()` catches genuinely catastrophic failures (like a missing file) and moves `BulkOperation` to a `Failed` state — a completely different failure class than a single-row failure. Because every Job only takes primitive values (an id, a string) in its constructor, not a pre-built dependency, if the queue itself re-runs this Job (e.g. after a timeout), the Job starts over from scratch with the same original inputs."

🔁 **Likely follow-ups:**
1. "Does that mean a rerun reprocesses the same rows again?" → Yes, a real, honestly acknowledged risk — this project has no explicit 'resume from where it stopped' mechanism today; a rerun is a full restart from the first row, not a resume from the failure point.
2. "How is this different from the idempotency architecture in file 05 (question 8)?" → There (`ConfirmRedirectPaymentAction`), the code explicitly checks the current state before processing; here, no such check exists — a real difference in maturity level between these two mechanisms, acknowledged honestly rather than hidden.

🚩 **Red flags:**
Claiming "Jobs are always idempotent" without actually checking the code — as this question shows, that claim isn't true for `ProcessBulkImportJob` today.

---

### Q11: Why do listeners always inject another module's interface, never its Model directly?

🎯 **What the interviewer is REALLY testing:**
A direct connection between this file and the module-level Dependency Inversion rule (file 02 of this handbook) — seeing an architectural pattern repeat identically in a different context (a listener, not a Repository).

✅ **Model answer:**
"Because a listener, architecturally, follows the exact same rule as any other code in a module: never depend directly on another module's Model or Entity, only on its published interface. `InventoryLowListener` (in Workflows) gets `Commerce\Domain\Repositories\InventoryRepositoryInterface`/`ProductRepositoryInterface` injected — never `App\Modules\Commerce\Infrastructure\Models\Product` directly. If this rule were broken, Workflows would become dependent on Commerce's implementation details (not just its contract) — exactly the coupling this project's entire multi-module architecture (files 02 and 08) is designed to prevent."

🔁 **Likely follow-ups:**
1. "Doesn't the event itself violate this rule, since OrderWasPlaced carries the full Order?" → No — `OrderWasPlaced` carries the Domain Entity itself (`Commerce\Domain\Entities\Order`), not the Eloquent Model; the Entity is already completely framework-independent and free of Infrastructure detail, so carrying it isn't a boundary violation.
2. "Does that mean listeners can't write to another module's Repository either?" → They can, and do — `InventoryLowListener` only reads, but `OrderPlacedListener` (Loyalty) both reads and genuinely writes through `LoyaltyAccountRepositoryInterface`; the restriction is about *which module*, not *read vs. write*.

🚩 **Red flags:**
Suggesting "I'll just use the Model directly this one time to move faster" — exactly the temptation file 02 of this handbook (question 12) already named as the biggest real risk to architectural boundary erosion.

---

### Q12: If you had to turn this system into genuine async (with a real message queue), what new risks would that introduce that don't exist today?

🎯 **What the interviewer is REALLY testing:**
An Architect-level answer pulling every concept from this file (eventual consistency, Outbox, idempotency) together into one real decision.

✅ **Model answer:**
"Three real, new risks, each directly tied to an earlier question in this file. First, the dual-write problem (question 4) — the database write and the message send are no longer one atomic operation; the Outbox pattern goes from an optional choice to a genuine necessity. Second, the exactly-once guarantee disappears (question 7) — a real message queue usually guarantees at-least-once, not exactly-once, meaning every listener has to become explicitly idempotent (exactly the same pattern `ConfirmRedirectPaymentAction`, file 05 question 8, already has for webhooks, but now needed for every listener). Third, today's immediate consistency becomes *genuine* eventual consistency (question 3) — a real time window opens between 'the order was placed' and 'points were awarded' that the UI/client has to explicitly account for, instead of assuming everything is synchronized at that same instant."

🔁 **Likely follow-ups:**
1. "Which of these three risks is hardest?" → A balanced answer: idempotency is usually hardest, since it has to be individually revisited for *every* listener, not one centralized architectural change like the Outbox pattern.
2. "Is this project's existing pattern (event → listener) even preservable after this change?" → Yes, exactly the same contract (a publisher never knows its consumers) — only the infrastructure behind it (an in-memory queue vs. a real broker) changes, not the listeners' own code shape.

🚩 **Red flags:**
"I'll just change `QUEUE_CONNECTION` and it's done" — completely ignoring that genuine async introduces three entirely new classes of bugs (dual writes, duplicate execution, an inconsistency time window) that simply don't exist today.

---

← [DDD Strategic](08-ddd-strategic.md) | Next: [CQRS & Read Models](10-cqrs-read-models.md) →
