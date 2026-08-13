<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTeamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_invitesTeamMemberAndRedirects(): void
    {
        $owner = $this->ownerWithBusiness();

        $response = $this->actingAs($owner, 'business')->post(route('nexus.business.team.store'), [
            'name' => 'Manager Person',
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);

        $response->assertRedirect(route('nexus.business.team.index'));
        $this->assertDatabaseHas('business_owners', ['email' => 'manager@example.com', 'role' => 'manager']);
    }

    public function test_index_listsTeamMembers(): void
    {
        $owner = $this->ownerWithBusiness();

        $response = $this->actingAs($owner, 'business')->get(route('nexus.business.team.index'));

        $response->assertOk();
        $response->assertSee($owner->email);
    }

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.business.team.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    private function ownerWithBusiness(): BusinessOwner
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'role' => TeamMemberRole::Owner->value,
        ]);
    }
}
