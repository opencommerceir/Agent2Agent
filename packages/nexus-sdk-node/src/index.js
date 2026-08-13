import { createHmac, timingSafeEqual } from 'node:crypto';

/**
 * Thrown for any non-2xx response — carries the Nexus error envelope's
 * own `code`/`message` fields (see /nexus/docs) rather than a generic
 * HTTP-status-only message.
 */
export class NexusApiError extends Error {
    constructor(httpStatus, errorCode, message) {
        super(message);
        this.name = 'NexusApiError';
        this.httpStatus = httpStatus;
        this.errorCode = errorCode;
    }
}

/**
 * Official Node.js client for the Nexus Public REST API. Zero
 * dependencies — built on the runtime's own `fetch` (Node >=18) rather
 * than axios/node-fetch, same "don't force a dependency choice on the
 * consumer" reasoning the PHP SDK (packages/nexus-sdk-php) already
 * applies with plain curl instead of Guzzle.
 */
export class NexusClient {
    #baseUrl;
    #apiKey;
    #fetchImpl;

    constructor(baseUrl, apiKey, { fetchImpl = fetch } = {}) {
        this.#baseUrl = baseUrl.replace(/\/$/, '');
        this.#apiKey = apiKey;
        this.#fetchImpl = fetchImpl;
    }

    async getBusinessProfile() {
        return this.#get('business');
    }

    async getCatalog(query) {
        return this.#get('catalog', query ? { query } : {});
    }

    async searchMarketplace({ query, industry } = {}) {
        const params = {};
        if (query) params.query = query;
        if (industry) params.industry = industry;
        return this.#get('marketplace/search', params);
    }

    async getNegotiation(negotiationId) {
        return this.#get(`negotiations/${negotiationId}`);
    }

    async getCreditBalance() {
        return this.#get('credit/balance');
    }

    async graphql(query, variables = {}) {
        return this.#request('POST', '/nexus/api/v1/graphql', { query, variables });
    }

    async #get(path, query = {}) {
        const search = new URLSearchParams(query).toString();
        const result = await this.#request('GET', `/nexus/api/v1/${path}${search ? `?${search}` : ''}`);
        return result.data ?? result;
    }

    async #request(method, path, jsonBody) {
        const response = await this.#fetchImpl(`${this.#baseUrl}${path}`, {
            method,
            headers: {
                Authorization: `Bearer ${this.#apiKey}`,
                Accept: 'application/json',
                ...(jsonBody ? { 'Content-Type': 'application/json' } : {}),
            },
            body: jsonBody ? JSON.stringify(jsonBody) : undefined,
        });

        const decoded = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = decoded.error ?? { code: 'UNKNOWN', message: 'Request failed.' };
            throw new NexusApiError(response.status, error.code, error.message);
        }

        return decoded;
    }

    /**
     * Verifies a webhook delivery's X-Nexus-Signature header — timing-safe
     * comparison (node:crypto's timingSafeEqual), the same discipline the
     * PHP SDK's hash_equals() and the platform's own credential checks use.
     */
    static verifyWebhookSignature(rawBody, signatureHeader, webhookSecret) {
        const expected = `sha256=${createHmac('sha256', webhookSecret).update(rawBody).digest('hex')}`;
        const expectedBuf = Buffer.from(expected);
        const actualBuf = Buffer.from(signatureHeader ?? '');

        if (expectedBuf.length !== actualBuf.length) {
            return false;
        }

        return timingSafeEqual(expectedBuf, actualBuf);
    }
}
