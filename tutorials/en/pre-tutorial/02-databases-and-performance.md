← [Web & API Fundamentals](01-web-and-api-fundamentals.md) | Next: [Software Architecture & Design Principles](03-software-architecture-and-design-principles.md) →

# 2. Databases & Performance

Every piece of data this platform needs to remember — a product, an order, a Tenant, an AI execution — needs to live somewhere that survives the server being turned off. That somewhere is the database.

## Database, Table, Row, Column

**Simple definition:** a database is like a big, organized spreadsheet. A "table" is one sheet (e.g. the `products` table). A "column" is a specific type of information (like `name` or `price`). A "row" is one real record (one specific product).

📍 **In this project:** every domain module (Commerce, CRM, Finance, ...) has its own set of tables — e.g. `products`, `orders`, `tickets`, `invoices`. You can see the full list in `database/migrations/`.

## Primary Key / Foreign Key

**Simple definition:** a "primary key" is a unique identifier for each row (usually a number called `id`). A "foreign key" means a row in one table points to a row in another table — e.g. an `order` points to a `customer_id` to say "this order belongs to this specific customer."

**Why it matters:** this is exactly the mechanism that lets data be "related" to other data without duplicating information everywhere.

📍 **In this project:** almost every table has a `tenant_id` column — a foreign key to the `tenants` table — which is the foundation of this platform's entire multi-tenancy model (chapter 7 of this pre-tutorial, and main series file 4).

## Relationships

**Simple definition:** the most common relationship type is "One-to-Many" — an `Order` can have many `OrderItem`s, but each `OrderItem` belongs to exactly one `Order`. Another type is "Many-to-Many" — e.g. a `Customer` can have many `Tag`s, and each `Tag` can be on many `Customer`s; this kind usually needs a separate "pivot" table.

📍 **In this project:** `customer_tag` is exactly that many-to-many pivot table between `Customer` and `Tag` (main series, file 7).

## Migration

**Simple definition:** instead of manually changing table structures directly on the database, you write each change as a code file (e.g. "create a new table" or "add a new column"). These files run in order and build the exact database structure, reproducibly, every time.

**Why it matters:** every developer, on every machine, ends up with exactly the same database structure — and a complete history of "when what was added to the database" is recorded in Git.

📍 **In this project:** the `database/migrations/` folder is literally the complete growth history of this platform — every file represents a real architectural decision (`HANDOFF.md` refers directly to these file numbers).

## Query and SQL

**Simple definition:** a "query" is a request to the database ("give me all this customer's orders"). SQL is the standard language these requests are written in.

📍 **In this project:** the code virtually never writes raw SQL anywhere — everything is built through Eloquent (chapter 6 of this pre-tutorial), except for a few deliberate exceptions (like the Reporting module's own Query Builders, main series file 7) built specifically for fast, aggregate reports.

## Transaction

**Simple definition:** a group of database changes that must either **all** happen, or **none** of them do. If something fails partway through, every change in that group is rolled back — as if none of it ever happened.

**Why it matters:** say placing an order involves "decrease warehouse stock" + "record a payment." If stock decreases but the payment fails for any reason, and there's no transaction, the warehouse now lies. A transaction makes that inconsistency impossible.

📍 **In this project:** `ProcessPaymentAction` wraps the whole "calculate price → decrease stock → create order → record payment" flow in exactly one `DB::transaction()` for exactly this reason (main series, file 6).

## Locking and Race Conditions

**Simple definition:** imagine two people trying to buy the last unit of a product's stock at the same time. If both "check the stock" and then "decrease it" simultaneously, both might think stock is available when only one purchase should succeed — this is a "race condition." "Locking" means when one request is reading a row in order to change it, every other request must wait until the first one finishes.

**Why it matters:** without proper locking, a high-traffic store can easily sell more than it actually has in stock — a real, costly bug.

📍 **In this project:** this is exactly the bug that was found and fixed during the "Tech Debt Sprint" (`HANDOFF.md` §7.13) — `findByProductForUpdate()` takes a real row-level lock so two simultaneous `AddToCartAction` calls can't both reserve more than the real available stock.

## The N+1 Problem

**Simple definition:** imagine you want to display 100 orders along with their items. The naive approach: one query to get the 100 orders, then **one separate query per order** to get its items — 101 database round trips instead of one or two.

**Why it matters:** this is one of the most common performance bugs in the entire software industry — the code looks correct, but as data grows, the server chokes.

📍 **In this project:** a full code audit during "Performance Optimization" (main series, file 10) found exactly 4 real N+1 cases and fixed them with "Eager Loading" (loading related data upfront, i.e. fetching orders and their items in one combined query).

## Index

**Simple definition:** a helper structure a database builds on one or more columns to make searching on those columns dramatically faster — exactly like the alphabetical index at the back of a book, so you don't have to flip through the whole book to find one word.

**Why it matters:** without proper indexing, a table with millions of rows can take seconds for a simple search.

📍 **In this project:** a dedicated migration (`add_performance_indexes`, main series file 10) did exactly this — but only after checking every proposed index against the real table structure first, so no duplicate or incorrect indexes were added.

---

← [Web & API Fundamentals](01-web-and-api-fundamentals.md) | Next: [Software Architecture & Design Principles](03-software-architecture-and-design-principles.md) →
