<?php

namespace App\Modules\CRM\Application\DTOs;

use App\Modules\CRM\Domain\Entities\CustomerNote;

/**
 * Structured data transfer for CustomerNote across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class CustomerNoteData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $customerId,
        public readonly int $agentId,
        public readonly string $content,
    ) {
    }

    public static function fromEntity(CustomerNote $note): self
    {
        return new self(
            id: $note->id(),
            tenantId: $note->tenantId(),
            customerId: $note->customerId(),
            agentId: $note->agentId(),
            content: $note->content(),
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
            'content' => $this->content,
        ];
    }
}
