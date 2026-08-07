"""A canned-response fake for :class:`opencommerce_sdk.transport.Transport`
— this SDK's equivalent of the PHP SDK's own Guzzle ``MockHandler`` usage.
No test in this suite ever touches a real socket.
"""

from __future__ import annotations

from typing import Any, Optional


class FakeTransport:
    def __init__(self, status: int, body: dict[str, Any]) -> None:
        self.status = status
        self.body = body
        self.calls: list[dict[str, Any]] = []

    def request(
        self,
        method: str,
        url: str,
        headers: dict[str, str],
        json_body: Optional[dict[str, Any]] = None,
        timeout: int = 30,
    ) -> tuple[int, dict[str, Any]]:
        self.calls.append(
            {"method": method, "url": url, "headers": headers, "json_body": json_body, "timeout": timeout}
        )
        return self.status, self.body
