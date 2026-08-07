// Module path resolves to this monorepo's own real, public GitHub
// location — no separate repository was created for this SDK (§7.36).
// A consumer pins a version via a git tag of the form
// `packages/opencommerce-sdk-go/vX.Y.Z` (Go's own subdirectory-module
// tagging convention), pushed against this repository.
module github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go

go 1.21
