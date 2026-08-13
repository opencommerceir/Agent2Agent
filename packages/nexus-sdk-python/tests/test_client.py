import hashlib
import hmac
import json
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from nexus_sdk import NexusApiError, NexusClient


class NexusClientTest(unittest.TestCase):
    def test_get_credit_balance_returns_data_payload(self):
        def transport(method, path, json_body):
            self.assertEqual(method, "GET")
            self.assertEqual(path, "/nexus/api/v1/credit/balance")
            return 200, json.dumps({"data": {"businessId": 1, "balance": 500}})

        client = NexusClient("https://nexus.example.com", "nx_test", transport)
        result = client.get_credit_balance()

        self.assertEqual(result["balance"], 500)

    def test_get_catalog_passes_query_parameter(self):
        captured = {}

        def transport(method, path, json_body):
            captured["path"] = path
            return 200, json.dumps({"data": {"products": [], "services": []}})

        client = NexusClient("https://nexus.example.com", "nx_test", transport)
        client.get_catalog("widget")

        self.assertEqual(captured["path"], "/nexus/api/v1/catalog?query=widget")

    def test_error_response_raises_nexus_api_error_with_envelope_details(self):
        def transport(method, path, json_body):
            return 403, json.dumps({"error": {"code": "FORBIDDEN", "message": "Missing scope: catalog.read"}})

        client = NexusClient("https://nexus.example.com", "nx_test", transport)

        with self.assertRaises(NexusApiError) as ctx:
            client.get_catalog()

        self.assertEqual(ctx.exception.http_status, 403)
        self.assertEqual(ctx.exception.error_code, "FORBIDDEN")
        self.assertEqual(str(ctx.exception), "Missing scope: catalog.read")

    def test_graphql_sends_query_and_variables_as_json_body(self):
        captured = {}

        def transport(method, path, json_body):
            captured["body"] = json_body
            return 200, json.dumps({"data": {"creditBalance": {"balance": 10}}})

        client = NexusClient("https://nexus.example.com", "nx_test", transport)
        client.graphql("{ creditBalance { balance } }", {"foo": "bar"})

        self.assertEqual(captured["body"]["query"], "{ creditBalance { balance } }")
        self.assertEqual(captured["body"]["variables"], {"foo": "bar"})

    def test_verify_webhook_signature_accepts_valid_signature(self):
        body = '{"event":"negotiation.accepted"}'
        secret = "shhh"
        signature = "sha256=" + hmac.new(secret.encode(), body.encode(), hashlib.sha256).hexdigest()

        self.assertTrue(NexusClient.verify_webhook_signature(body, signature, secret))

    def test_verify_webhook_signature_rejects_tampered_body(self):
        secret = "shhh"
        signature = "sha256=" + hmac.new(secret.encode(), b'{"event":"original"}', hashlib.sha256).hexdigest()

        self.assertFalse(NexusClient.verify_webhook_signature('{"event":"tampered"}', signature, secret))


if __name__ == "__main__":
    unittest.main()
