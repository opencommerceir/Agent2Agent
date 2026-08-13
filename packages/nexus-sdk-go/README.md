# Nexus Go SDK

Official Go client for the [Nexus Public REST API](/nexus/docs). Zero third-party dependencies (standard library `net/http` only).

> **Scope note:** no Go toolchain was available in the dev environment this codebase was built in, so — unlike the PHP/Node/Python SDKs alongside this package, each of which has a passing test suite run in that environment — this package has not been compiled or executed there. It's written to the exact same contract as the other three, but treat it as unverified until built and tested with a real Go toolchain (`go test ./...`).

## Install

This package lives in-repo at `packages/nexus-sdk-go` today (not yet published as a versioned module). To use it, add a `replace` directive to your own `go.mod`:

```
replace github.com/opencommerceir/nexus-sdk-go => ../nexus-sdk-go
```

## Usage

```go
client := nexus.New("https://your-nexus-domain.example.com", "nx_your_api_key")

profile, err := client.GetBusinessProfile()
catalog, err := client.GetCatalog("widget")
listings, err := client.SearchMarketplace("", "technology")
negotiation, err := client.GetNegotiation(42)
balance, err := client.GetCreditBalance()

result, err := client.GraphQL("{ creditBalance { balance } catalog { products } }", nil)
```

## Errors

```go
_, err := client.GetCatalog("")
if apiErr, ok := err.(*nexus.APIError); ok {
    // apiErr.HTTPStatus, apiErr.Code, apiErr.Message
}
```

## Verifying webhook signatures

```go
isValid := nexus.VerifyWebhookSignature(rawRequestBody, request.Header.Get("X-Nexus-Signature"), yourStoredWebhookSecret)
```

## Testing

```
go test ./...
```
