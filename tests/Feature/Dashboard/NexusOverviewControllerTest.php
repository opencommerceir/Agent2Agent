<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real admin home page (replaces the old Commerce-KPI page
 * DashboardController used to render before Nexus Phase 0 disabled
 * Commerce/Analytics) — a "what needs your attention right now" summary,
 * backed by GetPlatformOverviewAction.
 */
class NexusOverviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_withoutAdminLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('dashboard.nexus.overview.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_showsRealPendingVerificationCount(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        app(RegisterBusinessAction::class)->execute('شرکت تستی', 'Test Co', BusinessType::Company, Industry::Technology);

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.overview.index'));

        $response->assertStatus(200);
        $response->assertSee(t('messages.nexus.admin.overview.pending_business_verifications'));
        $response->assertSeeInOrder(['1', t('messages.nexus.admin.overview.pending_business_verifications')]);
    }

    public function test_index_withNoPendingWork_showsZeroCounts(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.overview.index'));

        $response->assertStatus(200);
        $response->assertSeeInOrder(['0', t('messages.nexus.admin.overview.pending_business_verifications')]);
    }

    public function test_dashboardIndex_redirectsToOverview(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('dashboard.nexus.overview.index'));
    }
}
