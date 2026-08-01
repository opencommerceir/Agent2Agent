<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\UserData;
use App\Core\Domain\Entities\User;
use App\Core\Domain\Events\UserWasCreated;
use App\Core\Domain\Repositories\UserRepositoryInterface;
use App\Core\Domain\ValueObjects\Email;
use App\Core\Domain\ValueObjects\HashedPassword;
use App\Core\Domain\ValueObjects\UserRole;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * One Action = one business operation: register a new Dashboard User and
 * dispatch the corresponding domain event — same shape CreateTenantAction
 * already establishes, including the plain InvalidArgumentException for a
 * duplicate unique field (mirrors CreateTenantAction's own slugExists
 * check).
 */
final class CreateUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function execute(string $name, string $email, string $plainPassword, string $role): UserData
    {
        if ($this->users->emailExists(strtolower(trim($email)))) {
            throw new InvalidArgumentException("A User with email [{$email}] already exists.");
        }

        $user = User::register(
            name: $name,
            email: new Email($email),
            password: HashedPassword::fromPlainText($plainPassword),
            role: UserRole::from($role),
        );

        $user = $this->users->save($user);

        Event::dispatch(new UserWasCreated($user));

        return UserData::fromEntity($user);
    }
}
