#!/usr/bin/env python3
"""A minimal, standalone AI Agent script — proof that a plain Python
script can discover and execute OpenCommerce capabilities using nothing
but `pip install opencommerce-sdk` (or, inside this monorepo,
`packages/opencommerce-sdk-python` on your PYTHONPATH).

Prerequisites:
  1. `php artisan serve` running this app (default: http://localhost:8000)
  2. An Agent token — generate one via GenerateAgentTokenAction, or see
     the "Quick Start" section of packages/opencommerce-sdk/README.md
     for a copy-pasteable Tinker snippet that creates a Tenant, Org,
     Agent, grants the three demo.* permissions, and prints a token.

Usage:
  python examples/sample-agent.py <token> [base-url]
"""

from __future__ import annotations

import sys

from opencommerce_sdk import MCPClient, MCPConfig
from opencommerce_sdk.exceptions import MCPException


def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: python examples/sample-agent.py <token> [base-url]", file=sys.stderr)
        return 1

    token = sys.argv[1]
    base_url = sys.argv[2] if len(sys.argv) > 2 else "http://localhost:8000/mcp/v1"

    config = MCPConfig(base_url=base_url, token=token)
    client = MCPClient(config)

    print("=== Available Capabilities ===")
    try:
        for capability in client.discover_capabilities():
            print(f"- {capability.name}: {capability.description}")
    except MCPException as exc:
        print(f"Discovery failed: [{exc.error_code}] {exc}", file=sys.stderr)
        return 1

    print("\n=== demo.tools.echo ===")
    try:
        result = client.execute("demo.tools.echo", {"message": "Hello from AI Agent!"})
        print(result.data)
    except MCPException as exc:
        print(f"demo.tools.echo failed: [{exc.error_code}] {exc}", file=sys.stderr)

    print("\n=== demo.tools.time ===")
    try:
        result = client.execute("demo.tools.time")
        print(result.data)
    except MCPException as exc:
        print(f"demo.tools.time failed: [{exc.error_code}] {exc}", file=sys.stderr)

    print("\n=== demo.tools.calculator ===")
    try:
        result = client.execute("demo.tools.calculator", {"operation": "multiply", "a": 42, "b": 10})
        print(result.data)
    except MCPException as exc:
        print(f"demo.tools.calculator failed: [{exc.error_code}] {exc}", file=sys.stderr)

    print("\n=== Negative test: unknown capability ===")
    try:
        # Well-formed (domain.resource.action) but genuinely unregistered —
        # a malformed name like "demo.nonexistent" would fail format
        # validation (VALIDATION_ERROR) before ever reaching the "does this
        # exist" check this test is meant to demonstrate (NOT_FOUND).
        client.execute("demo.tools.nonexistent", {})
    except MCPException as exc:
        print(f"Correctly rejected: [{exc.error_code}] {exc}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
