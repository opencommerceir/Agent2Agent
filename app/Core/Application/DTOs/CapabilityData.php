<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Capability;

final class CapabilityData
{
    /**
     * @param array<string, string> $inputSchema
     * @param array<string, string> $outputSchema
     * @param list<string> $requiredPermissions
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly array $outputSchema,
        public readonly array $requiredPermissions,
    ) {
    }

    public static function fromEntity(Capability $capability): self
    {
        return new self(
            id: $capability->id(),
            name: $capability->name()->value(),
            description: $capability->description(),
            inputSchema: $capability->inputSchema()->toArray(),
            outputSchema: $capability->outputSchema()->toArray(),
            requiredPermissions: array_map(
                fn ($key) => $key->value(),
                $capability->requiredPermissions(),
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
            'outputSchema' => $this->outputSchema,
            'requiredPermissions' => $this->requiredPermissions,
        ];
    }
}
