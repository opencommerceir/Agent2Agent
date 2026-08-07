package opencommerce

// Small, defensive accessors over the generic map[string]interface{}
// shape encoding/json produces for an arbitrary JSON object — every field
// is read with a type assertion + fallback rather than a direct cast, so
// a server response missing an expected key (or carrying an unexpected
// type) degrades to a zero value instead of panicking.

func asMap(v interface{}) map[string]interface{} {
	if m, ok := v.(map[string]interface{}); ok {
		return m
	}
	return map[string]interface{}{}
}

func asSlice(v interface{}) []interface{} {
	if s, ok := v.([]interface{}); ok {
		return s
	}
	return nil
}

func stringField(data map[string]interface{}, key string) string {
	if s, ok := data[key].(string); ok {
		return s
	}
	return ""
}

func stringMapField(data map[string]interface{}, key string) map[string]string {
	result := map[string]string{}
	raw, ok := data[key].(map[string]interface{})
	if !ok {
		return result
	}
	for k, v := range raw {
		if s, ok := v.(string); ok {
			result[k] = s
		}
	}
	return result
}

func stringSliceField(data map[string]interface{}, key string) []string {
	var result []string
	raw, ok := data[key].([]interface{})
	if !ok {
		return result
	}
	for _, v := range raw {
		if s, ok := v.(string); ok {
			result = append(result, s)
		}
	}
	return result
}
