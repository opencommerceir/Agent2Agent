<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\Email;
use App\Core\Domain\ValueObjects\HashedPassword;
use App\Core\Domain\ValueObjects\UserRole;
use App\Core\Domain\ValueObjects\UserStatus;
use DateTimeImmutable;

/**
 * A human identity that can log into the Admin Dashboard — deliberately a
 * platform-level Core entity with no tenant_id, the second one alongside
 * `Tenant` itself. Not a `MemberType::User` `OrganizationMember` (that
 * polymorphic case models a human belonging to *one specific* Tenant's
 * Organization, a distinct, still-unbuilt future feature — HANDOFF §8.7);
 * this User is a platform operator who can see/manage every Tenant, the
 * same reasoning `UserRole`'s own docblock explains in more depth.
 *
 * Replaces an earlier, never-wired Phase 1 skeleton of this same class
 * (tenantId + organizationId + a raw string email, no password at all —
 * literally incapable of authenticating anyone) that had zero callers, no
 * Repository interface, and no Infrastructure model anywhere in the
 * codebase. This is Phase 4 Stage 5's real, first implementation, not a
 * modification of working code.
 */
final class User
{
    public function __construct(
        private readonly ?int $id,
        private string $name,
        private Email $email,
        private HashedPassword $password,
        private UserRole $role,
        private UserStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(string $name, Email $email, HashedPassword $password, UserRole $role): self
    {
        return new self(
            id: null,
            name: $name,
            email: $email,
            password: $password,
            role: $role,
            status: UserStatus::Active,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
    }

    public function changePassword(HashedPassword $password): void
    {
        $this->password = $password;
    }

    public function changeRole(UserRole $role): void
    {
        $this->role = $role;
    }

    public function activate(): void
    {
        $this->status = UserStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::Inactive;
    }

    public function verifyPassword(string $plainTextPassword): bool
    {
        return $this->password->verify($plainTextPassword);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
