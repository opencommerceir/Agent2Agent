// A minimal module wrapper so `go run sample-agent.go ...` works directly
// from this folder — points at the local copy of the Go SDK inside this
// monorepo. An external consumer of the SDK wouldn't need this file at
// all; they'd just `go get` the SDK's real, published module path (see
// packages/opencommerce-sdk-go/README.md's own "Module path" note) inside
// their own project's go.mod.
module opencommerce-examples

go 1.21

require opencommerce-sdk-go v0.0.0

replace opencommerce-sdk-go => ../packages/opencommerce-sdk-go
