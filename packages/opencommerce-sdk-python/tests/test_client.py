import unittest

from opencommerce_sdk import Capability, ExecutionResult, MCPClient, MCPConfig
from opencommerce_sdk.exceptions import AuthorizationException, NotFoundException, ValidationException

from .fakes import FakeTransport


def _client(transport: FakeTransport) -> MCPClient:
    config = MCPConfig(base_url="https://api.opencommerce.ir/mcp/v1", token="agent_token")
    return MCPClient(config, transport)


class DiscoverCapabilitiesTest(unittest.TestCase):
    def test_reads_the_v1_envelope_shape(self) -> None:
        transport = FakeTransport(
            200,
            {"data": {"capabilities": [{"name": "commerce.product.search", "description": "Search products."}]}, "meta": {}},
        )

        capabilities = _client(transport).discover_capabilities()

        self.assertEqual(1, len(capabilities))
        self.assertIsInstance(capabilities[0], Capability)
        self.assertEqual("commerce.product.search", capabilities[0].name)

    def test_reads_the_v2_envelope_shape(self) -> None:
        transport = FakeTransport(
            200,
            {"capabilities": [{"name": "commerce.product.search", "description": "Search products."}], "metadata": {}},
        )

        capabilities = _client(transport).discover_capabilities()

        self.assertEqual(1, len(capabilities))
        self.assertEqual("commerce.product.search", capabilities[0].name)

    def test_sends_the_bearer_token_header(self) -> None:
        transport = FakeTransport(200, {"data": {"capabilities": []}})

        _client(transport).discover_capabilities()

        self.assertEqual("Bearer agent_token", transport.calls[0]["headers"]["Authorization"])
        self.assertEqual("GET", transport.calls[0]["method"])

    def test_a_non_2xx_response_raises_the_mapped_exception(self) -> None:
        transport = FakeTransport(403, {"error": {"code": "FORBIDDEN", "message": "no access"}})

        with self.assertRaises(AuthorizationException):
            _client(transport).discover_capabilities()


class ExecuteTest(unittest.TestCase):
    def test_reads_the_v1_envelope_shape(self) -> None:
        transport = FakeTransport(200, {"data": {"echo": "hi"}, "meta": {"capability": "demo.tools.echo"}})

        result = _client(transport).execute("demo.tools.echo", {"message": "hi"})

        self.assertIsInstance(result, ExecutionResult)
        self.assertEqual({"echo": "hi"}, result.data)
        self.assertEqual({"capability": "demo.tools.echo"}, result.meta)

    def test_reads_the_v2_envelope_shape(self) -> None:
        transport = FakeTransport(
            200, {"result": {"echo": "hi"}, "metadata": {"api_version": "v2", "timestamp": "2026-01-01T00:00:00Z"}}
        )

        result = _client(transport).execute("demo.tools.echo", {"message": "hi"})

        self.assertEqual({"echo": "hi"}, result.data)
        self.assertEqual("v2", result.meta["api_version"])

    def test_sends_the_capability_name_and_input_as_json(self) -> None:
        transport = FakeTransport(200, {"data": {}, "meta": {}})

        _client(transport).execute("commerce.product.search", {"query": "laptop"})

        self.assertEqual("POST", transport.calls[0]["method"])
        self.assertEqual(
            {"capability": "commerce.product.search", "input": {"query": "laptop"}},
            transport.calls[0]["json_body"],
        )

    def test_omitted_input_defaults_to_an_empty_object(self) -> None:
        transport = FakeTransport(200, {"data": {}, "meta": {}})

        _client(transport).execute("demo.time.read")

        self.assertEqual({}, transport.calls[0]["json_body"]["input"])

    def test_a_validation_error_raises_validation_exception(self) -> None:
        transport = FakeTransport(422, {"error": {"code": "VALIDATION_ERROR", "message": "query is required"}})

        with self.assertRaises(ValidationException):
            _client(transport).execute("commerce.product.search", {})


class GetCapabilityTest(unittest.TestCase):
    def test_returns_the_matching_capability(self) -> None:
        transport = FakeTransport(
            200,
            {
                "data": {
                    "capabilities": [
                        {"name": "commerce.product.search", "description": "Search."},
                        {"name": "commerce.order.place", "description": "Place an order."},
                    ]
                }
            },
        )

        capability = _client(transport).get_capability("commerce.order.place")

        self.assertEqual("commerce.order.place", capability.name)

    def test_raises_not_found_when_no_capability_matches(self) -> None:
        transport = FakeTransport(200, {"data": {"capabilities": []}})

        with self.assertRaises(NotFoundException):
            _client(transport).get_capability("commerce.nonexistent.capability")


if __name__ == "__main__":
    unittest.main()
