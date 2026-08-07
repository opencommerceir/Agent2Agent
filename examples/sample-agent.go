// A minimal, standalone AI Agent program — proof that a plain Go program
// can discover and execute OpenCommerce capabilities using nothing but
// `go get opencommerce-sdk-go` (once published; see this file's own
// go.mod for how it's wired to the local copy inside this monorepo via a
// replace directive).
//
// Prerequisites:
//  1. `php artisan serve` running this app (default: http://localhost:8000)
//  2. An Agent token — generate one via GenerateAgentTokenAction, or see
//     the "Quick Start" section of packages/opencommerce-sdk/README.md
//     for a copy-pasteable Tinker snippet that creates a Tenant, Org,
//     Agent, grants the three demo.* permissions, and prints a token.
//
// Usage:
//
//	go run sample-agent.go <token> [base-url]
package main

import (
	"context"
	"errors"
	"fmt"
	"os"

	opencommerce "opencommerce-sdk-go"
)

func main() {
	os.Exit(run())
}

func run() int {
	if len(os.Args) < 2 {
		fmt.Fprintln(os.Stderr, "Usage: go run sample-agent.go <token> [base-url]")
		return 1
	}

	token := os.Args[1]
	baseURL := "http://localhost:8000/mcp/v1"
	if len(os.Args) > 2 {
		baseURL = os.Args[2]
	}

	ctx := context.Background()
	config := opencommerce.NewConfig(baseURL, token)
	client := opencommerce.NewClient(config)

	fmt.Println("=== Available Capabilities ===")
	capabilities, err := client.DiscoverCapabilities(ctx)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Discovery failed: %s\n", describe(err))
		return 1
	}
	for _, capability := range capabilities {
		fmt.Printf("- %s: %s\n", capability.Name, capability.Description)
	}

	fmt.Println("\n=== demo.tools.echo ===")
	if result, err := client.Execute(ctx, "demo.tools.echo", map[string]interface{}{"message": "Hello from AI Agent!"}); err != nil {
		fmt.Fprintf(os.Stderr, "demo.tools.echo failed: %s\n", describe(err))
	} else {
		fmt.Printf("%+v\n", result.Data)
	}

	fmt.Println("\n=== demo.tools.time ===")
	if result, err := client.Execute(ctx, "demo.tools.time", nil); err != nil {
		fmt.Fprintf(os.Stderr, "demo.tools.time failed: %s\n", describe(err))
	} else {
		fmt.Printf("%+v\n", result.Data)
	}

	fmt.Println("\n=== demo.tools.calculator ===")
	calcInput := map[string]interface{}{"operation": "multiply", "a": 42, "b": 10}
	if result, err := client.Execute(ctx, "demo.tools.calculator", calcInput); err != nil {
		fmt.Fprintf(os.Stderr, "demo.tools.calculator failed: %s\n", describe(err))
	} else {
		fmt.Printf("%+v\n", result.Data)
	}

	fmt.Println("\n=== Negative test: unknown capability ===")
	// Well-formed (domain.resource.action) but genuinely unregistered — a
	// malformed name like "demo.nonexistent" would fail format validation
	// (VALIDATION_ERROR) before ever reaching the "does this exist" check
	// this test is meant to demonstrate (NOT_FOUND).
	if _, err := client.Execute(ctx, "demo.tools.nonexistent", map[string]interface{}{}); err != nil {
		fmt.Printf("Correctly rejected: %s\n", describe(err))
	}

	return 0
}

func describe(err error) string {
	var mcpErr *opencommerce.MCPError
	if errors.As(err, &mcpErr) {
		return fmt.Sprintf("[%s] %s", mcpErr.ErrorCode, mcpErr.Message)
	}
	return err.Error()
}
