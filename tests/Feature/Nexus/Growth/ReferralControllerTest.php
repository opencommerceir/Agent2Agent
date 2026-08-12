<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_showsOwnReferralCodeAndCounts(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($business->id);

        $response = $this->actingAs($owner, 'business')->get(route('nexus.growth.referrals.index'));

        $response->assertOk();
        $response->assertSee($code->code());
    }

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.growth.referrals.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }
}
