<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $this->get(route('nexus.developer.integrations.index'))
            ->assertRedirect(route('nexus.business.login'));
    }

    public function test_store_createsConnectionWithFieldMapping(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $response = $this->actingAs($owner, 'business')->post(route('nexus.developer.integrations.store'), [
            'category' => 'crm',
            'name' => 'My CRM',
            'target_url' => 'https://crm.example.com/contacts',
            'auth_token' => 'token-123',
            'mapping_source' => ['nameEn', ''],
            'mapping_target' => ['company_name', ''],
        ]);

        $response->assertRedirect(route('nexus.developer.integrations.index'));
        $this->assertDatabaseHas('nexus_integration_connections', ['business_id' => $owner->business_id, 'name' => 'My CRM']);
    }

    public function test_store_rejectsInvalidUrl(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $this->actingAs($owner, 'business')->post(route('nexus.developer.integrations.store'), [
            'category' => 'crm',
            'name' => 'My CRM',
            'target_url' => 'not-a-url',
        ])->assertSessionHasErrors('target_url');
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
