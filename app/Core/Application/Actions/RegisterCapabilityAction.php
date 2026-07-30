<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\CapabilityData;
use App\Core\Domain\Entities\Capability;
use App\Core\Domain\Events\CapabilityWasRegistered;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;
use App\Core\Domain\ValueObjects\CapabilityName;
use App\Core\Domain\ValueObjects\CapabilitySchema;
use App\Core\Domain\ValueObjects\PermissionKey;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class RegisterCapabilityAction
{
    public function __construct(
        private readonly CapabilityRepositoryInterface $capabilities,
    ) {
    }

    /**
     * @param array<string, string> $inputSchema
     * @param array<string, string> $outputSchema
     * @param list<string> $requiredPermissions
     */
    public function execute(
        string $name,
        string $description,
        array $inputSchema = [],
        array $outputSchema = [],
        array $requiredPermissions = [],
    ): CapabilityData {
        $capabilityName = new CapabilityName($name); // throws InvalidCapabilityNameException on bad format

        if ($this->capabilities->findByName($capabilityName)) {
            throw new InvalidArgumentException("Capability [{$name}] already exists.");
        }

        $capability = Capability::register(
            name: $capabilityName,
            description: $description,
            inputSchema: CapabilitySchema::fromArray($inputSchema),
            outputSchema: CapabilitySchema::fromArray($outputSchema),
            requiredPermissions: array_map(fn (string $key) => new PermissionKey($key), $requiredPermissions),
        );

        $capability = $this->capabilities->save($capability);

        Event::dispatch(new CapabilityWasRegistered($capability));

        return CapabilityData::fromEntity($capability);
    }
}
