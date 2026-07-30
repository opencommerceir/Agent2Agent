<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\CapabilityName;
use App\Core\Domain\ValueObjects\CapabilitySchema;
use App\Core\Domain\ValueObjects\PermissionKey;
use DateTimeImmutable;

/**
 * A capability is a *description* of something an Agent can invoke through
 * MCP — never the execution itself (Decision 010). Registering one does
 * not wire up any behavior; a Domain Module (Commerce, in Phase 2) is what
 * eventually attaches real execution logic behind this name. Deliberately
 * global, not tenant-scoped, for the same reason as Permission: this is
 * platform vocabulary defined once by the Capability Registry, not private
 * per-tenant data.
 */
final class Capability
{
    /**
     * @param list<PermissionKey> $requiredPermissions
     */
    public function __construct(
        private readonly ?int $id,
        private readonly CapabilityName $name,
        private string $description,
        private CapabilitySchema $inputSchema,
        private CapabilitySchema $outputSchema,
        private array $requiredPermissions,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<PermissionKey> $requiredPermissions
     */
    public static function register(
        CapabilityName $name,
        string $description,
        CapabilitySchema $inputSchema,
        CapabilitySchema $outputSchema,
        array $requiredPermissions = [],
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            inputSchema: $inputSchema,
            outputSchema: $outputSchema,
            requiredPermissions: $requiredPermissions,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param list<PermissionKey> $requiredPermissions
     */
    public function update(
        string $description,
        CapabilitySchema $inputSchema,
        CapabilitySchema $outputSchema,
        array $requiredPermissions,
    ): void {
        $this->description = $description;
        $this->inputSchema = $inputSchema;
        $this->outputSchema = $outputSchema;
        $this->requiredPermissions = $requiredPermissions;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): CapabilityName
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function inputSchema(): CapabilitySchema
    {
        return $this->inputSchema;
    }

    public function outputSchema(): CapabilitySchema
    {
        return $this->outputSchema;
    }

    /**
     * @return list<PermissionKey>
     */
    public function requiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
