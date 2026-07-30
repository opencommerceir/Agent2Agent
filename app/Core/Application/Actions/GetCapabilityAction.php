<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\CapabilityData;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;
use App\Core\Domain\ValueObjects\CapabilityName;

final class GetCapabilityAction
{
    public function __construct(
        private readonly CapabilityRepositoryInterface $capabilities,
    ) {
    }

    public function execute(string $name): CapabilityData
    {
        $capability = $this->capabilities->findByName(new CapabilityName($name));

        if (! $capability) {
            throw new CapabilityNotFoundException("Capability [{$name}] does not exist.");
        }

        return CapabilityData::fromEntity($capability);
    }
}
