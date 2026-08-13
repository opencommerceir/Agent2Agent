<?php

namespace Tests\Feature\Nexus\PrivateMarketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\CreatePrivateMarketplaceAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateMarketplaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_createsMarketplaceAndRedirects(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Owner Co');

        $response = $this->actingAs($owner['owner'], 'business')->post(route('nexus.private-marketplace.store'), [
            'name_fa' => 'الف',
            'name_en' => 'Alpha Market',
            'branding_primary_color' => '#00F0FF',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nexus_private_marketplaces', ['owner_business_id' => $owner['business']->id]);
    }

    public function test_show_byNonMember_isForbidden(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Owner Co');
        $outsider = $this->verifiedBusinessWithOwner('Outsider Co');
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner['business']->id, 'الف', 'Alpha Market');

        $response = $this->actingAs($outsider['owner'], 'business')->get(route('nexus.private-marketplace.show', $marketplace->id));

        $response->assertForbidden();
    }

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.private-marketplace.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }

    private function verifiedBusinessWithOwner(string $nameEn): array
    {
        $business = $this->verifiedBusiness($nameEn);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => strtolower(str_replace(' ', '', $nameEn)).'@example.com',
            'password' => 'password123',
        ]);

        return ['business' => $business, 'owner' => $owner];
    }
}
