<?php

namespace App\Modules\Notifications\Domain\Repositories;

use App\Core\Domain\ValueObjects\Language;
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
     *
     * $language (Phase 4 Stage 4, i18n): tries an exact (tenant, type,
     * channel, language) match first; if none is active and $language
     * isn't already English, retries once against English before giving
     * up and returning null. This is the one place the "fallback to
     * English" rule lives for Templates — every caller (3 Listeners + the
     * notification.message.send MCP handler) gets it for free rather than
     * re-implementing the same two-step lookup.
     */
    public function findActive(
        int $tenantId,
        NotificationType $type,
        ChannelType $channelType,
        Language $language = Language::English,
    ): ?NotificationTemplate;

    /**
     * @return list<NotificationTemplate>
     */
    public function list(int $tenantId, ?NotificationType $type, ?ChannelType $channelType): array;

    public function save(NotificationTemplate $template): NotificationTemplate;
}
