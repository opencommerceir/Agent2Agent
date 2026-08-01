<?php

namespace App\Modules\Notifications\Domain\Repositories;

use App\Modules\Notifications\Domain\Entities\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;

interface NotificationChannelRepositoryInterface
{
    public function findByType(int $tenantId, ChannelType $channelType): ?NotificationChannel;

    /**
     * Upserts by (tenantId, channelType) — ConfigureChannelAction's own
     * "configure" verb implies create-or-update, the same
     * find-or-new-then-save shape EloquentInventoryRepository::save()
     * already uses for its own (tenant, product) uniqueness.
     */
    public function save(NotificationChannel $channel): NotificationChannel;
}
