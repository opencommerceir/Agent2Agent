<?php

namespace App\Modules\Notifications\Domain\Repositories;

use App\Modules\Notifications\Domain\Entities\NotificationPreference;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;

/**
 * Not in this stage's original request (which named only 3 Repository
 * interfaces) — added unprompted for the same reason Commerce Stage 5's
 * DiscountRepositoryInterface/CRM's TagNotFoundException/Shipping's
 * OrderNotFoundException each were: NotificationPreference has its own
 * Entity, migration, and MCP capability but no named Repository, which
 * would have meant bypassing the Repository convention every other
 * aggregate in this codebase follows.
 */
interface NotificationPreferenceRepositoryInterface
{
    public function find(
        int $tenantId,
        RecipientType $recipientType,
        int $recipientId,
        NotificationType $notificationType,
        ChannelType $channelType,
    ): ?NotificationPreference;

    /**
     * Upserts by the same 4-part key the table's own unique constraint
     * enforces — SetUserPreferenceAction always "sets" the current value
     * rather than requiring a separate create-then-update flow.
     */
    public function save(NotificationPreference $preference): NotificationPreference;
}
