<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\SendAgentInviteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusGrowthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_showsInviteTotalsAndKFactor(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('نام Inviter Co', 'Inviter Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100, CreditTransactionType::AdminGrant, 'test.seed');
        app(SendAgentInviteAction::class)->execute($business->id, 'Lead Co', 'lead@example.com');

        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->get(route('dashboard.nexus.growth.index'));

        $response->assertStatus(200);
        $response->assertViewHas('growth', fn ($growth) => $growth['invitesSent'] === 1 && $growth['invitingBusinesses'] === 1);
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.growth.index'))->assertRedirect('/login');
    }
}
