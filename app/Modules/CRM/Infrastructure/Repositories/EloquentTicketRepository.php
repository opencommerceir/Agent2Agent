<?php

namespace App\Modules\CRM\Infrastructure\Repositories;

use App\Modules\CRM\Domain\Entities\Ticket as TicketEntity;
use App\Modules\CRM\Domain\Entities\TicketComment as TicketCommentEntity;
use App\Modules\CRM\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\CRM\Domain\ValueObjects\TicketPriority;
use App\Modules\CRM\Domain\ValueObjects\TicketStatus;
use App\Modules\CRM\Infrastructure\Models\Ticket as TicketModel;
use App\Modules\CRM\Infrastructure\Models\TicketComment as TicketCommentModel;
use DateTimeImmutable;

class EloquentTicketRepository implements TicketRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?TicketEntity
    {
        $model = TicketModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function list(int $tenantId, ?TicketStatus $status, ?int $customerId, int $limit): array
    {
        $builder = TicketModel::query()->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        if ($customerId !== null) {
            $builder->where('customer_id', $customerId);
        }

        return $builder->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (TicketModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(TicketEntity $ticket): TicketEntity
    {
        $model = $ticket->id()
            ? TicketModel::query()->where('tenant_id', $ticket->tenantId())->findOrFail($ticket->id())
            : new TicketModel();

        $model->tenant_id = $ticket->tenantId();
        $model->customer_id = $ticket->customerId();
        $model->agent_id = $ticket->agentId();
        $model->subject = $ticket->subject();
        $model->description = $ticket->description();
        $model->status = $ticket->status()->value;
        $model->priority = $ticket->priority()->value;
        $model->save();

        return $this->toEntity($model);
    }

    public function addComment(TicketCommentEntity $comment): TicketCommentEntity
    {
        $model = new TicketCommentModel();
        $model->ticket_id = $comment->ticketId();
        $model->agent_id = $comment->agentId();
        $model->content = $comment->content();
        $model->save();

        return $this->toCommentEntity($model);
    }

    private function toEntity(TicketModel $model): TicketEntity
    {
        return new TicketEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            customerId: $model->customer_id,
            agentId: $model->agent_id,
            subject: $model->subject,
            description: $model->description,
            status: TicketStatus::from($model->status),
            priority: TicketPriority::from($model->priority),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    private function toCommentEntity(TicketCommentModel $model): TicketCommentEntity
    {
        return new TicketCommentEntity(
            id: $model->id,
            ticketId: $model->ticket_id,
            agentId: $model->agent_id,
            content: $model->content,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
