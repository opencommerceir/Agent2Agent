<?php

namespace App\Modules\CRM\Domain\Entities;

use DateTimeImmutable;

/**
 * A free-text annotation an Agent leaves on a Customer (Commerce module,
 * referenced only by id — same cross-module boundary reasoning as
 * Ticket). Immutable — notes are an append-only log, never edited.
 */
final class CustomerNote
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $customerId,
        private readonly int $agentId,
        private readonly string $content,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $tenantId, int $customerId, int $agentId, string $content): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            customerId: $customerId,
            agentId: $agentId,
            content: $content,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function customerId(): int
    {
        return $this->customerId;
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
