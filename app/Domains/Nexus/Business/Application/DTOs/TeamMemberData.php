<?php

namespace App\Domains\Nexus\Business\Application\DTOs;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;

final class TeamMemberData
{
    public function __construct(
        public readonly int $id,
        public readonly int $businessId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $mustChangePassword,
    ) {
    }

    public static function fromModel(BusinessOwner $owner): self
    {
        return new self(
            id: $owner->id,
            businessId: $owner->business_id,
            name: $owner->name,
            email: $owner->email,
            role: $owner->role->value,
            mustChangePassword: $owner->must_change_password,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessId' => $this->businessId,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'mustChangePassword' => $this->mustChangePassword,
        ];
    }
}
