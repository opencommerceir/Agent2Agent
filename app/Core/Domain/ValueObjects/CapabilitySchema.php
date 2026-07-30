<?php

namespace App\Core\Domain\ValueObjects;

/**
 * A lightweight field-name -> type-hint map (e.g. ['query' => 'string']),
 * not a full JSON-Schema implementation — that would need a schema
 * validation library and this phase only needs enough structure for
 * McpRequestValidationService to check an Agent's input has the right
 * shape before a capability "executes" (still a mock in this phase).
 */
final class CapabilitySchema
{
    /**
     * @param array<string, string> $fields
     */
    private function __construct(
        private readonly array $fields,
    ) {
    }

    /**
     * @param array<string, string> $fields
     */
    public static function fromArray(array $fields): self
    {
        return new self($fields);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->fields;
    }
}
