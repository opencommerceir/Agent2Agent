<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\CreateCoalitionAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoalitionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_createsCoalitionAndRedirectsToShow(): void
    {
        $organizer = $this->verifiedBusinessWithOwner('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');

        $response = $this->actingAs($organizer['owner'], 'business')->post(route('nexus.growth.coalitions.store'), [
            'target_business_id' => $target->id,
            'catalog_item_type' => 'product',
            'catalog_item_id' => 1,
            'unit_price_amount' => 10000,
            'unit_price_currency' => 'IRT',
            'min_participants' => 2,
            'discount_percent' => 10,
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nexus_coalitions', ['organizer_business_id' => $organizer['business']->id]);
    }

    public function test_show_displaysCoalitionDetails(): void
    {
        $organizer = $this->verifiedBusinessWithOwner('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer['business']->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 2, 10.0, 5,
        );

        $response = $this->actingAs($organizer['owner'], 'business')->get(route('nexus.growth.coalitions.show', $coalition->id));

        $response->assertOk();
        $response->assertSee('10%');
    }

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.growth.coalitions.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        if ($credits > 0) {
            app(GrantCreditsAction::class)->execute($business->id, $credits, CreditTransactionType::AdminGrant, 'test.seed');
        }

        return $business;
    }

    private function verifiedBusinessWithOwner(string $nameEn, int $credits = 0): array
    {
        $business = $this->verifiedBusiness($nameEn, $credits);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => strtolower(str_replace(' ', '', $nameEn)).'@example.com',
            'password' => 'password123',
        ]);

        return ['business' => $business, 'owner' => $owner];
    }
}
