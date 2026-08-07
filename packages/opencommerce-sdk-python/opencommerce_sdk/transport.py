"""The HTTP layer, kept behind a small :class:`Transport` protocol so tests
never touch a real socket — the exact role
``packages/opencommerce-sdk/src/Authentication/AuthenticatedRequest.php``
plays in the PHP SDK, with an injectable client for that same reason.

Deliberately built on Python's own standard library
(``urllib.request``/``json``, no ``requests``/``httpx`` dependency) so
installing this SDK never pulls in a third-party HTTP library a consuming
project might already pin a different version of — a real, considered
trade-off (a few more lines here) for zero dependency-resolution risk for
every downstream project, not an oversight.
"""

from __future__ import annotations

import json
import ssl
import urllib.error
import urllib.request
from typing import Any, Optional, Protocol

from .config import MCPConfig


class Transport(Protocol):
    """Anything that can perform one HTTP request and return ``(status, body)``.

    ``body`` is always a ``dict`` — an empty one if the response had no
    parseable JSON body at all (e.g. a 204, or a proxy error page).
    """

    def request(
        self,
        method: str,
        url: str,
        headers: dict[str, str],
        json_body: Optional[dict[str, Any]] = None,
        timeout: int = 30,
    ) -> tuple[int, dict[str, Any]]:
        ...


class UrllibTransport:
    """The real, default :class:`Transport` — plain ``urllib.request``."""

    def __init__(self, verify_ssl: bool = True) -> None:
        self._ssl_context = None if verify_ssl else ssl._create_unverified_context()

    def request(
        self,
        method: str,
        url: str,
        headers: dict[str, str],
        json_body: Optional[dict[str, Any]] = None,
        timeout: int = 30,
    ) -> tuple[int, dict[str, Any]]:
        data: Optional[bytes] = None
        request_headers = dict(headers)
        if json_body is not None:
            data = json.dumps(json_body).encode("utf-8")
            request_headers["Content-Type"] = "application/json"

        request = urllib.request.Request(url, data=data, headers=request_headers, method=method)

        try:
            with urllib.request.urlopen(request, timeout=timeout, context=self._ssl_context) as response:
                status = response.getcode()
                raw_body = response.read()
        except urllib.error.HTTPError as error:
            # MCP Gateway's own error envelope is only readable from here —
            # urllib raises on any 4xx/5xx instead of just returning it, the
            # opposite of Guzzle's `http_errors => false` the PHP SDK relies
            # on, so every non-2xx response is caught and treated the same
            # way a normal response would be.
            status = error.code
            raw_body = error.read()

        return status, _decode_json_object(raw_body)


def _decode_json_object(raw_body: bytes) -> dict[str, Any]:
    if not raw_body:
        return {}

    try:
        decoded = json.loads(raw_body)
    except (json.JSONDecodeError, UnicodeDecodeError):
        return {}

    return decoded if isinstance(decoded, dict) else {}


class AuthenticatedTransport:
    """Joins ``MCPConfig.base_url`` to a path and attaches the bearer token
    header, so :class:`~opencommerce_sdk.client.MCPClient` never builds a
    URL or a header dict itself.
    """

    def __init__(self, config: MCPConfig, transport: Optional[Transport] = None) -> None:
        self._config = config
        self._transport = transport or UrllibTransport(verify_ssl=config.verify_ssl)

    def get(self, path: str) -> tuple[int, dict[str, Any]]:
        return self._request("GET", path)

    def post(self, path: str, json_body: dict[str, Any]) -> tuple[int, dict[str, Any]]:
        return self._request("POST", path, json_body)

    def _request(
        self, method: str, path: str, json_body: Optional[dict[str, Any]] = None
    ) -> tuple[int, dict[str, Any]]:
        url = f"{self._config.base_url.rstrip('/')}/{path.lstrip('/')}"
        headers = {"Authorization": f"Bearer {self._config.token}"}
        return self._transport.request(method, url, headers, json_body, timeout=self._config.timeout)
