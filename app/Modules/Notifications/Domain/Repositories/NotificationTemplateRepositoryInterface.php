<?php

namespace App\Modules\Notifications\Domain\Repositories;

use App\Modules\Notifications\Domain\Entities\NotificationTemplate;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;

interface NotificationTemplateRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?NotificationTemplate;

    /**
     * The lookup every event Listener uses: "is there an active template
     * configured for this type+channel". Returns null (not an exception)
     * when none exists — a Listener treats that as "tenant hasn't opted
     * into this notification," not an error.
     */
    public function findActive(int $tenantId, NotificationType $type, ChannelType $channelType): ?NotificationTemplate;

    /**
     * @return list<NotificationTemplate>
     */
    public function list(int $tenantId, ?NotificationType $type, ?ChannelType $channelType): array;

    public function save(NotificationTemplate $template): NotificationTemplate;
}
