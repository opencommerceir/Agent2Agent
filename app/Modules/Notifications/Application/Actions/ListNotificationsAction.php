<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Application\DTOs\NotificationData;
use App\Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\DeliveryStatus;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;

final class ListNotificationsAction
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{notifications: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $type = isset($input['type']) ? NotificationType::from($input['type']) : null;
        $status = isset($input['status']) ? DeliveryStatus::from($input['status']) : null;
        $limit = (int) ($input['limit'] ?? self::DEFAULT_LIMIT);

        $notifications = $this->notifications->list($tenantId, $type, $status, $limit);

        return [
            'notifications' => array_map(fn ($notification) => NotificationData::fromEntity($notification)->toArray(), $notifications),
        ];
    }
}
