<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageStatus;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageType;
use DateTimeImmutable;
use LogicException;

/**
 * A durable log entry of one persona-to-persona communication (Phase 6,
 * Stage 5, §7.30) — the audit trail `AgentCommunicationService` writes
 * around every delegation, independent of `DelegationRequest`'s own
 * work-tracking row (see that Entity's own docblock for the split).
 * `fromAgentType`/`toAgentType` are Orchestrator personas
 * (`ceo`/`sales`/`support`/`finance`), never a real `Agent` identity —
 * see `docs/multi-agent-collaboration.md`'s own "Personas are not
 * identities" section before assuming otherwise.
 */
final class AgentMessage
{
    private ?int $id;

    private MessageStatus $status;

    private ?DateTimeImmutable $processedAt = null;

    /**
     * @param array<string, mixed> $content
     */
    private function __construct(
        ?int $id,
        public readonly int $tenantId,
        public readonly AgentType $fromAgentType,
        public readonly AgentType $toAgentType,
        public readonly MessageType $messageType,
        private readonly array $content,
        MessageStatus $status,
        public readonly ?int $parentExecutionId,
        public readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->status = $status;
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function create(
        int $tenantId,
        AgentType $fromAgentType,
        AgentType $toAgentType,
        MessageType $messageType,
        array $content,
        ?int $parentExecutionId,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            fromAgentType: $fromAgentType,
            toAgentType: $toAgentType,
            messageType: $messageType,
            content: $content,
            status: MessageStatus::Pending,
            parentExecutionId: $parentExecutionId,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function reconstruct(
        int $id,
        int $tenantId,
        AgentType $fromAgentType,
        AgentType $toAgentType,
        MessageType $messageType,
        array $content,
        MessageStatus $status,
        ?int $parentExecutionId,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $processedAt,
    ): self {
        $message = new self(
            id: $id,
            tenantId: $tenantId,
            fromAgentType: $fromAgentType,
            toAgentType: $toAgentType,
            messageType: $messageType,
            content: $content,
            status: $status,
            parentExecutionId: $parentExecutionId,
            createdAt: $createdAt,
        );
        $message->processedAt = $processedAt;

        return $message;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException("AgentMessage already has id [{$this->id}]; assignId() is one-time only.");
        }

        $this->id = $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return $this->content;
    }

    public function status(): MessageStatus
    {
        return $this->status;
    }

    public function processedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markAsSent(): void
    {
        $this->status = MessageStatus::Sent;
    }

    public function markAsProcessed(): void
    {
        $this->status = MessageStatus::Processed;
        $this->processedAt = new DateTimeImmutable();
    }
}
