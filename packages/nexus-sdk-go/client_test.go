package nexus

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

// NOTE: written but not executed — see client.go's package doc comment
// for why (no Go toolchain in the environment this codebase was built
// in). Uses httptest.NewServer, the standard library's own real-local-
// server approach to testing an HTTP client, so it needs no mocking
// framework beyond net/http/httptest itself.

func TestGetCreditBalance_ReturnsDataPayload(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/nexus/api/v1/credit/balance" {
			t.Errorf("unexpected path: %s", r.URL.Path)
		}
		if r.Header.Get("Authorization") != "Bearer nx_test" {
			t.Errorf("unexpected Authorization header: %s", r.Header.Get("Authorization"))
		}
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]any{"data": map[string]any{"businessId": 1, "balance": 500}})
	}))
	defer server.Close()

	client := New(server.URL, "nx_test")
	result, err := client.GetCreditBalance()
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if result["balance"] != float64(500) {
		t.Errorf("expected balance 500, got %v", result["balance"])
	}
}

func TestGetCatalog_PassesQueryParameter(t *testing.T) {
	var capturedQuery string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		capturedQuery = r.URL.RawQuery
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]any{"data": map[string]any{"products": []any{}, "services": []any{}}})
	}))
	defer server.Close()

	client := New(server.URL, "nx_test")
	if _, err := client.GetCatalog("widget"); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if capturedQuery != "query=widget" {
		t.Errorf("expected query=widget, got %s", capturedQuery)
	}
}

func TestErrorResponse_ReturnsAPIErrorWithEnvelopeDetails(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]any{"error": map[string]any{"code": "FORBIDDEN", "message": "Missing scope: catalog.read"}})
	}))
	defer server.Close()

	client := New(server.URL, "nx_test")
	_, err := client.GetCatalog("")

	apiErr, ok := err.(*APIError)
	if !ok {
		t.Fatalf("expected *APIError, got %T", err)
	}
	if apiErr.HTTPStatus != http.StatusForbidden || apiErr.Code != "FORBIDDEN" || apiErr.Message != "Missing scope: catalog.read" {
		t.Errorf("unexpected APIError: %+v", apiErr)
	}
}

func TestVerifyWebhookSignature_ValidSignature_ReturnsTrue(t *testing.T) {
	body := []byte(`{"event":"negotiation.accepted"}`)
	secret := "shhh"
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(body)
	signature := "sha256=" + hex.EncodeToString(mac.Sum(nil))

	if !VerifyWebhookSignature(body, signature, secret) {
		t.Error("expected valid signature to verify")
	}
}

func TestVerifyWebhookSignature_TamperedBody_ReturnsFalse(t *testing.T) {
	secret := "shhh"
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(`{"event":"original"}`))
	signature := "sha256=" + hex.EncodeToString(mac.Sum(nil))

	if VerifyWebhookSignature([]byte(`{"event":"tampered"}`), signature, secret) {
		t.Error("expected tampered body to fail verification")
	}
}
