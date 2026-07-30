<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\Permission;
use App\Core\Domain\ValueObjects\PermissionKey;

interface PermissionRepositoryInterface
{
    public function findById(int $id): ?Permission;

    public function findByKey(PermissionKey $key): ?Permission;

    public function save(Permission $permission): Permission;
}
