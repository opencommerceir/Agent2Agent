<?php

namespace App\SDK\DTOs;

/**
 * Client-side mirror of what GET /mcp/v1/capabilities returns per entry.
 * No `id` field — that's an internal database identifier the server uses;
 * an external Agent developer has no use for it, only the `name`.
 */
final class Capability
{
    /**
     * @param array<string, string> $inputSchema
     * @param array<string, string> $outputSchema
     * @param list<string> $requiredPermissions
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly array $outputSchema,
        public readonly array $requiredPermissions,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? '',
            inputSchema: $data['inputSchema'] ?? [],
            outputSchema: $data['outputSchema'] ?? [],
            requiredPermissions: $data['requiredPermissions'] ?? [],
        );
    }
}
