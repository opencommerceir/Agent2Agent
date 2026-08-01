<?php

namespace App\Modules\Notifications\Domain\Repositories;

use App\Modules\Notifications\Domain\Entities\Notification;
use App\Modules\Notifications\Domain\ValueObjects\DeliveryStatus;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;

interface NotificationRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Notification;

    /**
     * @return list<Notification>
     */
    public function list(int $tenantId, ?NotificationType $type, ?DeliveryStatus $status, int $limit): array;

    public function save(Notification $notification): Notification;
}
