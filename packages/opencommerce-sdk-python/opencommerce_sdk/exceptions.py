"""Every error this SDK raises, and the single place an MCP Gateway HTTP
response is turned into the right one.

Mirrors ``packages/opencommerce-sdk/src/Exceptions/*.php``: one base type
carrying the server's own ``error.code``/``error.message``/HTTP status, plus
four narrower subclasses for the four statuses worth catching separately
(401/403/404/422). Anything else (429, 500, ...) stays the base
``MCPException`` — a caller that only wants "did this fail" can always catch
that one type; a caller that wants to branch on a specific known status can
catch a subclass instead.
"""

from __future__ import annotations

from typing import Any


class MCPException(Exception):
    """Base type for every error the SDK raises."""

    def __init__(self, error_code: str, message: str, status_code: int) -> None:
        super().__init__(message)
        self.error_code = error_code
        self.status_code = status_code

    def __repr__(self) -> str:  # pragma: no cover - cosmetic only
        return f"{type(self).__name__}(error_code={self.error_code!r}, status_code={self.status_code!r})"


class AuthenticationException(MCPException):
    """HTTP 401 — the bearer token is missing, malformed, or invalid."""


class AuthorizationException(MCPException):
    """HTTP 403 — the token is valid, but this Agent lacks the required permission."""


class NotFoundException(MCPException):
    """HTTP 404 — the capability, or the resource it operates on, doesn't exist."""


class ValidationException(MCPException):
    """HTTP 422 — the request's own ``input`` failed the capability's input schema."""


_STATUS_TO_EXCEPTION: dict[int, type[MCPException]] = {
    401: AuthenticationException,
    403: AuthorizationException,
    404: NotFoundException,
    422: ValidationException,
}


def exception_from_response(status: int, body: dict[str, Any]) -> MCPException:
    """Build the right exception instance from a non-2xx MCP Gateway response."""
    error = body.get("error") or {}
    error_code = error.get("code", "UNKNOWN_ERROR")
    message = error.get("message", f"MCP request failed with HTTP {status}.")

    exception_class = _STATUS_TO_EXCEPTION.get(status, MCPException)
    return exception_class(error_code, message, status)
