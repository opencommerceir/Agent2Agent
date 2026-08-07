"""Immutable connection settings for a single :class:`MCPClient` instance.

Mirrors the PHP SDK's own ``MCPConfig`` (``packages/opencommerce-sdk/src/Config/MCPConfig.php``)
field for field, so a developer moving between the two SDKs finds the same
shape. ``base_url`` already carries the wire version in its own path
(``https://api.opencommerce.ir/mcp/v1``, ``.../mcp/v2``, ...) — a consumer
picks a version simply by pointing at a different ``base_url``, the same
explicit, no-hidden-behavior approach the server's own version detection
uses (an explicit URL always wins there too). ``for_version()`` is purely
additive sugar for building that URL correctly.
"""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class MCPConfig:
    base_url: str
    token: str
    timeout: int = 30
    verify_ssl: bool = True

    @classmethod
    def for_version(
        cls,
        host: str,
        version: str,
        token: str,
        timeout: int = 30,
        verify_ssl: bool = True,
    ) -> "MCPConfig":
        """Build ``base_url`` as ``{host}/mcp/{version}`` for you.

        >>> MCPConfig.for_version(host="https://api.opencommerce.ir", version="v2", token="agent_token").base_url
        'https://api.opencommerce.ir/mcp/v2'
        """
        return cls(
            base_url=f"{host.rstrip('/')}/mcp/{version}",
            token=token,
            timeout=timeout,
            verify_ssl=verify_ssl,
        )
