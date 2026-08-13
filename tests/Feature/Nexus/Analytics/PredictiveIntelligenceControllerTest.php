<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictiveIntelligenceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.analytics.predictive'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_index_rendersForecastForOwnBusinessByDefault(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.analytics.predictive'));

        $response->assertOk();
        $response->assertViewHas('forecast', fn ($forecast) => $forecast['businessId'] === $owner->business_id);
        $response->assertViewHas('risk', null);
        $response->assertViewHas('scenario', null);
    }

    public function test_index_withRiskParams_loadsRisk(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');
        $target = $this->verifiedBusiness('Target Co');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.analytics.predictive', [
            'risk_for' => $target->id,
            'deal_amount' => 10000,
        ]));

        $response->assertOk();
        $response->assertViewHas('risk');
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
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
