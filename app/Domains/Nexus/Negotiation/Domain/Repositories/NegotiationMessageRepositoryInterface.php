<?php

namespace App\Domains\Nexus\Negotiation\Domain\Repositories;

use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;

interface NegotiationMessageRepositoryInterface
{
    /**
     * @return list<NegotiationMessage>
     */
    public function findByNegotiationId(int $negotiationId): array;

    /**
     * Messages after a given id, oldest first — backs M7's polling
     * endpoint ("what's new since I last checked").
     *
     * @return list<NegotiationMessage>
     */
    public function findAfter(int $negotiationId, int $afterMessageId): array;

    public function save(NegotiationMessage $message): NegotiationMessage;
}
