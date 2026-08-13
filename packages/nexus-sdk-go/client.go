// Package nexus is the official Go client for the Nexus Public REST API
// (see /nexus/docs on any Nexus deployment for the full reference this
// package implements). Zero third-party dependencies — only net/http and
// the standard library, the same "don't force a dependency choice on the
// consumer" reasoning the PHP (plain curl), Node (built-in fetch), and
// Python (urllib) SDKs in this same packages/ directory already apply.
//
// Scope note: no Go toolchain is available in the dev environment this
// codebase was built in, so unlike the other three SDKs (each with its
// own passing test suite run in that environment), this package has not
// been compiled or executed here. It is written to the exact same
// contract (methods, error shape, signature verification) as the
// PHP/Node/Python clients, which HAVE been verified — but treat this one
// as unverified until built and tested with a real Go toolchain. The
// same honesty this codebase already applies to SAML/LDAP connector
// stubs (Phase 7/M8) and local-LLM endpoints (Phase 4/M2): a real,
// carefully-written implementation of something this environment simply
// cannot run, not a placeholder.
package nexus

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
)

// APIError is returned for any non-2xx response — it carries the Nexus
// error envelope's own Code/Message fields (see /nexus/docs) rather than
// a generic HTTP-status-only error.
type APIError struct {
	HTTPStatus int
	Code       string
	Message    string
}

func (e *APIError) Error() string {
	return fmt.Sprintf("nexus: %s (%s, HTTP %d)", e.Message, e.Code, e.HTTPStatus)
}

// Client is the Nexus Public REST API client.
type Client struct {
	baseURL    string
	apiKey     string
	httpClient *http.Client
}

// New creates a Client. Pass a custom *http.Client (e.g. one with a mock
// RoundTripper for tests) via WithHTTPClient — the same injectable-
// transport shape the PHP/Node/Python SDKs already use.
func New(baseURL, apiKey string, opts ...Option) *Client {
	c := &Client{
		baseURL:    strings.TrimRight(baseURL, "/"),
		apiKey:     apiKey,
		httpClient: &http.Client{Timeout: 15 * time.Second},
	}
	for _, opt := range opts {
		opt(c)
	}
	return c
}

// Option configures a Client.
type Option func(*Client)

// WithHTTPClient overrides the default *http.Client (useful for tests).
func WithHTTPClient(httpClient *http.Client) Option {
	return func(c *Client) { c.httpClient = httpClient }
}

func (c *Client) GetBusinessProfile() (map[string]any, error) {
	return c.get("business", nil)
}

func (c *Client) GetCatalog(query string) (map[string]any, error) {
	params := url.Values{}
	if query != "" {
		params.Set("query", query)
	}
	return c.get("catalog", params)
}

func (c *Client) SearchMarketplace(query, industry string) (map[string]any, error) {
	params := url.Values{}
	if query != "" {
		params.Set("query", query)
	}
	if industry != "" {
		params.Set("industry", industry)
	}
	return c.get("marketplace/search", params)
}

func (c *Client) GetNegotiation(negotiationID int) (map[string]any, error) {
	return c.get(fmt.Sprintf("negotiations/%d", negotiationID), nil)
}

func (c *Client) GetCreditBalance() (map[string]any, error) {
	return c.get("credit/balance", nil)
}

func (c *Client) GraphQL(query string, variables map[string]any) (map[string]any, error) {
	if variables == nil {
		variables = map[string]any{}
	}
	return c.request(http.MethodPost, "/nexus/api/v1/graphql", map[string]any{
		"query":     query,
		"variables": variables,
	})
}

func (c *Client) get(path string, params url.Values) (map[string]any, error) {
	fullPath := "/nexus/api/v1/" + path
	if params != nil && len(params) > 0 {
		fullPath += "?" + params.Encode()
	}

	decoded, err := c.request(http.MethodGet, fullPath, nil)
	if err != nil {
		return nil, err
	}
	if data, ok := decoded["data"].(map[string]any); ok {
		return data, nil
	}
	return decoded, nil
}

func (c *Client) request(method, path string, jsonBody any) (map[string]any, error) {
	var bodyReader io.Reader
	if jsonBody != nil {
		encoded, err := json.Marshal(jsonBody)
		if err != nil {
			return nil, err
		}
		bodyReader = bytes.NewReader(encoded)
	}

	req, err := http.NewRequest(method, c.baseURL+path, bodyReader)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Authorization", "Bearer "+c.apiKey)
	req.Header.Set("Accept", "application/json")
	if jsonBody != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	rawBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	var decoded map[string]any
	if len(rawBody) > 0 {
		if err := json.Unmarshal(rawBody, &decoded); err != nil {
			return nil, err
		}
	}

	if resp.StatusCode >= 400 {
		code, message := "UNKNOWN", "Request failed."
		if errObj, ok := decoded["error"].(map[string]any); ok {
			if v, ok := errObj["code"].(string); ok {
				code = v
			}
			if v, ok := errObj["message"].(string); ok {
				message = v
			}
		}
		return nil, &APIError{HTTPStatus: resp.StatusCode, Code: code, Message: message}
	}

	return decoded, nil
}

// VerifyWebhookSignature verifies a webhook delivery's X-Nexus-Signature
// header — timing-safe comparison (hmac.Equal), the same discipline the
// PHP/Node/Python SDKs' hash_equals()/timingSafeEqual()/compare_digest()
// already use.
func VerifyWebhookSignature(rawBody []byte, signatureHeader, webhookSecret string) bool {
	mac := hmac.New(sha256.New, []byte(webhookSecret))
	mac.Write(rawBody)
	expected := "sha256=" + hex.EncodeToString(mac.Sum(nil))

	return hmac.Equal([]byte(expected), []byte(signatureHeader))
}
