<?php

namespace App\Domains\Nexus\Developer\Application\Listeners;

use App\Domains\Nexus\Developer\Application\Actions\DispatchWebhookEventAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;

/**
 * Notifies both parties' webhook subscriptions (if any) that their
 * Negotiation was accepted — same event-driven, no-direct-call shape
 * GenerateContractOnNegotiationAcceptedListener (Phase 2/M6) already
 * established for this exact event.
 */
final class DispatchWebhookOnNegotiationAcceptedListener
{
    public function __construct(
        private readonly DispatchWebhookEventAction $dispatchWebhook,
    ) {
    }

    public function handle(NegotiationWasAccepted $event): void
    {
        $payload = NegotiationData::fromEntity($event->negotiation)->toArray();

        $this->dispatchWebhook->execute($event->negotiation->initiatorBusinessId(), WebhookEvent::NegotiationAccepted, $payload);
        $this->dispatchWebhook->execute($event->negotiation->counterpartyBusinessId(), WebhookEvent::NegotiationAccepted, $payload);
    }
}
