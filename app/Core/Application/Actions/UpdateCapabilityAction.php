<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\CapabilityData;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;
use App\Core\Domain\ValueObjects\CapabilityName;
use App\Core\Domain\ValueObjects\CapabilitySchema;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Core\Domain\Events\CapabilityWasUpdated;
use Illuminate\Support\Facades\Event;

final class UpdateCapabilityAction
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
        $capability = $this->capabilities->findByName(new CapabilityName($name));

        if (! $capability) {
            throw new CapabilityNotFoundException("Capability [{$name}] does not exist.");
        }

        $capability->update(
            description: $description,
            inputSchema: CapabilitySchema::fromArray($inputSchema),
            outputSchema: CapabilitySchema::fromArray($outputSchema),
            requiredPermissions: array_map(fn (string $key) => new PermissionKey($key), $requiredPermissions),
        );

        $capability = $this->capabilities->save($capability);

        Event::dispatch(new CapabilityWasUpdated($capability));

        return CapabilityData::fromEntity($capability);
    }
}
