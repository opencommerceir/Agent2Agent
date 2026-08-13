<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Developer\Application\Actions\IssueApiKeyAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.developer.api-keys.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_index_rendersOwnKeysOnly(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');
        $other = $this->verifiedBusiness('Other Co');
        app(IssueApiKeyAction::class)->execute($owner->business_id, 'Mine', [ApiKeyScope::CatalogRead]);
        app(IssueApiKeyAction::class)->execute($other->id, 'Not mine', [ApiKeyScope::CatalogRead]);

        $response = $this->actingAs($owner, 'business')->get(route('nexus.developer.api-keys.index'));

        $response->assertOk();
        $response->assertViewHas('apiKeys', fn ($apiKeys) => count($apiKeys) === 1 && $apiKeys[0]->label === 'Mine');
    }

    public function test_store_issuesKeyAndFlashesPlainKeyOnce(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $response = $this->actingAs($owner, 'business')->post(route('nexus.developer.api-keys.store'), [
            'label' => 'CI integration',
            'scopes' => ['catalog.read', 'credit.read'],
        ]);

        $response->assertRedirect(route('nexus.developer.api-keys.index'));
        $response->assertSessionHas('plain_api_key');
        $this->assertDatabaseHas('nexus_api_keys', ['business_id' => $owner->business_id, 'label' => 'CI integration']);

        // The flash is available on the very next request only.
        $follow = $this->actingAs($owner, 'business')->get(route('nexus.developer.api-keys.index'));
        $follow->assertViewHas('plainKey', fn ($plainKey) => str_starts_with($plainKey, 'nx_'));

        $again = $this->actingAs($owner, 'business')->get(route('nexus.developer.api-keys.index'));
        $again->assertViewHas('plainKey', null);
    }

    public function test_store_requiresAtLeastOneScope(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $response = $this->actingAs($owner, 'business')->post(route('nexus.developer.api-keys.store'), [
            'label' => 'No scopes',
            'scopes' => [],
        ]);

        $response->assertSessionHasErrors('scopes');
    }

    public function test_revoke_ownKey_marksRevoked(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');
        $result = app(IssueApiKeyAction::class)->execute($owner->business_id, null, [ApiKeyScope::CatalogRead]);

        $response = $this->actingAs($owner, 'business')->post(route('nexus.developer.api-keys.revoke', $result['apiKey']->id));

        $response->assertRedirect(route('nexus.developer.api-keys.index'));
        $this->assertDatabaseHas('nexus_api_keys', ['id' => $result['apiKey']->id]);
        $this->assertNotNull(\App\Domains\Nexus\Developer\Infrastructure\Models\ApiKey::query()->find($result['apiKey']->id)->revoked_at);
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
