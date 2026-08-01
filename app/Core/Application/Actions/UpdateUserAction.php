<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\UserData;
use App\Core\Domain\Events\UserWasUpdated;
use App\Core\Domain\Exceptions\UserNotFoundException;
use App\Core\Domain\Repositories\UserRepositoryInterface;
use App\Core\Domain\ValueObjects\Email;
use App\Core\Domain\ValueObjects\HashedPassword;
use App\Core\Domain\ValueObjects\UserRole;
use App\Core\Domain\ValueObjects\UserStatus;
use Illuminate\Support\Facades\Event;

/**
 * $plainPassword is the one genuinely optional trailing parameter (HANDOFF
 * §3 pattern #6) — an edit form leaves the password field blank to keep
 * the current one; every other field is a required full replacement value,
 * the same shape UpdateWorkflowAction/UpdateTaxRateAction already
 * establish (a caller always submits the whole form, not a sparse patch).
 */
final class UpdateUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function execute(
        int $id,
        string $name,
        string $email,
        string $role,
        string $status,
        ?string $plainPassword = null,
    ): UserData {
        $user = $this->users->findById($id);

        if (! $user) {
            throw new UserNotFoundException("User [{$id}] does not exist.");
        }

        $user->rename($name);
        $user->changeEmail(new Email($email));
        $user->changeRole(UserRole::from($role));

        if (UserStatus::from($status) === UserStatus::Active) {
            $user->activate();
        } else {
            $user->deactivate();
        }

        if ($plainPassword !== null && $plainPassword !== '') {
            $user->changePassword(HashedPassword::fromPlainText($plainPassword));
        }

        $user = $this->users->save($user);

        Event::dispatch(new UserWasUpdated($user));

        return UserData::fromEntity($user);
    }
}
