// Package opencommerce is the Go SDK for the OpenCommerce Platform's MCP
// Gateway — the layer that lets AI Agents (and any other Go code: a CLI
// tool, a Lambda, a background worker, ...) discover and execute business
// capabilities exposed by an OpenCommerce deployment, whether self-hosted
// or OpenCommerce's own hosted infrastructure.
package opencommerce

import "context"

// Client is the one type a developer needs to know about — the Go
// equivalent of packages/opencommerce-sdk/src/MCPClient.php.
//
//	config := opencommerce.NewConfig("http://localhost:8000/mcp/v1", "agent_token")
//	client := opencommerce.NewClient(config)
//
//	capabilities, err := client.DiscoverCapabilities(ctx)
//	result, err := client.Execute(ctx, "commerce.product.search", map[string]interface{}{"query": "laptop"})
//
// Migrating to v2 (docs/api/migration/v1-to-v2.md) is a one-argument
// change — point BaseURL at /mcp/v2 (or call
// ForVersion(host, "v2", token)) — nothing else about this type changes,
// since v1/v2 only differ in the response envelope shape, and
// DiscoverCapabilities/Execute already tolerate both.
//
// Every method takes a context.Context first — this SDK's one deliberate
// API difference from its PHP/Python/Node.js siblings, since explicit
// context propagation for cancellation/timeouts is Go's own standard
// idiom, not an inconsistency.
type Client struct {
	request *authenticatedTransport
}

// NewClient builds a Client for real use.
func NewClient(config Config) *Client {
	return &Client{request: newAuthenticatedTransport(config, nil)}
}

// NewClientWithTransport is for tests only — production code should
// always use NewClient, the same way the PHP SDK's MCPClient never takes
// an injected Guzzle client directly either.
func NewClientWithTransport(config Config, transport Transport) *Client {
	return &Client{request: newAuthenticatedTransport(config, transport)}
}

// DiscoverCapabilities returns every capability this Agent's token can
// see. No caching — a cached list could go stale the moment a new
// capability is registered server-side; wrap Client yourself if you want
// that trade-off.
func (c *Client) DiscoverCapabilities(ctx context.Context) ([]Capability, error) {
	response, err := c.request.get(ctx, "capabilities")
	if err != nil {
		return nil, err
	}
	if !isSuccess(response.Status) {
		return nil, ErrorFromResponse(response.Status, response.Body)
	}

	// v1 nests `capabilities` under `data`; v2 puts it at the top level
	// next to `metadata` — accept either, the same envelope-shape
	// tolerance Execute applies to `result`/`data`.
	raw := asSlice(asMap(response.Body["data"])["capabilities"])
	if raw == nil {
		raw = asSlice(response.Body["capabilities"])
	}

	capabilities := make([]Capability, 0, len(raw))
	for _, entry := range raw {
		capabilities = append(capabilities, capabilityFromMap(asMap(entry)))
	}
	return capabilities, nil
}

// Execute runs one capability. Returns a non-nil error (one of
// *MCPError, *AuthenticationError, *AuthorizationError, *NotFoundError,
// *ValidationError — see errors.go) on any non-2xx response; there is no
// "failed result" to check for separately. A nil input is treated as an
// empty object, matching every other OpenCommerce SDK.
func (c *Client) Execute(
	ctx context.Context,
	capabilityName string,
	input map[string]interface{},
) (ExecutionResult, error) {
	if input == nil {
		input = map[string]interface{}{}
	}

	response, err := c.request.post(ctx, "execute", map[string]interface{}{
		"capability": capabilityName,
		"input":      input,
	})
	if err != nil {
		return ExecutionResult{}, err
	}
	if !isSuccess(response.Status) {
		return ExecutionResult{}, ErrorFromResponse(response.Status, response.Body)
	}

	data := response.Body["data"]
	if result, ok := response.Body["result"]; ok {
		data = result
	}

	meta := response.Body["meta"]
	if metadata, ok := response.Body["metadata"]; ok {
		meta = metadata
	}

	return ExecutionResult{Data: asMap(data), Meta: asMap(meta)}, nil
}

// GetCapability fetches one capability by name.
//
// There is no GET /mcp/{version}/capabilities/{name} endpoint on the
// server today — this fetches the full discovery list and filters
// client-side, exactly like the PHP SDK does.
func (c *Client) GetCapability(ctx context.Context, capabilityName string) (Capability, error) {
	capabilities, err := c.DiscoverCapabilities(ctx)
	if err != nil {
		return Capability{}, err
	}

	for _, capability := range capabilities {
		if capability.Name == capabilityName {
			return capability, nil
		}
	}

	return Capability{}, &NotFoundError{&MCPError{
		ErrorCode:  "NOT_FOUND",
		Message:    "Capability [" + capabilityName + "] was not found.",
		StatusCode: 404,
	}}
}

func isSuccess(status int) bool {
	return status >= 200 && status < 300
}
