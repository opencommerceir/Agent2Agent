<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\User;

/**
 * Structured data transfer for User across layers. Never carries the
 * password hash — DTOs cross into the Interfaces/HTTP layer and, from
 * there, Blade views; a hash has no reason to travel past the Repository
 * boundary.
 */
final class UserData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly string $status,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->id(),
            name: $user->name(),
            email: $user->email()->value(),
            role: $user->role()->value,
            status: $user->status()->value,
        );
    }

    /**
     * @return array{id: ?int, name: string, email: string, role: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
        ];
    }
}
