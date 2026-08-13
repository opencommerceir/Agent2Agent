<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Developer\Application\Actions\CreateWebhookSubscriptionAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $this->get(route('nexus.developer.webhooks.index'))
            ->assertRedirect(route('nexus.business.login'));
    }

    public function test_store_createsSubscriptionAndFlashesSecretOnce(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $response = $this->actingAs($owner, 'business')->post(route('nexus.developer.webhooks.store'), [
            'url' => 'https://example.com/hook',
            'events' => ['negotiation.accepted'],
        ]);

        $response->assertRedirect(route('nexus.developer.webhooks.index'));
        $response->assertSessionHas('plain_webhook_secret');
        $this->assertDatabaseHas('nexus_webhook_subscriptions', ['business_id' => $owner->business_id, 'url' => 'https://example.com/hook']);
    }

    public function test_store_rejectsInvalidUrl(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $this->actingAs($owner, 'business')->post(route('nexus.developer.webhooks.store'), [
            'url' => 'not-a-url',
            'events' => ['negotiation.accepted'],
        ])->assertSessionHasErrors('url');
    }

    public function test_revoke_ownSubscription_marksRevoked(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');
        $result = app(CreateWebhookSubscriptionAction::class)->execute($owner->business_id, 'https://example.com/hook', [WebhookEvent::EscrowReleased]);

        $response = $this->actingAs($owner, 'business')->post(route('nexus.developer.webhooks.revoke', $result['subscription']->id));

        $response->assertRedirect(route('nexus.developer.webhooks.index'));
        $this->assertNotNull(\App\Domains\Nexus\Developer\Infrastructure\Models\WebhookSubscription::query()->find($result['subscription']->id)->revoked_at);
    }

    private function verifiedBusinessWithOwner(string $nameEn): BusinessOwner
    {
        $business = $this->verifiedBusiness($nameEn);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => strtolower(str_replace(' ', '', $nameEn)).'@example.com',
            'password' => 'password123',
        ]);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }
}
