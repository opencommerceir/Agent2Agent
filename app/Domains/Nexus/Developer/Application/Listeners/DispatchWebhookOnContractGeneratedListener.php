<?php

namespace App\Domains\Nexus\Developer\Application\Listeners;

use App\Domains\Nexus\Contract\Application\DTOs\ContractData;
use App\Domains\Nexus\Contract\Domain\Events\ContractWasGenerated;
use App\Domains\Nexus\Developer\Application\Actions\DispatchWebhookEventAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;

/**
 * Notifies both parties' webhook subscriptions (if any) that a Contract
 * was generated — same event-driven shape HoldEscrowOnContractGeneratedListener
 * (Phase 3/M4) already established for this exact event.
 */
final class DispatchWebhookOnContractGeneratedListener
{
    public function __construct(
        private readonly DispatchWebhookEventAction $dispatchWebhook,
    ) {
    }

    public function handle(ContractWasGenerated $event): void
    {
        $payload = ContractData::fromEntity($event->contract)->toArray();

        $this->dispatchWebhook->execute($event->contract->businessAId(), WebhookEvent::ContractGenerated, $payload);
        $this->dispatchWebhook->execute($event->contract->businessBId(), WebhookEvent::ContractGenerated, $payload);
    }
}
