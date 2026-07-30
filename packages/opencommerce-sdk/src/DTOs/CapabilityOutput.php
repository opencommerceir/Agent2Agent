<?php

namespace OpenCommerce\SDK\DTOs;

/**
 * Typed wrapper around the `data` payload a successful execution returns.
 * Held inside ExecutionResult rather than duplicating its job.
 */
final class CapabilityOutput
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
