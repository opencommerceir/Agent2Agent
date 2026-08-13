<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\SetDataResidencyRegionAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\DataResidencyRegion;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7/M10 — Data Residency preference (declared, not enforced — see
 * DataResidencyRegion's own docblock).
 */
class DataResidencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_newBusiness_hasNoDeclaredRegionByDefault(): void
    {
        $business = $this->registerBusiness('Buyer Co');

        $this->assertNull($business->dataResidencyRegion);
    }

    public function test_setDataResidencyRegion_persistsTheDeclaredRegion(): void
    {
        $business = $this->registerBusiness('Buyer Co');

        $updated = app(SetDataResidencyRegionAction::class)->execute($business->id, DataResidencyRegion::EU);

        $this->assertSame('eu', $updated->dataResidencyRegion);
        $fromRepository = app(BusinessRepositoryInterface::class)->findById($business->id);
        $this->assertSame(DataResidencyRegion::EU, $fromRepository->dataResidencyRegion());
    }

    public function test_setDataResidencyRegion_canBeChangedLater(): void
    {
        $business = $this->registerBusiness('Buyer Co');
        app(SetDataResidencyRegionAction::class)->execute($business->id, DataResidencyRegion::Iran);

        $updated = app(SetDataResidencyRegionAction::class)->execute($business->id, DataResidencyRegion::GCC);

        $this->assertSame('gcc', $updated->dataResidencyRegion);
    }

    public function test_businessOwner_canDeclareRegion_viaDashboardForm(): void
    {
        $business = $this->registerBusiness('Buyer Co');
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Buyer Owner',
            'email' => 'buyer-'.uniqid().'@example.com',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($owner, 'business')->post(route('nexus.business.dashboard.data-residency'), [
            'data_residency_region' => 'us',
        ]);

        $response->assertRedirect(route('nexus.business.dashboard'));
        $updated = app(BusinessRepositoryInterface::class)->findById($business->id);
        $this->assertSame(DataResidencyRegion::US, $updated->dataResidencyRegion());
    }

    private function registerBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }
}
