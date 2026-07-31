<?php

namespace App\Modules\CRM\Application\DTOs;

use App\Modules\CRM\Domain\Entities\TicketComment;

/**
 * Structured data transfer for TicketComment across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class TicketCommentData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $ticketId,
        public readonly int $agentId,
        public readonly string $content,
    ) {
    }

    public static function fromEntity(TicketComment $comment): self
    {
        return new self(
            id: $comment->id(),
            ticketId: $comment->ticketId(),
            agentId: $comment->agentId(),
            content: $comment->content(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ticketId' => $this->ticketId,
            'agentId' => $this->agentId,
            'content' => $this->content,
        ];
    }
}
