<?php

namespace Tests\Feature\Nexus\Marketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_rendersForLoggedInOwner(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($owner, 'business')->get(route('nexus.network.index'));

        $response->assertOk();
    }

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.network.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }
}
