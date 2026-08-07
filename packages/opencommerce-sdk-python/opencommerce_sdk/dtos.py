"""Typed, read-only value objects returned by :class:`MCPClient`.

Kept intentionally small — a plain ``dict`` is idiomatic Python for a
capability's own free-form ``input``/``output`` payload, so unlike the PHP
SDK's ``CapabilityInput``/``CapabilityOutput`` wrapper classes, this SDK
just uses ``dict[str, Any]`` directly for those. ``Capability`` and
``ExecutionResult`` stay typed because their own shape (name, schemas,
permissions / data, meta) is fixed and worth real attributes instead of
dict-key typos waiting to happen.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any


@dataclass(frozen=True)
class Capability:
    """Client-side mirror of one entry from ``GET /mcp/{version}/capabilities``."""

    name: str
    description: str
    input_schema: dict[str, str] = field(default_factory=dict)
    output_schema: dict[str, str] = field(default_factory=dict)
    required_permissions: list[str] = field(default_factory=list)

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> "Capability":
        return cls(
            name=data["name"],
            description=data.get("description", ""),
            input_schema=data.get("inputSchema", {}) or {},
            output_schema=data.get("outputSchema", {}) or {},
            required_permissions=data.get("requiredPermissions", []) or [],
        )


@dataclass(frozen=True)
class ExecutionResult:
    """The return value of :meth:`MCPClient.execute`.

    There is no ``error``/``is_success`` field the way a Result type in some
    other languages might have one: :meth:`MCPClient.execute` always raises
    an :class:`~opencommerce_sdk.exceptions.MCPException` on any HTTP-level
    failure rather than returning a "failed" result — Python's own
    "exceptions for errors" convention, not a gap relative to the other SDKs.
    """

    data: dict[str, Any]
    meta: dict[str, Any]

    @classmethod
    def from_response(cls, data: dict[str, Any], meta: dict[str, Any]) -> "ExecutionResult":
        return cls(data=data, meta=meta)
