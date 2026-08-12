<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionAppealRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\AddServiceAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);

        return [$business, $owner];
    }

    public function test_index_beforeVerification_showsAgentPendingAndZeroCatalog(): void
    {
        [$business, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner, 'business')->get(route('nexus.business.dashboard'));

        $response->assertOk();
        $response->assertViewHas('agent', null);
        $response->assertViewHas('productCount', 0);
        $response->assertViewHas('serviceCount', 0);
        $response->assertSee(t('messages.nexus.business.dashboard.agent_pending'));
    }

    public function test_index_afterVerificationAndCatalogItems_showsAgentAndCounts(): void
    {
        [$business, $owner] = $this->makeOwner();

        app(VerifyBusinessAction::class)->execute($business->id);
        app(AddProductAction::class)->execute($business->id, 'محصول', 'Product A', 1000, 'IRT');
        app(AddServiceAction::class)->execute($business->id, 'خدمت', 'Service A', 2000, 'IRT');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.business.dashboard'));

        $response->assertOk();
        $response->assertViewHas('productCount', 1);
        $response->assertViewHas('serviceCount', 1);
        $response->assertViewHas('agent', fn ($agent) => $agent !== null && $agent->nameEn() === 'Test Company');
    }

    public function test_index_whileSuspended_showsBannerAndAppealForm(): void
    {
        [$business, $owner] = $this->makeOwner();
        app(VerifyBusinessAction::class)->execute($business->id);
        app(SuspendBusinessAction::class)->execute($business->id, 'test suspension');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.business.dashboard'));

        $response->assertOk();
        $response->assertSee(t('messages.nexus.business.dashboard.suspended_banner'));
    }

    public function test_submitSuspensionAppeal_createsAppeal(): void
    {
        [$business, $owner] = $this->makeOwner();
        app(VerifyBusinessAction::class)->execute($business->id);
        app(SuspendBusinessAction::class)->execute($business->id, 'test suspension');

        $response = $this->actingAs($owner, 'business')->post(route('nexus.business.dashboard.appeal'), [
            'message' => 'please review my case',
        ]);

        $response->assertRedirect(route('nexus.business.dashboard'));
        $appeals = app(SuspensionAppealRepositoryInterface::class)->findByStatus(\App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAppealStatus::Pending);
        $this->assertCount(1, $appeals);
        $this->assertSame('please review my case', $appeals[0]->message());
    }
}
