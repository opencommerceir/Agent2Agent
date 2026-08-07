package opencommerce

// Capability is the client-side mirror of one entry from
// GET /mcp/{version}/capabilities.
type Capability struct {
	Name                string
	Description         string
	InputSchema         map[string]string
	OutputSchema        map[string]string
	RequiredPermissions []string
}

func capabilityFromMap(data map[string]interface{}) Capability {
	return Capability{
		Name:                stringField(data, "name"),
		Description:         stringField(data, "description"),
		InputSchema:         stringMapField(data, "inputSchema"),
		OutputSchema:        stringMapField(data, "outputSchema"),
		RequiredPermissions: stringSliceField(data, "requiredPermissions"),
	}
}

// ExecutionResult is the return value of Client.Execute. There is no
// Error()/IsSuccess() field the way a Result type in some other
// languages might carry one — Execute always returns a non-nil error on
// any HTTP-level failure instead of a "failed" result, Go's own
// convention for errors.
type ExecutionResult struct {
	Data map[string]interface{}
	Meta map[string]interface{}
}
