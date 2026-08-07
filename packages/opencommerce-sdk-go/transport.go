package opencommerce

import (
	"bytes"
	"context"
	"crypto/tls"
	"encoding/json"
	"io"
	"net/http"
	"strings"
	"time"
)

// TransportResponse is the normalized shape every Transport returns.
type TransportResponse struct {
	Status int
	Body   map[string]interface{}
}

// Transport performs one HTTP request. Kept as an interface so tests
// never touch a real socket — the same role
// packages/opencommerce-sdk/src/Authentication/AuthenticatedRequest.php
// plays in the PHP SDK, with an injectable client for that same reason.
type Transport interface {
	Do(
		ctx context.Context,
		method, url string,
		headers map[string]string,
		jsonBody map[string]interface{},
		timeoutSeconds int,
	) (TransportResponse, error)
}

// HTTPTransport is the real, default Transport, built on net/http — the
// Go standard library, no third-party HTTP client dependency.
type HTTPTransport struct {
	client *http.Client
}

// NewHTTPTransport builds an HTTPTransport honoring verifySSL. Only set
// verifySSL to false for local development against a self-signed
// certificate — never in production.
func NewHTTPTransport(verifySSL bool) *HTTPTransport {
	transport := &http.Transport{}
	if !verifySSL {
		transport.TLSClientConfig = &tls.Config{InsecureSkipVerify: true} //nolint:gosec // explicit, documented, caller-requested opt-out only
	}
	return &HTTPTransport{client: &http.Client{Transport: transport}}
}

// Do implements Transport.
func (t *HTTPTransport) Do(
	ctx context.Context,
	method, url string,
	headers map[string]string,
	jsonBody map[string]interface{},
	timeoutSeconds int,
) (TransportResponse, error) {
	var bodyReader io.Reader
	if jsonBody != nil {
		encoded, err := json.Marshal(jsonBody)
		if err != nil {
			return TransportResponse{}, err
		}
		bodyReader = bytes.NewReader(encoded)
	}

	timeoutCtx, cancel := context.WithTimeout(ctx, time.Duration(timeoutSeconds)*time.Second)
	defer cancel()

	req, err := http.NewRequestWithContext(timeoutCtx, method, url, bodyReader)
	if err != nil {
		return TransportResponse{}, err
	}
	for key, value := range headers {
		req.Header.Set(key, value)
	}
	if jsonBody != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	resp, err := t.client.Do(req)
	if err != nil {
		return TransportResponse{}, err
	}
	defer resp.Body.Close()

	raw, err := io.ReadAll(resp.Body)
	if err != nil {
		return TransportResponse{}, err
	}

	return TransportResponse{Status: resp.StatusCode, Body: decodeJSONObject(raw)}, nil
}

func decodeJSONObject(raw []byte) map[string]interface{} {
	if len(raw) == 0 {
		return map[string]interface{}{}
	}

	var decoded map[string]interface{}
	if err := json.Unmarshal(raw, &decoded); err != nil {
		return map[string]interface{}{}
	}
	return decoded
}

// authenticatedTransport joins Config.BaseURL to a path and attaches the
// bearer token header, so Client never builds a URL or a header map
// itself.
type authenticatedTransport struct {
	config    Config
	transport Transport
}

func newAuthenticatedTransport(config Config, transport Transport) *authenticatedTransport {
	if transport == nil {
		transport = NewHTTPTransport(config.VerifySSL)
	}
	return &authenticatedTransport{config: config, transport: transport}
}

func (t *authenticatedTransport) get(ctx context.Context, path string) (TransportResponse, error) {
	return t.request(ctx, http.MethodGet, path, nil)
}

func (t *authenticatedTransport) post(
	ctx context.Context,
	path string,
	jsonBody map[string]interface{},
) (TransportResponse, error) {
	return t.request(ctx, http.MethodPost, path, jsonBody)
}

func (t *authenticatedTransport) request(
	ctx context.Context,
	method, path string,
	jsonBody map[string]interface{},
) (TransportResponse, error) {
	url := strings.TrimRight(t.config.BaseURL, "/") + "/" + strings.TrimLeft(path, "/")
	headers := map[string]string{"Authorization": "Bearer " + t.config.Token}

	timeout := t.config.TimeoutSeconds
	if timeout == 0 {
		timeout = 30
	}

	return t.transport.Do(ctx, method, url, headers, jsonBody, timeout)
}
