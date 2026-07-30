<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\RoleData;
use App\Core\Domain\Entities\Role;
use App\Core\Domain\Events\RoleWasCreated;
use App\Core\Domain\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class CreateRoleAction
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {
    }

    public function execute(int $tenantId, string $name, string $slug, ?string $description = null): RoleData
    {
        if ($this->roles->findBySlug($tenantId, $slug)) {
            throw new InvalidArgumentException("Role slug [{$slug}] is already taken in this tenant.");
        }

        $role = Role::create($tenantId, $name, $slug, $description);
        $role = $this->roles->save($role);

        Event::dispatch(new RoleWasCreated($role));

        return RoleData::fromEntity($role);
    }
}
