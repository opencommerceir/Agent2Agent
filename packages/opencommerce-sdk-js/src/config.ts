/**
 * Immutable connection settings for a single {@link MCPClient} instance.
 *
 * Mirrors the PHP SDK's own `MCPConfig`
 * (`packages/opencommerce-sdk/src/Config/MCPConfig.php`) field for field.
 * `baseUrl` already carries the wire version in its own path
 * (`https://api.opencommerce.ir/mcp/v1`, `.../mcp/v2`, ...) — a consumer
 * picks a version simply by pointing at a different `baseUrl`, the same
 * explicit, no-hidden-behavior approach the server's own version
 * detection uses. `forVersion()` is purely additive sugar for building
 * that URL correctly.
 *
 * `timeout` is in **seconds** (not milliseconds) — kept consistent with
 * the PHP and Python SDKs' own default of `30`, converted to milliseconds
 * internally by the default transport.
 */

export interface MCPConfigOptions {
  baseUrl: string;
  token: string;
  timeout?: number;
  verifySSL?: boolean;
}

export interface MCPConfigForVersionOptions {
  host: string;
  version: string;
  token: string;
  timeout?: number;
  verifySSL?: boolean;
}

export class MCPConfig {
  readonly baseUrl: string;
  readonly token: string;
  readonly timeout: number;
  readonly verifySSL: boolean;

  constructor(options: MCPConfigOptions) {
    this.baseUrl = options.baseUrl;
    this.token = options.token;
    this.timeout = options.timeout ?? 30;
    this.verifySSL = options.verifySSL ?? true;
  }

  /**
   * Builds `baseUrl` as `{host}/mcp/{version}` for you.
   *
   * ```ts
   * const config = MCPConfig.forVersion({ host: "https://api.opencommerce.ir", version: "v2", token: "agent_token" });
   * // config.baseUrl === "https://api.opencommerce.ir/mcp/v2"
   * ```
   */
  static forVersion(options: MCPConfigForVersionOptions): MCPConfig {
    return new MCPConfig({
      baseUrl: `${options.host.replace(/\/+$/, "")}/mcp/${options.version}`,
      token: options.token,
      timeout: options.timeout,
      verifySSL: options.verifySSL,
    });
  }
}
