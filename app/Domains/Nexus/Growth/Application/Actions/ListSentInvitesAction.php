<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Application\DTOs\InviteData;
use App\Domains\Nexus\Growth\Domain\Repositories\InviteRepositoryInterface;

final class ListSentInvitesAction
{
    public function __construct(
        private readonly InviteRepositoryInterface $invites,
    ) {
    }

    /**
     * @return list<InviteData>
     */
    public function execute(int $inviterBusinessId): array
    {
        return array_map(
            fn ($invite) => InviteData::fromEntity($invite),
            $this->invites->findByInviterId($inviterBusinessId),
        );
    }
}
