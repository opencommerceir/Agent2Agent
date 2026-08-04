<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;

final class ListSubscriptionsAction
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<SubscriptionData>
     */
    public function execute(array $filters, int $tenantId): array
    {
        $status = isset($filters['status']) ? SubscriptionStatus::from($filters['status']) : null;
        $customerId = isset($filters['customer_id']) ? (int) $filters['customer_id'] : null;

        return array_map(
            fn ($subscription) => SubscriptionData::fromEntity($subscription),
            $this->subscriptions->listByTenant($tenantId, $status, $customerId),
        );
    }
}
