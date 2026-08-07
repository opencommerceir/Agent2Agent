← [Table of Contents](00-table-of-contents.md) | Next: [Databases & Performance](02-databases-and-performance.md) →

# 1. Web & API Fundamentals

Before anything else, we need to understand what actually happens, technically, when we say "an AI agent connects to the platform." The short answer: an HTTP request is sent, and a JSON response comes back. This chapter unpacks that one sentence completely.

## Client / Server

**Simple definition:** the "client" is whatever makes a request (your browser, a mobile app, a Python script, an AI agent). The "server" is something that's always on, waits for requests, and answers them.

**Why it matters:** almost every modern software system is built on this simple model — one side wants something, the other side provides it.

📍 **In this project:** the Laravel server is this project itself (via `php artisan serve` or any real hosting). The client can be an admin's browser with the dashboard open, or an AI agent connected through one of this platform's five SDKs (main series, file 21).

## HTTP

**Simple definition:** HTTP is a "shared language" that clients and servers use to talk to each other — a fixed contract for what a request and a response should look like.

**Why it matters:** without a shared contract, every server would have to invent its own private language, and no client could reliably talk to it. HTTP solved this problem for the entire internet, once, for good.

📍 **In this project:** every interaction with this platform — from opening the Admin Dashboard to calling an AI capability — rides on HTTP.

## HTTP Methods (GET, POST, PUT/PATCH, DELETE)

**Simple definition:** every HTTP request carries a "verb" that states its intent:

| Method | Intent | Simple example |
|---|---|---|
| `GET` | "Just give it to me" — never changes anything | Get the list of products |
| `POST` | "Create something new" or "run an operation" | Place a new order |
| `PUT`/`PATCH` | "Edit something that already exists" | Update a customer's address |
| `DELETE` | "Remove something" | Delete a product |

**Why it matters:** this distinction lets the server, and any intermediary (like a cache or a firewall), instantly know whether a request is "read-only" or "changes something" — without ever opening the request body.

📍 **In this project:** interestingly, this platform's main gateway (the MCP Gateway, chapter 10 of this pre-tutorial) deliberately routes everything through one single method (`POST`), rather than building a separate address/method per capability — chapter 10 explains why.

## HTTP Status Codes

**Simple definition:** every HTTP response carries a three-digit number that summarizes the outcome — a client doesn't have to read the whole response body to know whether it succeeded.

| Range | Meaning | Common examples in this project |
|---|---|---|
| `2xx` | Success | `200 OK` |
| `4xx` | Client-side error (the request itself was wrong) | `401` (not authenticated), `403` (not authorized), `404` (not found), `422` (invalid input), `429` (too many requests) |
| `5xx` | Server-side error | `500` (an unexpected internal error) |

📍 **In this project:** `MCPExceptionHandler` (main series, file 5) does exactly this — it translates every kind of internal code error into one of these standard HTTP codes, so any client (not just PHP) can react correctly.

## URL and Endpoint

**Simple definition:** a URL is the address of a resource on the web (`https://api.opencommerce.ir/mcp/v1/execute`). An "endpoint" is a specific address the server has defined behavior for.

📍 **In this project:** `POST /mcp/v1/execute` is the single most important endpoint on the entire platform — almost every business capability (from searching products to executing an AI-driven goal) passes through this one address.

## REST and "API"

**Simple definition:** an API (Application Programming Interface) is "a door one program leaves open for other programs to work with it, without seeing its internal code." REST is a common style for designing APIs: every "thing" (a Resource) has a specific address, and HTTP methods (above) act on them — e.g. `GET /products/5` means "give me product number 5."

**Why it matters:** without APIs, every program could only talk to a human through a web page — never to another program. An API means software talks to other software.

📍 **In this project:** the MCP Gateway is also an API — but, as chapter 10 explains, it's deliberately not fully RESTful (one single address instead of separate addresses per resource) — because its audience isn't a human with a browser, it's an AI agent that needs to be able to **discover** every possible capability, not already know each one's address in advance.

## JSON

**Simple definition:** a simple text format for representing data — a series of "key: value" pairs, like a dictionary:

```json
{
  "name": "Laptop",
  "price": 25000000,
  "in_stock": true
}
```

**Why it matters:** almost every modern programming language can read and write JSON — a shared language between a PHP server and a Python/Go/JavaScript client.

📍 **In this project:** every request and response through the MCP Gateway is 100% JSON. Even when an agent written in a completely different language (Go) connects, the exact same JSON is read the exact same way.

## Request/Response Body and Headers

**Simple definition:** an HTTP request has two main parts — "headers" (metadata, like "this is my token" or "the content is JSON") and the "body" (the actual data, usually JSON).

📍 **In this project:** an Agent's authentication token is always sent in the `Authorization: Bearer <token>` header, never in the JSON body — a standard security convention explained further in chapter 7 of this pre-tutorial.

## Stateless

**Simple definition:** meaning the server remembers nothing about a client's previous request — every request must be complete and independent (e.g. it must resend its token every time, rather than assuming "since it logged in earlier, I already know who this is").

**Why it matters:** statelessness means you can run hundreds of parallel servers without worrying about which one holds a particular user's "memory" — that's exactly where scalability comes from.

📍 **In this project:** the MCP Gateway is completely stateless (every call carries its own token) — unlike the human Admin Dashboard, which uses Sessions (chapter 7), since its user is a browser with a cookie, not an agent.

## Backend vs. Frontend

**Simple definition:** the "frontend" is what a user directly sees and clicks on (the user interface). The "backend" is the logic, database, and business rules running behind the scenes that the user usually never sees.

📍 **In this project:** this platform is fundamentally a **pure backend** — it deliberately has no customer-facing storefront (main series, file 19). The only real frontend parts are the human Admin Dashboard and the Showcase demo; the rest of the platform only ever speaks through the API.

---

← [Table of Contents](00-table-of-contents.md) | Next: [Databases & Performance](02-databases-and-performance.md) →
