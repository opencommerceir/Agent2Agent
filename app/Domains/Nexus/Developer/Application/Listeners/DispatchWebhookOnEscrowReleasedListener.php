<?php

namespace App\Domains\Nexus\Developer\Application\Listeners;

use App\Domains\Nexus\Contract\Application\DTOs\EscrowData;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasReleased;
use App\Domains\Nexus\Developer\Application\Actions\DispatchWebhookEventAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;

/**
 * Notifies both parties' webhook subscriptions (if any) that an Escrow
 * was released — the same "deal genuinely complete" trigger point Phase
 * 6/M1's Reviews & Ratings already uses for this exact event.
 */
final class DispatchWebhookOnEscrowReleasedListener
{
    public function __construct(
        private readonly DispatchWebhookEventAction $dispatchWebhook,
    ) {
    }

    public function handle(EscrowWasReleased $event): void
    {
        $payload = (array) EscrowData::fromEntity($event->escrow);

        $this->dispatchWebhook->execute($event->escrow->businessAId(), WebhookEvent::EscrowReleased, $payload);
        $this->dispatchWebhook->execute($event->escrow->businessBId(), WebhookEvent::EscrowReleased, $payload);
    }
}
