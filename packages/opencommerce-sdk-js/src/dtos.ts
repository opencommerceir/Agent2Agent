/**
 * Plain, structural types returned by {@link MCPClient} — object literals,
 * not classes, since that's idiomatic for read-only data shapes in
 * TypeScript/JavaScript (the same reasoning the Python SDK uses `dataclass`
 * instances instead of the PHP SDK's own DTO classes for the same data).
 */

export interface Capability {
  readonly name: string;
  readonly description: string;
  readonly inputSchema: Record<string, string>;
  readonly outputSchema: Record<string, string>;
  readonly requiredPermissions: readonly string[];
}

export function capabilityFromJSON(data: Record<string, unknown>): Capability {
  return {
    name: data.name as string,
    description: (data.description as string) ?? "",
    inputSchema: (data.inputSchema as Record<string, string>) ?? {},
    outputSchema: (data.outputSchema as Record<string, string>) ?? {},
    requiredPermissions: (data.requiredPermissions as string[]) ?? [],
  };
}

/**
 * The return value of {@link MCPClient.execute}. There is no
 * `error`/`isSuccess` field — a failed call always rejects with an
 * {@link MCPException} instead of resolving with a "failed" result.
 */
export interface ExecutionResult {
  readonly data: Record<string, unknown>;
  readonly meta: Record<string, unknown>;
}
