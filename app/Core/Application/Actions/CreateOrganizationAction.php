<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\OrganizationData;
use App\Core\Domain\Entities\Organization;
use App\Core\Domain\Events\OrganizationWasCreated;
use App\Core\Domain\Repositories\OrganizationRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class CreateOrganizationAction
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(int $tenantId, string $name, string $slug): OrganizationData
    {
        if ($this->organizations->existsBySlug($tenantId, $slug)) {
            throw new InvalidArgumentException("Organization slug [{$slug}] is already taken in this tenant.");
        }

        $organization = Organization::create($tenantId, $name, $slug);
        $organization = $this->organizations->save($organization);

        Event::dispatch(new OrganizationWasCreated($organization));

        return OrganizationData::fromEntity($organization);
    }
}
