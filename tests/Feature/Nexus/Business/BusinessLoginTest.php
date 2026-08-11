<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): BusinessOwner
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_store_withValidCredentials_logsInAndRedirectsToDashboard(): void
    {
        $this->makeOwner();

        $response = $this->post(route('nexus.business.login.store'), [
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('nexus.business.dashboard'));
        $this->assertAuthenticated('business');
    }

    public function test_store_withInvalidPassword_isRejected(): void
    {
        $this->makeOwner();

        $response = $this->post(route('nexus.business.login.store'), [
            'email' => 'ali@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('business');
    }

    public function test_dashboard_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.business.dashboard'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_logout_endsSessionAndRedirectsToLogin(): void
    {
        $owner = $this->makeOwner();
        $this->actingAs($owner, 'business');

        $response = $this->post(route('nexus.business.logout'));

        $response->assertRedirect(route('nexus.business.login'));
        $this->assertGuest('business');
    }
}
