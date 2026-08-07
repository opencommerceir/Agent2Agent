package opencommerce

import "strconv"

// MCPError is the base type every error this SDK returns satisfies (either
// directly, or through one of the four narrower types below, each of
// which embeds one). Mirrors
// packages/opencommerce-sdk/src/Exceptions/MCPException.php: the server's
// own error.code / error.message / HTTP status, carried through untouched.
type MCPError struct {
	ErrorCode  string
	Message    string
	StatusCode int
}

func (e *MCPError) Error() string {
	return e.Message
}

// AuthenticationError — HTTP 401: the bearer token is missing, malformed,
// or invalid.
type AuthenticationError struct{ *MCPError }

// AuthorizationError — HTTP 403: the token is valid, but this Agent lacks
// the required permission.
type AuthorizationError struct{ *MCPError }

// NotFoundError — HTTP 404: the capability, or the resource it operates
// on, doesn't exist.
type NotFoundError struct{ *MCPError }

// ValidationError — HTTP 422: the request's own input failed the
// capability's input schema.
type ValidationError struct{ *MCPError }

// ErrorFromResponse builds the right error type from a non-2xx MCP
// Gateway response — exported so a caller supplying their own Transport
// can reuse the identical mapping.
//
// Every returned value also satisfies error via the embedded *MCPError,
// so `errors.As(err, &target)` (with target one of the four narrower
// types above) is the idiomatic way to branch on a specific status,
// exactly the role instanceof/isinstance plays in the other SDKs.
func ErrorFromResponse(status int, body map[string]interface{}) error {
	errorCode := "UNKNOWN_ERROR"
	message := unknownErrorMessage(status)

	if errObj, ok := body["error"].(map[string]interface{}); ok {
		if code := stringField(errObj, "code"); code != "" {
			errorCode = code
		}
		if msg := stringField(errObj, "message"); msg != "" {
			message = msg
		}
	}

	base := &MCPError{ErrorCode: errorCode, Message: message, StatusCode: status}

	switch status {
	case 401:
		return &AuthenticationError{base}
	case 403:
		return &AuthorizationError{base}
	case 404:
		return &NotFoundError{base}
	case 422:
		return &ValidationError{base}
	default:
		return base
	}
}

func unknownErrorMessage(status int) string {
	return "MCP request failed with HTTP " + strconv.Itoa(status) + "."
}
