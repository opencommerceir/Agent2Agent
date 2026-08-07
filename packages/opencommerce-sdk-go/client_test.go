package opencommerce_test

import (
	"context"
	"errors"
	"testing"

	opencommerce "github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go"
)

func testClient(transport *fakeTransport) *opencommerce.Client {
	config := opencommerce.NewConfig("https://api.opencommerce.ir/mcp/v1", "agent_token")
	return opencommerce.NewClientWithTransport(config, transport)
}

func TestDiscoverCapabilitiesReadsTheV1EnvelopeShape(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{
		"data": map[string]interface{}{
			"capabilities": []interface{}{
				map[string]interface{}{"name": "commerce.product.search", "description": "Search products."},
			},
		},
		"meta": map[string]interface{}{},
	})

	capabilities, err := testClient(transport).DiscoverCapabilities(context.Background())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if len(capabilities) != 1 || capabilities[0].Name != "commerce.product.search" {
		t.Fatalf("got %+v", capabilities)
	}
}

func TestDiscoverCapabilitiesReadsTheV2EnvelopeShape(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{
		"capabilities": []interface{}{
			map[string]interface{}{"name": "commerce.product.search", "description": "Search products."},
		},
		"metadata": map[string]interface{}{},
	})

	capabilities, err := testClient(transport).DiscoverCapabilities(context.Background())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if len(capabilities) != 1 || capabilities[0].Name != "commerce.product.search" {
		t.Fatalf("got %+v", capabilities)
	}
}

func TestDiscoverCapabilitiesSendsTheBearerTokenHeader(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{"data": map[string]interface{}{"capabilities": []interface{}{}}})

	if _, err := testClient(transport).DiscoverCapabilities(context.Background()); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if got := transport.calls[0].headers["Authorization"]; got != "Bearer agent_token" {
		t.Fatalf("got Authorization header %q", got)
	}
	if transport.calls[0].method != "GET" {
		t.Fatalf("got method %q", transport.calls[0].method)
	}
}

func TestDiscoverCapabilitiesReturnsTheMappedErrorOnANon2xxResponse(t *testing.T) {
	transport := newFakeTransport(403, map[string]interface{}{
		"error": map[string]interface{}{"code": "FORBIDDEN", "message": "no access"},
	})

	_, err := testClient(transport).DiscoverCapabilities(context.Background())

	var target *opencommerce.AuthorizationError
	if !errors.As(err, &target) {
		t.Fatalf("expected *AuthorizationError, got %T (%v)", err, err)
	}
}

func TestExecuteReadsTheV1EnvelopeShape(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{
		"data": map[string]interface{}{"echo": "hi"},
		"meta": map[string]interface{}{"capability": "demo.tools.echo"},
	})

	result, err := testClient(transport).Execute(context.Background(), "demo.tools.echo", map[string]interface{}{"message": "hi"})
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if result.Data["echo"] != "hi" {
		t.Fatalf("got Data %+v", result.Data)
	}
	if result.Meta["capability"] != "demo.tools.echo" {
		t.Fatalf("got Meta %+v", result.Meta)
	}
}

func TestExecuteReadsTheV2EnvelopeShape(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{
		"result":   map[string]interface{}{"echo": "hi"},
		"metadata": map[string]interface{}{"api_version": "v2"},
	})

	result, err := testClient(transport).Execute(context.Background(), "demo.tools.echo", map[string]interface{}{"message": "hi"})
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if result.Data["echo"] != "hi" {
		t.Fatalf("got Data %+v", result.Data)
	}
	if result.Meta["api_version"] != "v2" {
		t.Fatalf("got Meta %+v", result.Meta)
	}
}

func TestExecuteSendsTheCapabilityNameAndInputAsJSON(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{"data": map[string]interface{}{}, "meta": map[string]interface{}{}})

	_, err := testClient(transport).Execute(context.Background(), "commerce.product.search", map[string]interface{}{"query": "laptop"})
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if transport.calls[0].method != "POST" {
		t.Fatalf("got method %q", transport.calls[0].method)
	}
	if transport.calls[0].json["capability"] != "commerce.product.search" {
		t.Fatalf("got json %+v", transport.calls[0].json)
	}
}

func TestExecuteDefaultsNilInputToAnEmptyObject(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{"data": map[string]interface{}{}, "meta": map[string]interface{}{}})

	_, err := testClient(transport).Execute(context.Background(), "demo.time.read", nil)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	input, ok := transport.calls[0].json["input"].(map[string]interface{})
	if !ok || len(input) != 0 {
		t.Fatalf("got input %+v", transport.calls[0].json["input"])
	}
}

func TestExecuteReturnsValidationErrorOnA422(t *testing.T) {
	transport := newFakeTransport(422, map[string]interface{}{
		"error": map[string]interface{}{"code": "VALIDATION_ERROR", "message": "query is required"},
	})

	_, err := testClient(transport).Execute(context.Background(), "commerce.product.search", map[string]interface{}{})

	var target *opencommerce.ValidationError
	if !errors.As(err, &target) {
		t.Fatalf("expected *ValidationError, got %T (%v)", err, err)
	}
}

func TestGetCapabilityReturnsTheMatchingCapability(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{
		"data": map[string]interface{}{
			"capabilities": []interface{}{
				map[string]interface{}{"name": "commerce.product.search", "description": "Search."},
				map[string]interface{}{"name": "commerce.order.place", "description": "Place an order."},
			},
		},
	})

	capability, err := testClient(transport).GetCapability(context.Background(), "commerce.order.place")
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if capability.Name != "commerce.order.place" {
		t.Fatalf("got %+v", capability)
	}
}

func TestGetCapabilityReturnsNotFoundErrorWhenNothingMatches(t *testing.T) {
	transport := newFakeTransport(200, map[string]interface{}{"data": map[string]interface{}{"capabilities": []interface{}{}}})

	_, err := testClient(transport).GetCapability(context.Background(), "commerce.nonexistent.capability")

	var target *opencommerce.NotFoundError
	if !errors.As(err, &target) {
		t.Fatalf("expected *NotFoundError, got %T (%v)", err, err)
	}
}
