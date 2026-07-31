<?php

namespace App\Modules\CRM\Domain\Repositories;

use App\Modules\CRM\Domain\Entities\Ticket;
use App\Modules\CRM\Domain\Entities\TicketComment;
use App\Modules\CRM\Domain\ValueObjects\TicketStatus;

/**
 * Contract owned by the Domain layer (Interfaces Over Tight Coupling).
 * Every method takes tenantId explicitly — never inferred from ambient
 * state — so a caller can never accidentally cross a tenant boundary by
 * omission. Owns TicketComment persistence too (addComment()): a comment
 * has no meaning detached from its Ticket, the same "no separate
 * repository for a child record" reasoning OrderItem has relative to
 * Order's own repository.
 */
interface TicketRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Ticket;

    /**
     * @return list<Ticket>
     */
    public function list(int $tenantId, ?TicketStatus $status, ?int $customerId, int $limit): array;

    public function save(Ticket $ticket): Ticket;

    public function addComment(TicketComment $comment): TicketComment;
}
