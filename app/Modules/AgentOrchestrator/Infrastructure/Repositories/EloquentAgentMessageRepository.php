<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentMessage as AgentMessageEntity;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentMessageRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageStatus;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageType;
use App\Modules\AgentOrchestrator\Infrastructure\Models\AgentMessage as AgentMessageModel;

/**
 * `save()` upserts by the Entity's own `id()`, the same shape
 * `EloquentExecutionPatternRepository::save()` already establishes
 * (§7.29) — in practice every `AgentMessage` this stage is built fully
 * in-memory (`AgentCommunicationService` mutates it to its final `status`
 * before ever calling `save()` once), so only the insert branch is ever
 * actually exercised today; the update branch exists for the same
 * "structurally ready for a future flow that re-saves an existing
 * message" reason `AgentMessage`'s own mutators exist at all.
 */
class EloquentAgentMessageRepository implements AgentMessageRepositoryInterface
{
    public function save(AgentMessageEntity $message): void
    {
        if ($message->id() === null) {
            $model = new AgentMessageModel();
            $this->fill($model, $message);
            $model->save();

            $message->assignId($model->id);

            return;
        }

        $model = AgentMessageModel::query()->findOrFail($message->id());
        $this->fill($model, $message);
        $model->save();
    }

    public function findForAgent(int $tenantId, AgentType $agentType, int $limit): array
    {
        $models = AgentMessageModel::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($agentType) {
                $query->where('from_agent_type', $agentType->value)
                    ->orWhere('to_agent_type', $agentType->value);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $models->map(fn (AgentMessageModel $model) => $this->toEntity($model))->all();
    }

    private function fill(AgentMessageModel $model, AgentMessageEntity $message): void
    {
        $model->tenant_id = $message->tenantId;
        $model->from_agent_type = $message->fromAgentType->value;
        $model->to_agent_type = $message->toAgentType->value;
        $model->message_type = $message->messageType->value;
        $model->content = $message->content();
        $model->status = $message->status()->value;
        $model->parent_execution_id = $message->parentExecutionId;
        $model->processed_at = $message->processedAt();
    }

    private function toEntity(AgentMessageModel $model): AgentMessageEntity
    {
        return AgentMessageEntity::reconstruct(
            id: $model->id,
            tenantId: $model->tenant_id,
            fromAgentType: AgentType::from($model->from_agent_type),
            toAgentType: AgentType::from($model->to_agent_type),
            messageType: MessageType::from($model->message_type),
            content: $model->content ?? [],
            status: MessageStatus::from($model->status),
            parentExecutionId: $model->parent_execution_id,
            createdAt: $model->created_at->toDateTimeImmutable(),
            processedAt: $model->processed_at?->toDateTimeImmutable(),
        );
    }
}
