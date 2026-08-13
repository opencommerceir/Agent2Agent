"""Official Python client for the Nexus Public REST API.

Zero third-party dependencies — built on ``urllib.request`` (standard
library) rather than ``requests``, the same "don't force a dependency
choice on the consumer" reasoning the PHP SDK (packages/nexus-sdk-php,
plain curl) and the Node SDK (packages/nexus-sdk-node, built-in fetch)
already apply.
"""

from __future__ import annotations

import hashlib
import hmac
import json
import urllib.error
import urllib.parse
import urllib.request
from typing import Any, Callable, Optional

__all__ = ["NexusClient", "NexusApiError"]


class NexusApiError(Exception):
    """Raised for any non-2xx response — carries the Nexus error
    envelope's own ``code``/``message`` fields (see /nexus/docs) rather
    than a generic HTTP-status-only message."""

    def __init__(self, http_status: int, error_code: str, message: str) -> None:
        super().__init__(message)
        self.http_status = http_status
        self.error_code = error_code


# A transport is (method, path, json_body) -> (status, body_text). The
# default uses urllib; tests inject a fake to avoid real network calls —
# the same "injectable transport" shape the PHP/Node SDKs already use.
Transport = Callable[[str, str, Optional[dict]], tuple[int, str]]


class NexusClient:
    def __init__(self, base_url: str, api_key: str, transport: Optional[Transport] = None) -> None:
        self._base_url = base_url.rstrip("/")
        self._api_key = api_key
        self._transport = transport or self._urllib_transport

    def get_business_profile(self) -> dict[str, Any]:
        return self._get("business")

    def get_catalog(self, query: Optional[str] = None) -> dict[str, Any]:
        return self._get("catalog", {"query": query} if query else {})

    def search_marketplace(self, query: Optional[str] = None, industry: Optional[str] = None) -> dict[str, Any]:
        params = {k: v for k, v in {"query": query, "industry": industry}.items() if v is not None}
        return self._get("marketplace/search", params)

    def get_negotiation(self, negotiation_id: int) -> dict[str, Any]:
        return self._get(f"negotiations/{negotiation_id}")

    def get_credit_balance(self) -> dict[str, Any]:
        return self._get("credit/balance")

    def graphql(self, query: str, variables: Optional[dict] = None) -> dict[str, Any]:
        return self._request("POST", "/nexus/api/v1/graphql", {"query": query, "variables": variables or {}})

    def _get(self, path: str, query: Optional[dict] = None) -> dict[str, Any]:
        suffix = f"?{urllib.parse.urlencode(query)}" if query else ""
        result = self._request("GET", f"/nexus/api/v1/{path}{suffix}")
        return result.get("data", result)

    def _request(self, method: str, path: str, json_body: Optional[dict] = None) -> dict[str, Any]:
        status, body = self._transport(method, path, json_body)
        decoded = json.loads(body) if body else {}

        if status >= 400:
            error = decoded.get("error", {"code": "UNKNOWN", "message": "Request failed."})
            raise NexusApiError(status, error.get("code", "UNKNOWN"), error.get("message", "Request failed."))

        return decoded

    def _urllib_transport(self, method: str, path: str, json_body: Optional[dict]) -> tuple[int, str]:
        url = f"{self._base_url}{path}"
        data = json.dumps(json_body).encode("utf-8") if json_body is not None else None
        headers = {"Authorization": f"Bearer {self._api_key}", "Accept": "application/json"}
        if data is not None:
            headers["Content-Type"] = "application/json"

        request = urllib.request.Request(url, data=data, headers=headers, method=method)

        try:
            with urllib.request.urlopen(request, timeout=15) as response:
                return response.status, response.read().decode("utf-8")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8")

    @staticmethod
    def verify_webhook_signature(raw_body: bytes | str, signature_header: str, webhook_secret: str) -> bool:
        """Verifies a webhook delivery's X-Nexus-Signature header —
        timing-safe comparison (hmac.compare_digest), the same discipline
        the PHP/Node SDKs' hash_equals()/timingSafeEqual() already use."""
        if isinstance(raw_body, str):
            raw_body = raw_body.encode("utf-8")

        expected = "sha256=" + hmac.new(webhook_secret.encode("utf-8"), raw_body, hashlib.sha256).hexdigest()

        return hmac.compare_digest(expected, signature_header or "")
