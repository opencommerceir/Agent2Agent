/**
 * A canned-response fake implementing {@link Transport} — this SDK's
 * equivalent of the PHP SDK's own Guzzle `MockHandler` usage. No test in
 * this suite ever touches a real socket.
 */

import type { Transport, TransportResponse } from "../src/transport.ts";

export interface RecordedCall {
  method: string;
  url: string;
  headers: Record<string, string>;
  jsonBody?: Record<string, unknown>;
  timeoutSeconds: number;
}

export class FakeTransport implements Transport {
  readonly calls: RecordedCall[] = [];
  private readonly status: number;
  private readonly body: Record<string, unknown>;

  constructor(status: number, body: Record<string, unknown>) {
    this.status = status;
    this.body = body;
  }

  async request(
    method: string,
    url: string,
    headers: Record<string, string>,
    jsonBody: Record<string, unknown> | undefined,
    timeoutSeconds: number,
  ): Promise<TransportResponse> {
    this.calls.push({ method, url, headers, jsonBody, timeoutSeconds });
    return { status: this.status, body: this.body };
  }
}
