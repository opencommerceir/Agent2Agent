<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ServiceData;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only — same shape as RejectProductAction, for Service listings.
 */
final class RejectServiceAction
{
    public function __construct(
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function execute(int $serviceId): ServiceData
    {
        $service = $this->services->findById($serviceId);

        if (! $service) {
            throw new InvalidArgumentException("Service [{$serviceId}] does not exist.");
        }

        $service->reject();

        return ServiceData::fromEntity($this->services->save($service));
    }
}
