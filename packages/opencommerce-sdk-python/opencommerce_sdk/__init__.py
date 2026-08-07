"""OpenCommerce Platform — Python SDK.

A small, dependency-free Python client for the OpenCommerce Platform's
MCP Gateway — the layer that lets AI Agents (and any other Python code)
discover and execute business capabilities exposed by an OpenCommerce
deployment, whether self-hosted or OpenCommerce's own hosted
infrastructure.
"""

from .client import MCPClient
from .config import MCPConfig
from .dtos import Capability, ExecutionResult
from .exceptions import (
    AuthenticationException,
    AuthorizationException,
    MCPException,
    NotFoundException,
    ValidationException,
)

__version__ = "1.0.0"

__all__ = [
    "MCPClient",
    "MCPConfig",
    "Capability",
    "ExecutionResult",
    "MCPException",
    "AuthenticationException",
    "AuthorizationException",
    "NotFoundException",
    "ValidationException",
]
