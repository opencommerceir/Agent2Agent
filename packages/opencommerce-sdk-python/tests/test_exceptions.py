import unittest

from opencommerce_sdk.exceptions import (
    AuthenticationException,
    AuthorizationException,
    MCPException,
    NotFoundException,
    ValidationException,
    exception_from_response,
)


class ExceptionFromResponseTest(unittest.TestCase):
    def test_401_maps_to_authentication_exception(self) -> None:
        exc = exception_from_response(401, {"error": {"code": "UNAUTHORIZED", "message": "no token"}})

        self.assertIsInstance(exc, AuthenticationException)
        self.assertEqual("UNAUTHORIZED", exc.error_code)
        self.assertEqual("no token", str(exc))
        self.assertEqual(401, exc.status_code)

    def test_403_maps_to_authorization_exception(self) -> None:
        exc = exception_from_response(403, {"error": {"code": "FORBIDDEN", "message": "missing permission"}})

        self.assertIsInstance(exc, AuthorizationException)

    def test_404_maps_to_not_found_exception(self) -> None:
        exc = exception_from_response(404, {"error": {"code": "NOT_FOUND", "message": "Order not found"}})

        self.assertIsInstance(exc, NotFoundException)

    def test_422_maps_to_validation_exception(self) -> None:
        exc = exception_from_response(422, {"error": {"code": "VALIDATION_ERROR", "message": "bad input"}})

        self.assertIsInstance(exc, ValidationException)

    def test_unmapped_status_falls_back_to_the_base_exception(self) -> None:
        exc = exception_from_response(500, {"error": {"code": "INTERNAL_ERROR", "message": "boom"}})

        self.assertIs(type(exc), MCPException)
        self.assertEqual(500, exc.status_code)

    def test_429_also_falls_back_to_the_base_exception(self) -> None:
        exc = exception_from_response(429, {"error": {"code": "TOO_MANY_REQUESTS", "message": "slow down"}})

        self.assertIs(type(exc), MCPException)

    def test_missing_error_envelope_gets_sensible_defaults(self) -> None:
        exc = exception_from_response(500, {})

        self.assertEqual("UNKNOWN_ERROR", exc.error_code)
        self.assertIn("500", str(exc))

    def test_every_subclass_is_still_an_mcp_exception(self) -> None:
        exc = exception_from_response(404, {"error": {"code": "NOT_FOUND", "message": "x"}})

        self.assertIsInstance(exc, MCPException)


if __name__ == "__main__":
    unittest.main()
