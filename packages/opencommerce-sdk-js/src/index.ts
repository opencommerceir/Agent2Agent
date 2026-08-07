/**
 * OpenCommerce Platform — Node.js / TypeScript SDK.
 *
 * A small, dependency-free client for the OpenCommerce Platform's MCP
 * Gateway — the layer that lets AI Agents (and any other JS/TS code: a
 * script, a Next.js API route, a LangChain.js tool, ...) discover and
 * execute business capabilities exposed by an OpenCommerce deployment,
 * whether self-hosted or OpenCommerce's own hosted infrastructure.
 */

export { MCPClient } from "./client.ts";
export { MCPConfig } from "./config.ts";
export type { MCPConfigOptions, MCPConfigForVersionOptions } from "./config.ts";
export type { Capability, ExecutionResult } from "./dtos.ts";
export {
  MCPException,
  AuthenticationException,
  AuthorizationException,
  NotFoundException,
  ValidationException,
  exceptionFromResponse,
} from "./exceptions.ts";
export type { MCPErrorBody } from "./exceptions.ts";
export { FetchTransport, AuthenticatedTransport } from "./transport.ts";
export type { Transport, TransportResponse } from "./transport.ts";

export const VERSION = "1.0.0";
