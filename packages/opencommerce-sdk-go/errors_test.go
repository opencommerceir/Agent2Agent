package opencommerce_test

import (
	"errors"
	"strings"
	"testing"

	opencommerce "opencommerce-sdk-go"
)

func errorBody(code, message string) map[string]interface{} {
	return map[string]interface{}{"error": map[string]interface{}{"code": code, "message": message}}
}

func TestErrorFromResponse401MapsToAuthenticationError(t *testing.T) {
	err := opencommerce.ErrorFromResponse(401, errorBody("UNAUTHORIZED", "no token"))

	var target *opencommerce.AuthenticationError
	if !errors.As(err, &target) {
		t.Fatalf("expected *AuthenticationError, got %T", err)
	}
	if target.ErrorCode != "UNAUTHORIZED" || target.Message != "no token" || target.StatusCode != 401 {
		t.Fatalf("unexpected fields: %+v", target.MCPError)
	}
}

func TestErrorFromResponse403MapsToAuthorizationError(t *testing.T) {
	err := opencommerce.ErrorFromResponse(403, errorBody("FORBIDDEN", "missing permission"))

	var target *opencommerce.AuthorizationError
	if !errors.As(err, &target) {
		t.Fatalf("expected *AuthorizationError, got %T", err)
	}
}

func TestErrorFromResponse404MapsToNotFoundError(t *testing.T) {
	err := opencommerce.ErrorFromResponse(404, errorBody("NOT_FOUND", "Order not found"))

	var target *opencommerce.NotFoundError
	if !errors.As(err, &target) {
		t.Fatalf("expected *NotFoundError, got %T", err)
	}
}

func TestErrorFromResponse422MapsToValidationError(t *testing.T) {
	err := opencommerce.ErrorFromResponse(422, errorBody("VALIDATION_ERROR", "bad input"))

	var target *opencommerce.ValidationError
	if !errors.As(err, &target) {
		t.Fatalf("expected *ValidationError, got %T", err)
	}
}

func TestErrorFromResponseUnmappedStatusFallsBackToMCPError(t *testing.T) {
	err := opencommerce.ErrorFromResponse(500, errorBody("INTERNAL_ERROR", "boom"))

	mcpErr, ok := err.(*opencommerce.MCPError)
	if !ok {
		t.Fatalf("expected *MCPError, got %T", err)
	}
	if mcpErr.StatusCode != 500 {
		t.Fatalf("got StatusCode %d", mcpErr.StatusCode)
	}
}

func TestErrorFromResponse429AlsoFallsBackToMCPError(t *testing.T) {
	err := opencommerce.ErrorFromResponse(429, errorBody("TOO_MANY_REQUESTS", "slow down"))

	if _, ok := err.(*opencommerce.MCPError); !ok {
		t.Fatalf("expected *MCPError, got %T", err)
	}
}

func TestErrorFromResponseMissingErrorEnvelopeGetsSensibleDefaults(t *testing.T) {
	err := opencommerce.ErrorFromResponse(500, map[string]interface{}{})

	mcpErr := err.(*opencommerce.MCPError)
	if mcpErr.ErrorCode != "UNKNOWN_ERROR" {
		t.Fatalf("got ErrorCode %q", mcpErr.ErrorCode)
	}
	if !strings.Contains(mcpErr.Message, "500") {
		t.Fatalf("expected message to mention the status, got %q", mcpErr.Message)
	}
}

func TestEveryNarrowerErrorSatisfiesTheStandardErrorInterface(t *testing.T) {
	err := opencommerce.ErrorFromResponse(404, errorBody("NOT_FOUND", "x"))

	if err.Error() != "x" {
		t.Fatalf("got %q", err.Error())
	}
}
