<?php

namespace Tests\Unit\Nexus\Developer;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookSubscription;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class WebhookSubscriptionTest extends TestCase
{
    public function test_create_startsUnrevoked(): void
    {
        $subscription = WebhookSubscription::create(1, 'https://example.com/hook', 'secret', [WebhookEvent::NegotiationAccepted]);

        $this->assertNull($subscription->id());
        $this->assertFalse($subscription->isRevoked());
    }

    public function test_isSubscribedTo_matchingEvent_returnsTrue(): void
    {
        $subscription = WebhookSubscription::create(1, 'https://example.com/hook', 'secret', [WebhookEvent::EscrowReleased]);

        $this->assertTrue($subscription->isSubscribedTo(WebhookEvent::EscrowReleased));
        $this->assertFalse($subscription->isSubscribedTo(WebhookEvent::ContractGenerated));
    }

    public function test_isSubscribedTo_afterRevoke_alwaysFalse(): void
    {
        $subscription = WebhookSubscription::create(1, 'https://example.com/hook', 'secret', [WebhookEvent::EscrowReleased]);

        $subscription->revoke();

        $this->assertFalse($subscription->isSubscribedTo(WebhookEvent::EscrowReleased));
        $this->assertTrue($subscription->isRevoked());
        $this->assertInstanceOf(DateTimeImmutable::class, $subscription->revokedAt());
    }
}
