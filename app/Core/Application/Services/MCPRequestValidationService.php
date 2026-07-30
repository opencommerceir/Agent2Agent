<?php

namespace App\Core\Application\Services;

use App\Core\Application\DTOs\CapabilityData;
use InvalidArgumentException;

/**
 * Validates an Agent's `input` payload against the specific capability's
 * declared input_schema. Distinct from ExecuteCapabilityRequest (which
 * only validates the outer envelope shape — "capability" and "input" are
 * present) because the *inner* shape requirements vary per capability and
 * can't be expressed in a static FormRequest rules() array.
 *
 * Deliberately a lightweight field/type check, not full JSON-Schema
 * validation — CapabilitySchema itself is a simple map, not a schema
 * document, so there is nothing richer to validate against yet.
 */
final class MCPRequestValidationService
{
    public function validate(CapabilityData $capability, array $input): void
    {
        foreach ($capability->inputSchema as $field => $type) {
            if (! array_key_exists($field, $input)) {
                throw new InvalidArgumentException(
                    "Missing required input field [{$field}] for capability [{$capability->name}]."
                );
            }

            if (! $this->matchesType($input[$field], $type)) {
                throw new InvalidArgumentException(
                    "Input field [{$field}] must be of type [{$type}] for capability [{$capability->name}]."
                );
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'number' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            default => true, // unrecognized declared type: don't block execution over a schema hint we don't understand
        };
    }
}
