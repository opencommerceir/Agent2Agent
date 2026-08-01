<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\User;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling) — same shape every
 * other Core aggregate's Repository interface already has.
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function emailExists(string $email): bool;

    /**
     * @return list<User>
     */
    public function all(): array;

    public function save(User $user): User;
}
