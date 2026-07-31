<?php

namespace App\Modules\CRM\Domain\Entities;

use DateTimeImmutable;

/**
 * A single reply on a Ticket. Immutable — comments are never edited,
 * only added — and, like OrderItem/Discount, carries no separate
 * tenant_id column: tenant isolation is enforced one layer up, by
 * AddCommentToTicketAction loading the parent Ticket with an explicit
 * tenantId before a comment is ever created against it.
 */
final class TicketComment
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $ticketId,
        private readonly int $agentId,
        private readonly string $content,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $ticketId, int $agentId, string $content): self
    {
        return new self(
            id: null,
            ticketId: $ticketId,
            agentId: $agentId,
            content: $content,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function ticketId(): int
    {
        return $this->ticketId;
    }

    public function agentId(): int
    {
        return $this->agentId;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
