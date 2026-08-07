/**
 * The one class a developer needs to know about — the TypeScript/Node.js
 * equivalent of `packages/opencommerce-sdk/src/MCPClient.php`.
 *
 * ```ts
 * import { MCPClient, MCPConfig } from "@opencommerce/sdk";
 *
 * const config = new MCPConfig({ baseUrl: "http://localhost:8000/mcp/v1", token: "agent_token" });
 * const client = new MCPClient(config);
 *
 * const capabilities = await client.discoverCapabilities();
 * const result = await client.execute("commerce.product.search", { query: "laptop" });
 * console.log(result.data);
 * ```
 *
 * Migrating to v2 (`docs/api/migration/v1-to-v2.md`) is a one-argument
 * change — point `baseUrl` at `/mcp/v2` (or call
 * `MCPConfig.forVersion({ ..., version: "v2" })`) — nothing else about
 * this class changes, since v1/v2 only differ in the response envelope
 * shape, and `discoverCapabilities()`/`execute()` already tolerate both.
 */

import { MCPConfig } from "./config.ts";
import { type Capability, type ExecutionResult, capabilityFromJSON } from "./dtos.ts";
import { NotFoundException, exceptionFromResponse } from "./exceptions.ts";
import { AuthenticatedTransport, type Transport } from "./transport.ts";

export class MCPClient {
  private readonly request: AuthenticatedTransport;

  /**
   * `transport` is for tests only — production code should never pass
   * one, the same way the PHP SDK's `MCPClient` never passes an injected
   * Guzzle client to `AuthenticatedRequest` itself.
   */
  constructor(config: MCPConfig, transport?: Transport) {
    this.request = new AuthenticatedTransport(config, transport);
  }

  /**
   * Every capability this Agent's token can see.
   *
   * No caching — a cached list could go stale the moment a new capability
   * is registered server-side; wrap `MCPClient` yourself if you want that
   * trade-off.
   */
  async discoverCapabilities(): Promise<Capability[]> {
    const { status, body } = await this.request.get("capabilities");
    if (!isSuccess(status)) {
      throw exceptionFromResponse(status, body);
    }

    // v1 nests `capabilities` under `data`; v2 puts it at the top level
    // next to `metadata` — accept either, the same envelope-shape
    // tolerance execute() below applies to `result`/`data`.
    const nested = body.data as Record<string, unknown> | undefined;
    const rawCapabilities =
      (nested?.capabilities as unknown[] | undefined) ?? (body.capabilities as unknown[] | undefined) ?? [];

    return rawCapabilities.map((capability) => capabilityFromJSON(capability as Record<string, unknown>));
  }

  /**
   * Runs one capability. Rejects with a subclass of `MCPException` on any
   * non-2xx response — there is no "failed result" to check for
   * separately.
   */
  async execute(capabilityName: string, input: Record<string, unknown> = {}): Promise<ExecutionResult> {
    const { status, body } = await this.request.post("execute", { capability: capabilityName, input });
    if (!isSuccess(status)) {
      throw exceptionFromResponse(status, body);
    }

    const data = ("result" in body ? body.result : body.data) as Record<string, unknown> | undefined;
    const meta = ("metadata" in body ? body.metadata : body.meta) as Record<string, unknown> | undefined;

    return { data: data ?? {}, meta: meta ?? {} };
  }

  /**
   * Fetches one capability by name.
   *
   * There is no `GET /mcp/{version}/capabilities/{name}` endpoint on the
   * server today — this fetches the full discovery list and filters
   * client-side, exactly like the PHP SDK does.
   */
  async getCapability(capabilityName: string): Promise<Capability> {
    const capabilities = await this.discoverCapabilities();
    const found = capabilities.find((capability) => capability.name === capabilityName);

    if (found) {
      return found;
    }

    throw new NotFoundException("NOT_FOUND", `Capability [${capabilityName}] was not found.`, 404);
  }
}

function isSuccess(status: number): boolean {
  return status >= 200 && status < 300;
}
