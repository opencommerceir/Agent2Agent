<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\User as UserEntity;
use App\Core\Domain\Repositories\UserRepositoryInterface;
use App\Core\Domain\ValueObjects\Email;
use App\Core\Domain\ValueObjects\HashedPassword;
use App\Core\Domain\ValueObjects\UserRole;
use App\Core\Domain\ValueObjects\UserStatus;
use App\Core\Infrastructure\Models\User as UserModel;
use DateTimeImmutable;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?UserEntity
    {
        $model = UserModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByEmail(string $email): ?UserEntity
    {
        $model = UserModel::query()->where('email', $email)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function emailExists(string $email): bool
    {
        return UserModel::query()->where('email', $email)->exists();
    }

    public function all(): array
    {
        return UserModel::query()
            ->orderBy('id')
            ->get()
            ->map(fn (UserModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(UserEntity $user): UserEntity
    {
        $model = $user->id()
            ? UserModel::query()->findOrFail($user->id())
            : new UserModel();

        $model->name = $user->name();
        $model->email = $user->email()->value();
        $model->password = $user->password()->value();
        $model->role = $user->role()->value;
        $model->is_active = $user->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(UserModel $model): UserEntity
    {
        return new UserEntity(
            id: $model->id,
            name: $model->name,
            email: new Email($model->email),
            password: HashedPassword::fromHash($model->password),
            role: UserRole::from($model->role),
            status: $model->is_active ? UserStatus::Active : UserStatus::Inactive,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
