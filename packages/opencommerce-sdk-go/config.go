package opencommerce

import "strings"

// Config holds immutable connection settings for a single Client instance.
//
// Mirrors the PHP SDK's own MCPConfig
// (packages/opencommerce-sdk/src/Config/MCPConfig.php) field for field.
// BaseURL already carries the wire version in its own path
// (https://api.opencommerce.ir/mcp/v1, .../mcp/v2, ...) — a consumer picks
// a version simply by pointing at a different BaseURL, the same explicit,
// no-hidden-behavior approach the server's own version detection uses.
// ForVersion is purely additive sugar for building that URL correctly.
type Config struct {
	BaseURL string
	Token   string

	// TimeoutSeconds — see NewConfig/ForVersion for the default (30,
	// matching the PHP/Python/Node SDKs).
	TimeoutSeconds int

	// VerifySSL — see NewConfig/ForVersion for the default (true). The
	// default HTTPTransport always honors this one.
	VerifySSL bool
}

// ConfigOption customizes a Config built by NewConfig or ForVersion —
// Go's own idiomatic functional-options pattern, since the language has
// no optional/named constructor parameters the way PHP/Python/TypeScript do.
type ConfigOption func(*Config)

// WithTimeout overrides the default 30 second timeout.
func WithTimeout(seconds int) ConfigOption {
	return func(c *Config) { c.TimeoutSeconds = seconds }
}

// WithVerifySSL overrides the default (true). Only the default
// HTTPTransport actually reads this field — a custom Transport is free to
// ignore it.
func WithVerifySSL(verify bool) ConfigOption {
	return func(c *Config) { c.VerifySSL = verify }
}

// NewConfig builds a Config with the same defaults every other
// OpenCommerce SDK uses: a 30 second timeout, TLS verification on.
func NewConfig(baseURL, token string, opts ...ConfigOption) Config {
	config := Config{BaseURL: baseURL, Token: token, TimeoutSeconds: 30, VerifySSL: true}
	for _, opt := range opts {
		opt(&config)
	}
	return config
}

// ForVersion builds BaseURL as "{host}/mcp/{version}" for you.
//
//	config := opencommerce.ForVersion("https://api.opencommerce.ir", "v2", "agent_token")
//	// config.BaseURL == "https://api.opencommerce.ir/mcp/v2"
func ForVersion(host, version, token string, opts ...ConfigOption) Config {
	return NewConfig(strings.TrimRight(host, "/")+"/mcp/"+version, token, opts...)
}
