<?php

namespace App\Domains\Nexus\Growth\Application\DTOs;

use App\Domains\Nexus\Growth\Domain\Entities\Invite;

final class InviteData
{
    public function __construct(
        public readonly int $id,
        public readonly string $inviteeName,
        public readonly string $inviteeEmail,
        public readonly string $status,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(Invite $invite): self
    {
        return new self(
            id: $invite->id(),
            inviteeName: $invite->inviteeName(),
            inviteeEmail: $invite->inviteeEmail(),
            status: $invite->status()->value,
            createdAt: $invite->createdAt()->format(DATE_ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'inviteeName' => $this->inviteeName,
            'inviteeEmail' => $this->inviteeEmail,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
        ];
    }
}
