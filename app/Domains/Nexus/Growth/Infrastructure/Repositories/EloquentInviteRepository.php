<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\Invite as InviteEntity;
use App\Domains\Nexus\Growth\Domain\Repositories\InviteRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\ValueObjects\InviteStatus;
use App\Domains\Nexus\Growth\Infrastructure\Models\Invite as InviteModel;
use DateTimeImmutable;

class EloquentInviteRepository implements InviteRepositoryInterface
{
    public function findById(int $id): ?InviteEntity
    {
        $model = InviteModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findOldestUnconvertedByReferralCode(string $referralCode): ?InviteEntity
    {
        $model = InviteModel::query()
            ->where('referral_code', $referralCode)
            ->where('status', InviteStatus::Sent->value)
            ->oldest('id')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByInviterId(int $inviterBusinessId): array
    {
        return InviteModel::query()
            ->where('inviter_business_id', $inviterBusinessId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (InviteModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(InviteEntity $invite): InviteEntity
    {
        $model = $invite->id()
            ? InviteModel::query()->findOrFail($invite->id())
            : new InviteModel();

        $model->inviter_business_id = $invite->inviterBusinessId();
        $model->invitee_name = $invite->inviteeName();
        $model->invitee_email = $invite->inviteeEmail();
        $model->referral_code = $invite->referralCode();
        $model->message_variant = $invite->messageVariant();
        $model->status = $invite->status()->value;
        $model->converted_business_id = $invite->convertedBusinessId();
        $model->converted_at = $invite->convertedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(InviteModel $model): InviteEntity
    {
        return new InviteEntity(
            id: $model->id,
            inviterBusinessId: $model->inviter_business_id,
            inviteeName: $model->invitee_name,
            inviteeEmail: $model->invitee_email,
            referralCode: $model->referral_code,
            messageVariant: $model->message_variant,
            status: InviteStatus::from($model->status),
            convertedBusinessId: $model->converted_business_id,
            convertedAt: $model->converted_at ? DateTimeImmutable::createFromInterface($model->converted_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
