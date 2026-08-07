/**
 * The HTTP layer, kept behind a small {@link Transport} interface so tests
 * never touch a real socket — the exact role
 * `packages/opencommerce-sdk/src/Authentication/AuthenticatedRequest.php`
 * plays in the PHP SDK, with an injectable client for that same reason.
 */

import { MCPConfig } from "./config.ts";

export interface TransportResponse {
  status: number;
  body: Record<string, unknown>;
}

export interface Transport {
  request(
    method: string,
    url: string,
    headers: Record<string, string>,
    jsonBody: Record<string, unknown> | undefined,
    timeoutSeconds: number,
  ): Promise<TransportResponse>;
}

/**
 * The real, default {@link Transport} — built entirely on the standard
 * `fetch` API, available natively in Node.js 18+, every modern browser,
 * Deno, and Bun. No HTTP client dependency at all.
 *
 * `verifySSL: false` is **not honored** by this transport — the standard
 * `fetch` API has no cross-platform way to disable certificate
 * verification (browsers never allow it from JS at all, by design). If you
 * need this for local development against a self-signed certificate,
 * inject your own `Transport` built on Node's own `https.Agent` instead —
 * see this package's README for a complete example. This is a documented,
 * deliberate limitation, not an oversight.
 */
export class FetchTransport implements Transport {
  async request(
    method: string,
    url: string,
    headers: Record<string, string>,
    jsonBody: Record<string, unknown> | undefined,
    timeoutSeconds: number,
  ): Promise<TransportResponse> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutSeconds * 1000);

    try {
      const response = await fetch(url, {
        method,
        headers: jsonBody !== undefined ? { ...headers, "Content-Type": "application/json" } : headers,
        body: jsonBody !== undefined ? JSON.stringify(jsonBody) : undefined,
        signal: controller.signal,
      });

      const text = await response.text();
      return { status: response.status, body: parseJSONObject(text) };
    } finally {
      clearTimeout(timer);
    }
  }
}

function parseJSONObject(text: string): Record<string, unknown> {
  if (!text) {
    return {};
  }

  try {
    const parsed: unknown = JSON.parse(text);
    return typeof parsed === "object" && parsed !== null && !Array.isArray(parsed)
      ? (parsed as Record<string, unknown>)
      : {};
  } catch {
    return {};
  }
}

/**
 * Joins {@link MCPConfig.baseUrl} to a path and attaches the bearer token
 * header, so {@link MCPClient} never builds a URL or a header object
 * itself.
 */
export class AuthenticatedTransport {
  private readonly config: MCPConfig;
  private readonly transport: Transport;

  constructor(config: MCPConfig, transport?: Transport) {
    this.config = config;
    this.transport = transport ?? new FetchTransport();
  }

  get(path: string): Promise<TransportResponse> {
    return this.request("GET", path);
  }

  post(path: string, jsonBody: Record<string, unknown>): Promise<TransportResponse> {
    return this.request("POST", path, jsonBody);
  }

  private request(
    method: string,
    path: string,
    jsonBody?: Record<string, unknown>,
  ): Promise<TransportResponse> {
    const url = `${this.config.baseUrl.replace(/\/+$/, "")}/${path.replace(/^\/+/, "")}`;
    const headers = { Authorization: `Bearer ${this.config.token}` };
    return this.transport.request(method, url, headers, jsonBody, this.config.timeout);
  }
}
