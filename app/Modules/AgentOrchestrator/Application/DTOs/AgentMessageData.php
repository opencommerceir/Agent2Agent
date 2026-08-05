<?php

namespace App\Modules\AgentOrchestrator\Application\DTOs;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentMessage;

final class AgentMessageData
{
    /**
     * @param array<string, mixed> $content
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $fromAgentType,
        public readonly string $toAgentType,
        public readonly string $messageType,
        public readonly array $content,
        public readonly string $status,
        public readonly ?int $parentExecutionId,
        public readonly string $createdAt,
        public readonly ?string $processedAt,
    ) {
    }

    public static function fromEntity(AgentMessage $message): self
    {
        return new self(
            id: $message->id(),
            fromAgentType: $message->fromAgentType->value,
            toAgentType: $message->toAgentType->value,
            messageType: $message->messageType->value,
            content: $message->content(),
            status: $message->status()->value,
            parentExecutionId: $message->parentExecutionId,
            createdAt: $message->createdAt->format(DATE_ATOM),
            processedAt: $message->processedAt()?->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: ?int, from_agent_type: string, to_agent_type: string, message_type: string, content: array, status: string, parent_execution_id: ?int, created_at: string, processed_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'from_agent_type' => $this->fromAgentType,
            'to_agent_type' => $this->toAgentType,
            'message_type' => $this->messageType,
            'content' => $this->content,
            'status' => $this->status,
            'parent_execution_id' => $this->parentExecutionId,
            'created_at' => $this->createdAt,
            'processed_at' => $this->processedAt,
        ];
    }
}
