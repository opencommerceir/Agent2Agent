<?php

namespace OpenCommerce\SDK\DTOs;

/**
 * Typed wrapper around the `input` payload sent to execute(). Does not
 * validate against a capability's input_schema — the SDK doesn't know
 * that schema until discover() is called, and re-implementing the check
 * client-side would duplicate the server's own validation. Validation
 * stays a server responsibility; this class only offers typed access.
 */
final class CapabilityInput
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
