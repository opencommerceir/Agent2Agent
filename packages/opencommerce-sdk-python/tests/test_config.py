import unittest

from opencommerce_sdk import MCPConfig


class MCPConfigTest(unittest.TestCase):
    def test_constructor_still_accepts_a_fully_qualified_base_url_directly(self) -> None:
        config = MCPConfig(base_url="https://api.opencommerce.ir/mcp/v1", token="agent_token")

        self.assertEqual("https://api.opencommerce.ir/mcp/v1", config.base_url)

    def test_for_version_builds_the_base_url_from_host_and_version(self) -> None:
        config = MCPConfig.for_version(host="https://api.opencommerce.ir", version="v2", token="agent_token")

        self.assertEqual("https://api.opencommerce.ir/mcp/v2", config.base_url)
        self.assertEqual("agent_token", config.token)

    def test_for_version_trims_a_trailing_slash_from_the_host(self) -> None:
        config = MCPConfig.for_version(host="https://api.opencommerce.ir/", version="v1", token="agent_token")

        self.assertEqual("https://api.opencommerce.ir/mcp/v1", config.base_url)

    def test_for_version_passes_through_timeout_and_verify_ssl(self) -> None:
        config = MCPConfig.for_version(
            host="https://api.opencommerce.ir",
            version="v2",
            token="agent_token",
            timeout=5,
            verify_ssl=False,
        )

        self.assertEqual(5, config.timeout)
        self.assertFalse(config.verify_ssl)

    def test_defaults_match_the_php_sdk(self) -> None:
        config = MCPConfig(base_url="https://api.opencommerce.ir/mcp/v1", token="agent_token")

        self.assertEqual(30, config.timeout)
        self.assertTrue(config.verify_ssl)


if __name__ == "__main__":
    unittest.main()
