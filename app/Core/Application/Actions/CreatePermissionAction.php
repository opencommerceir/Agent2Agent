<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\PermissionData;
use App\Core\Domain\Entities\Permission;
use App\Core\Domain\Events\PermissionWasCreated;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\PermissionKey;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class CreatePermissionAction
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
    ) {
    }

    public function execute(string $key, ?string $description = null): PermissionData
    {
        $permissionKey = new PermissionKey($key); // throws InvalidPermissionKeyException on bad format

        if ($this->permissions->findByKey($permissionKey)) {
            throw new InvalidArgumentException("Permission [{$key}] already exists.");
        }

        $permission = Permission::create($permissionKey, $description);
        $permission = $this->permissions->save($permission);

        Event::dispatch(new PermissionWasCreated($permission));

        return PermissionData::fromEntity($permission);
    }
}
