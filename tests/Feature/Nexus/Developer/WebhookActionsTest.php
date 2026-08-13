<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Developer\Application\Actions\CreateWebhookSubscriptionAction;
use App\Domains\Nexus\Developer\Application\Actions\ListWebhookSubscriptionsAction;
use App\Domains\Nexus\Developer\Application\Actions\RevokeWebhookSubscriptionAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WebhookActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_returnsSecretOnceAndPersistsEncrypted(): void
    {
        $business = $this->verifiedBusiness('Caller Co');

        $result = app(CreateWebhookSubscriptionAction::class)->execute(
            $business->id, 'https://example.com/hook', [WebhookEvent::NegotiationAccepted],
        );

        $this->assertSame(64, strlen($result['secret']));
        $this->assertSame(['negotiation.accepted'], $result['subscription']->events);
        $this->assertDatabaseHas('nexus_webhook_subscriptions', ['business_id' => $business->id, 'url' => 'https://example.com/hook']);
        $this->assertDatabaseMissing('nexus_webhook_subscriptions', ['secret' => $result['secret']]);
    }

    public function test_list_returnsOnlyOwnSubscriptions(): void
    {
        $businessA = $this->verifiedBusiness('Business A');
        $businessB = $this->verifiedBusiness('Business B');
        app(CreateWebhookSubscriptionAction::class)->execute($businessA->id, 'https://a.example.com/hook', [WebhookEvent::EscrowReleased]);
        app(CreateWebhookSubscriptionAction::class)->execute($businessB->id, 'https://b.example.com/hook', [WebhookEvent::EscrowReleased]);

        $subscriptions = app(ListWebhookSubscriptionsAction::class)->execute($businessA->id);

        $this->assertCount(1, $subscriptions);
        $this->assertSame('https://a.example.com/hook', $subscriptions[0]->url);
    }

    public function test_revoke_ownSubscription_succeeds(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $result = app(CreateWebhookSubscriptionAction::class)->execute($business->id, 'https://example.com/hook', [WebhookEvent::ContractGenerated]);

        app(RevokeWebhookSubscriptionAction::class)->execute($result['subscription']->id, $business->id);

        $this->assertTrue(app(ListWebhookSubscriptionsAction::class)->execute($business->id)[0]->isRevoked);
    }

    public function test_revoke_someoneElsesSubscription_throws(): void
    {
        $owner = $this->verifiedBusiness('Owner Co');
        $intruder = $this->verifiedBusiness('Intruder Co');
        $result = app(CreateWebhookSubscriptionAction::class)->execute($owner->id, 'https://example.com/hook', [WebhookEvent::ContractGenerated]);

        $this->expectException(InvalidArgumentException::class);

        app(RevokeWebhookSubscriptionAction::class)->execute($result['subscription']->id, $intruder->id);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }
}
