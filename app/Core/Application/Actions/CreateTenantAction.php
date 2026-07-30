<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\TenantData;
use App\Core\Domain\Entities\Tenant;
use App\Core\Domain\Events\TenantWasRegistered;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * One Action = one business operation (Application Layer Rules):
 * register a new Tenant and dispatch the corresponding domain event.
 */
final class CreateTenantAction
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function execute(string $name, string $slug): TenantData
    {
        if ($this->tenants->slugExists($slug)) {
            throw new InvalidArgumentException("Tenant slug [{$slug}] is already taken.");
        }

        $tenant = Tenant::register($name, $slug);
        $tenant = $this->tenants->save($tenant);

        Event::dispatch(new TenantWasRegistered($tenant));

        return TenantData::fromEntity($tenant);
    }
}
