<?php

namespace App\Modules\CRM\Application\DTOs;

use App\Modules\CRM\Domain\Entities\Ticket;

/**
 * Structured data transfer for Ticket across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class TicketData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $customerId,
        public readonly int $agentId,
        public readonly string $subject,
        public readonly string $description,
        public readonly string $status,
        public readonly string $priority,
    ) {
    }

    public static function fromEntity(Ticket $ticket): self
    {
        return new self(
            id: $ticket->id(),
            tenantId: $ticket->tenantId(),
            customerId: $ticket->customerId(),
            agentId: $ticket->agentId(),
            subject: $ticket->subject(),
            description: $ticket->description(),
            status: $ticket->status()->value,
            priority: $ticket->priority()->value,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'customerId' => $this->customerId,
            'agentId' => $this->agentId,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
        ];
    }
}
