package opencommerce_test

import (
	"testing"

	opencommerce "opencommerce-sdk-go"
)

func TestNewConfigAcceptsAFullyQualifiedBaseURLDirectly(t *testing.T) {
	config := opencommerce.NewConfig("https://api.opencommerce.ir/mcp/v1", "agent_token")

	if config.BaseURL != "https://api.opencommerce.ir/mcp/v1" {
		t.Fatalf("got BaseURL %q", config.BaseURL)
	}
}

func TestForVersionBuildsTheBaseURLFromHostAndVersion(t *testing.T) {
	config := opencommerce.ForVersion("https://api.opencommerce.ir", "v2", "agent_token")

	if config.BaseURL != "https://api.opencommerce.ir/mcp/v2" {
		t.Fatalf("got BaseURL %q", config.BaseURL)
	}
	if config.Token != "agent_token" {
		t.Fatalf("got Token %q", config.Token)
	}
}

func TestForVersionTrimsATrailingSlashFromTheHost(t *testing.T) {
	config := opencommerce.ForVersion("https://api.opencommerce.ir/", "v1", "agent_token")

	if config.BaseURL != "https://api.opencommerce.ir/mcp/v1" {
		t.Fatalf("got BaseURL %q", config.BaseURL)
	}
}

func TestWithTimeoutAndWithVerifySSLOverrideTheDefaults(t *testing.T) {
	config := opencommerce.ForVersion(
		"https://api.opencommerce.ir", "v2", "agent_token",
		opencommerce.WithTimeout(5),
		opencommerce.WithVerifySSL(false),
	)

	if config.TimeoutSeconds != 5 {
		t.Fatalf("got TimeoutSeconds %d", config.TimeoutSeconds)
	}
	if config.VerifySSL {
		t.Fatal("expected VerifySSL to be false")
	}
}

func TestDefaultsMatchTheOtherSDKs(t *testing.T) {
	config := opencommerce.NewConfig("https://api.opencommerce.ir/mcp/v1", "agent_token")

	if config.TimeoutSeconds != 30 {
		t.Fatalf("got TimeoutSeconds %d", config.TimeoutSeconds)
	}
	if !config.VerifySSL {
		t.Fatal("expected VerifySSL to default to true")
	}
}
