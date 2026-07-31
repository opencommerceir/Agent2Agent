<?php

namespace App\Modules\CRM\Domain\Entities;

use App\Modules\CRM\Domain\Exceptions\InvalidTicketStatusException;
use App\Modules\CRM\Domain\ValueObjects\TicketPriority;
use App\Modules\CRM\Domain\ValueObjects\TicketStatus;
use DateTimeImmutable;

/**
 * A support Ticket raised for a Customer (Commerce module, referenced
 * only by id — see CreateTicketAction's docblock for why CRM never
 * imports Commerce's Customer entity directly). Comments are a separate
 * child entity (TicketComment) persisted through this same aggregate's
 * TicketRepositoryInterface, the same "no comment ever exists without a
 * loaded Ticket" reasoning OrderItem has relative to Order — but unlike
 * OrderItem (frozen at construction), comments accumulate over the
 * Ticket's lifetime, so they are not held as an in-memory list here.
 */
final class Ticket
{
    /**
     * Declaration order is the only allowed forward direction —
     * changeStatus() rejects any target whose index isn't strictly
     * greater than the current status's index, enforcing "open ->
     * in_progress -> resolved -> closed, no regression" without a
     * separate transition table to keep in sync with the enum.
     */
    private const SEQUENCE = [
        TicketStatus::Open,
        TicketStatus::InProgress,
        TicketStatus::Resolved,
        TicketStatus::Closed,
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $customerId,
        private readonly int $agentId,
        private string $subject,
        private string $description,
        private TicketStatus $status,
        private TicketPriority $priority,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        int $customerId,
        int $agentId,
        string $subject,
        string $description,
        TicketPriority $priority = TicketPriority::Medium,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            customerId: $customerId,
            agentId: $agentId,
            subject: $subject,
            description: $description,
            status: TicketStatus::Open,
            priority: $priority,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Only a strictly-forward move within SEQUENCE is allowed — the same
     * status is rejected too (not a meaningful transition), and there is
     * no path back to an earlier status once left (per this module's
     * explicit "no regression" rule, stricter than Order's, which at
     * least allows staying within its non-terminal states in any order).
     */
    public function changeStatus(TicketStatus $newStatus): void
    {
        $currentIndex = array_search($this->status, self::SEQUENCE, true);
        $newIndex = array_search($newStatus, self::SEQUENCE, true);

        if ($newIndex <= $currentIndex) {
            throw new InvalidTicketStatusException(
                "Ticket cannot move from status [{$this->status->value}] to [{$newStatus->value}] — only a forward transition is allowed."
            );
        }

        $this->status = $newStatus;
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

    public function subject(): string
    {
        return $this->subject;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }

    public function priority(): TicketPriority
    {
        return $this->priority;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
