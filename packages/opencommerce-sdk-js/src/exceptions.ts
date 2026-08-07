/**
 * Every error this SDK throws, and the single place an MCP Gateway HTTP
 * response is turned into the right one.
 *
 * Mirrors `packages/opencommerce-sdk/src/Exceptions/*.php`: one base type
 * carrying the server's own `error.code`/`error.message`/HTTP status, plus
 * four narrower subclasses for the four statuses worth catching separately
 * (401/403/404/422). Anything else (429, 500, ...) stays the base
 * `MCPException`.
 */

export interface MCPErrorBody {
  error?: {
    code?: string;
    message?: string;
  };
  [key: string]: unknown;
}

export class MCPException extends Error {
  readonly errorCode: string;
  readonly statusCode: number;

  constructor(errorCode: string, message: string, statusCode: number) {
    super(message);
    this.name = "MCPException";
    this.errorCode = errorCode;
    this.statusCode = statusCode;
    // Restores the prototype chain — needed because extending built-ins
    // like Error is not fully reliable across every JS runtime/target
    // without this, even though ES2022+ generally handles it correctly.
    Object.setPrototypeOf(this, new.target.prototype);
  }
}

/** HTTP 401 — the bearer token is missing, malformed, or invalid. */
export class AuthenticationException extends MCPException {
  constructor(errorCode: string, message: string, statusCode: number) {
    super(errorCode, message, statusCode);
    this.name = "AuthenticationException";
    Object.setPrototypeOf(this, AuthenticationException.prototype);
  }
}

/** HTTP 403 — the token is valid, but this Agent lacks the required permission. */
export class AuthorizationException extends MCPException {
  constructor(errorCode: string, message: string, statusCode: number) {
    super(errorCode, message, statusCode);
    this.name = "AuthorizationException";
    Object.setPrototypeOf(this, AuthorizationException.prototype);
  }
}

/** HTTP 404 — the capability, or the resource it operates on, doesn't exist. */
export class NotFoundException extends MCPException {
  constructor(errorCode: string, message: string, statusCode: number) {
    super(errorCode, message, statusCode);
    this.name = "NotFoundException";
    Object.setPrototypeOf(this, NotFoundException.prototype);
  }
}

/** HTTP 422 — the request's own `input` failed the capability's input schema. */
export class ValidationException extends MCPException {
  constructor(errorCode: string, message: string, statusCode: number) {
    super(errorCode, message, statusCode);
    this.name = "ValidationException";
    Object.setPrototypeOf(this, ValidationException.prototype);
  }
}

/** Builds the right exception instance from a non-2xx MCP Gateway response. */
export function exceptionFromResponse(status: number, body: MCPErrorBody): MCPException {
  const errorCode = body?.error?.code ?? "UNKNOWN_ERROR";
  const message = body?.error?.message ?? `MCP request failed with HTTP ${status}.`;

  switch (status) {
    case 401:
      return new AuthenticationException(errorCode, message, status);
    case 403:
      return new AuthorizationException(errorCode, message, status);
    case 404:
      return new NotFoundException(errorCode, message, status);
    case 422:
      return new ValidationException(errorCode, message, status);
    default:
      return new MCPException(errorCode, message, status);
  }
}
