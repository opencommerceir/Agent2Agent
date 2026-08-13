# Nexus Node.js SDK

Official Node.js client for the [Nexus Public REST API](/nexus/docs). Zero dependencies — built on the runtime's own `fetch` (Node >=18).

## Install

This package lives in-repo at `packages/nexus-sdk-node` today (not yet published to npm). To use it, copy the `src/` directory into your project, or reference it locally:

```
npm install ../nexus-sdk-node
```

## Usage

```js
import { NexusClient } from '@opencommerce/nexus-sdk';

const client = new NexusClient('https://your-nexus-domain.example.com', 'nx_your_api_key');

const profile = await client.getBusinessProfile();
const catalog = await client.getCatalog('widget');
const listings = await client.searchMarketplace({ industry: 'technology' });
const negotiation = await client.getNegotiation(42);
const balance = await client.getCreditBalance();

const result = await client.graphql('{ creditBalance { balance } catalog { products } }');
```

## Errors

```js
import { NexusApiError } from '@opencommerce/nexus-sdk';

try {
    await client.getCatalog();
} catch (err) {
    if (err instanceof NexusApiError) {
        // err.httpStatus, err.errorCode, err.message
    }
}
```

## Verifying webhook signatures

```js
const isValid = NexusClient.verifyWebhookSignature(rawRequestBody, request.headers['x-nexus-signature'], yourStoredWebhookSecret);
```

## Testing

```
npm test
```
