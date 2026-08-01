<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\UserData;
use App\Core\Domain\Exceptions\UserNotFoundException;
use App\Core\Domain\Repositories\UserRepositoryInterface;

final class GetUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function execute(int $id): UserData
    {
        $user = $this->users->findById($id);

        if (! $user) {
            throw new UserNotFoundException("User [{$id}] does not exist.");
        }

        return UserData::fromEntity($user);
    }
}
