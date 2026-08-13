import { test } from 'node:test';
import assert from 'node:assert/strict';
import { NexusClient, NexusApiError } from '../src/index.js';

function fakeResponse(status, body) {
    return {
        ok: status < 400,
        status,
        json: async () => body,
    };
}

test('getCreditBalance returns the data payload', async () => {
    const client = new NexusClient('https://nexus.example.com', 'nx_test', {
        fetchImpl: async (url, options) => {
            assert.equal(url, 'https://nexus.example.com/nexus/api/v1/credit/balance');
            assert.equal(options.method, 'GET');
            return fakeResponse(200, { data: { businessId: 1, balance: 500 } });
        },
    });

    const result = await client.getCreditBalance();
    assert.equal(result.balance, 500);
});

test('getCatalog passes the query parameter', async () => {
    let capturedUrl;
    const client = new NexusClient('https://nexus.example.com', 'nx_test', {
        fetchImpl: async (url) => {
            capturedUrl = url;
            return fakeResponse(200, { data: { products: [], services: [] } });
        },
    });

    await client.getCatalog('widget');
    assert.equal(capturedUrl, 'https://nexus.example.com/nexus/api/v1/catalog?query=widget');
});

test('error response throws NexusApiError with envelope details', async () => {
    const client = new NexusClient('https://nexus.example.com', 'nx_test', {
        fetchImpl: async () => fakeResponse(403, { error: { code: 'FORBIDDEN', message: 'Missing scope: catalog.read' } }),
    });

    await assert.rejects(
        () => client.getCatalog(),
        (err) => {
            assert.ok(err instanceof NexusApiError);
            assert.equal(err.httpStatus, 403);
            assert.equal(err.errorCode, 'FORBIDDEN');
            assert.equal(err.message, 'Missing scope: catalog.read');
            return true;
        },
    );
});

test('graphql sends query and variables as JSON body', async () => {
    let capturedBody;
    const client = new NexusClient('https://nexus.example.com', 'nx_test', {
        fetchImpl: async (url, options) => {
            capturedBody = JSON.parse(options.body);
            return fakeResponse(200, { data: { creditBalance: { balance: 10 } } });
        },
    });

    await client.graphql('{ creditBalance { balance } }', { foo: 'bar' });
    assert.equal(capturedBody.query, '{ creditBalance { balance } }');
    assert.deepEqual(capturedBody.variables, { foo: 'bar' });
});

test('verifyWebhookSignature accepts a valid signature', async () => {
    const { createHmac } = await import('node:crypto');
    const body = '{"event":"negotiation.accepted"}';
    const secret = 'shhh';
    const signature = `sha256=${createHmac('sha256', secret).update(body).digest('hex')}`;

    assert.equal(NexusClient.verifyWebhookSignature(body, signature, secret), true);
});

test('verifyWebhookSignature rejects a tampered body', async () => {
    const { createHmac } = await import('node:crypto');
    const secret = 'shhh';
    const signature = `sha256=${createHmac('sha256', secret).update('{"event":"original"}').digest('hex')}`;

    assert.equal(NexusClient.verifyWebhookSignature('{"event":"tampered"}', signature, secret), false);
});
