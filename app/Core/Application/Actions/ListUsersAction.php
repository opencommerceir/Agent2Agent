<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\UserData;
use App\Core\Domain\Entities\User;
use App\Core\Domain\Repositories\UserRepositoryInterface;

/**
 * Users are a small, platform-wide, admin-only list (not a paginated
 * per-tenant capability the way Commerce's ListProductsAction is) — no
 * limit/filter input needed, the same shape TenantRepositoryInterface::all()
 * already established for the Dashboard's own Tenant listing.
 */
final class ListUsersAction
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * @return list<UserData>
     */
    public function execute(): array
    {
        return array_map(
            fn (User $user) => UserData::fromEntity($user),
            $this->users->all(),
        );
    }
}
