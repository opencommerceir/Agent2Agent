# Nexus SDKs

Official client libraries for the [Nexus Public REST API](/nexus/docs) (Phase 9/M8) — none are published to their language's package registry yet; each lives in-repo and is used via a local path reference (see each package's own README).

| Package | Language | Dependencies | Tested in this environment |
|---|---|---|---|
| [`nexus-sdk-php`](./nexus-sdk-php) | PHP 8.2+ | `ext-curl`, `ext-json` only | Yes — `vendor/bin/phpunit` |
| [`nexus-sdk-node`](./nexus-sdk-node) | Node.js 18+ | none (built-in `fetch`) | Yes — `node --test` |
| [`nexus-sdk-python`](./nexus-sdk-python) | Python 3.9+ | none (standard library `urllib`) | Yes — `python -m unittest` |
| [`nexus-sdk-go`](./nexus-sdk-go) | Go 1.21+ | none (standard library `net/http`) | No — no Go toolchain in this dev environment; see the package's own README |

All four implement the identical contract: `getBusinessProfile`/`getCatalog`/`searchMarketplace`/`getNegotiation`/`getCreditBalance`/`graphql`, a `NexusApiError`-shaped exception carrying the API's own error envelope, and a `verifyWebhookSignature` static/module-level helper for Phase 9/M3 webhook consumers.
