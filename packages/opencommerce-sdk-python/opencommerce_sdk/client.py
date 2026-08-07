"""The one class a developer needs to know about — the Python equivalent of
``packages/opencommerce-sdk/src/MCPClient.php``.

    from opencommerce_sdk import MCPClient, MCPConfig

    config = MCPConfig(base_url="http://localhost:8000/mcp/v1", token="agent_token")
    client = MCPClient(config)

    capabilities = client.discover_capabilities()
    result = client.execute("commerce.product.search", {"query": "laptop"})
    print(result.data)

Migrating to v2 (``docs/api/migration/v1-to-v2.md``) is a one-argument
change — construct ``MCPConfig`` with ``base_url`` pointing at ``/mcp/v2``
(or call ``MCPConfig.for_version(..., version="v2", ...)``) — nothing else
about this class changes, since v1/v2 only differ in the response envelope
shape, and :meth:`discover_capabilities`/:meth:`execute` already tolerate
both.
"""

from __future__ import annotations

from typing import Any, Optional

from .config import MCPConfig
from .dtos import Capability, ExecutionResult
from .exceptions import NotFoundException, exception_from_response
from .transport import AuthenticatedTransport, Transport


class MCPClient:
    def __init__(self, config: MCPConfig, transport: Optional[Transport] = None) -> None:
        """``transport`` is for tests only — production code should never
        pass one, the same way the PHP SDK's ``MCPClient`` never passes an
        injected Guzzle client to ``AuthenticatedRequest`` itself.
        """
        self._request = AuthenticatedTransport(config, transport)

    def discover_capabilities(self) -> list[Capability]:
        """Every capability this Agent's token can see.

        No caching — a cached list could go stale the moment a new
        capability is registered server-side; wrap ``MCPClient`` yourself
        if you want that trade-off.
        """
        status, body = self._request.get("capabilities")
        if not _is_success(status):
            raise exception_from_response(status, body)

        # v1 nests `capabilities` under `data`; v2 puts it at the top level
        # next to `metadata` — accept either, the same envelope-shape
        # tolerance `execute()` below applies to `result`/`data`.
        capabilities = (body.get("data") or {}).get("capabilities") or body.get("capabilities") or []
        return [Capability.from_dict(capability) for capability in capabilities]

    def execute(self, capability_name: str, input: Optional[dict[str, Any]] = None) -> ExecutionResult:
        """Run one capability. Raises a subclass of
        :class:`~opencommerce_sdk.exceptions.MCPException` on any non-2xx
        response — there is no "failed result" to check for separately.
        """
        status, body = self._request.post(
            "execute",
            {"capability": capability_name, "input": input or {}},
        )
        if not _is_success(status):
            raise exception_from_response(status, body)

        data = body["result"] if "result" in body else body.get("data", {})
        meta = body["metadata"] if "metadata" in body else body.get("meta", {})
        return ExecutionResult.from_response(data or {}, meta or {})

    def get_capability(self, capability_name: str) -> Capability:
        """Fetch one capability by name.

        There is no ``GET /mcp/{version}/capabilities/{name}`` endpoint on
        the server today — this fetches the full discovery list and
        filters client-side, exactly like the PHP SDK does.
        """
        for capability in self.discover_capabilities():
            if capability.name == capability_name:
                return capability

        raise NotFoundException("NOT_FOUND", f"Capability [{capability_name}] was not found.", 404)


def _is_success(status: int) -> bool:
    return 200 <= status < 300
