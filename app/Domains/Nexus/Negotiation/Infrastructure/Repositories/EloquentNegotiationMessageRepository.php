<?php

namespace App\Domains\Nexus\Negotiation\Infrastructure\Repositories;

use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage as NegotiationMessageEntity;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationMessageType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Negotiation\Infrastructure\Models\NegotiationMessage as NegotiationMessageModel;
use DateTimeImmutable;

class EloquentNegotiationMessageRepository implements NegotiationMessageRepositoryInterface
{
    public function findByNegotiationId(int $negotiationId): array
    {
        return NegotiationMessageModel::query()
            ->where('negotiation_id', $negotiationId)
            ->orderBy('id')
            ->get()
            ->map(fn (NegotiationMessageModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findAfter(int $negotiationId, int $afterMessageId): array
    {
        return NegotiationMessageModel::query()
            ->where('negotiation_id', $negotiationId)
            ->where('id', '>', $afterMessageId)
            ->orderBy('id')
            ->get()
            ->map(fn (NegotiationMessageModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(NegotiationMessageEntity $message): NegotiationMessageEntity
    {
        $model = new NegotiationMessageModel();
        $model->negotiation_id = $message->negotiationId();
        $model->sender_business_id = $message->senderBusinessId();
        $model->type = $message->type()->value;
        $model->terms = $message->terms()->toArray();
        $model->reasoning = $message->reasoning();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NegotiationMessageModel $model): NegotiationMessageEntity
    {
        return new NegotiationMessageEntity(
            id: $model->id,
            negotiationId: $model->negotiation_id,
            senderBusinessId: $model->sender_business_id,
            type: NegotiationMessageType::from($model->type),
            terms: NegotiationTerms::fromArray($model->terms),
            reasoning: $model->reasoning,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
