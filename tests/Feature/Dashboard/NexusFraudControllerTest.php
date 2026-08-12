<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\SubmitSuspensionAppealAction;
use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-only Fraud/Suspension queue (Phase 6/M4) — core `auth`/`admin`
 * guard, same createAdmin() pattern NexusEscrowControllerTest establishes.
 */
class NexusFraudControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    public function test_index_listsSuspendedBusinessesAndPendingAppeals(): void
    {
        $admin = $this->createAdmin();
        $business = $this->verifiedBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'test suspension reason');
        app(SubmitSuspensionAppealAction::class)->execute($business->id, 'appeal message text');

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.fraud.index'));

        $response->assertStatus(200);
        $response->assertSee('appeal message text');
    }

    public function test_suspend_manuallySuspendsBusiness(): void
    {
        $admin = $this->createAdmin();
        $business = $this->verifiedBusiness('Buyer Co');

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.fraud.suspend'), [
            'business_id' => $business->id,
            'reason' => 'manual suspension',
        ]);

        $response->assertRedirect(route('dashboard.nexus.fraud.index'));
        $updated = app(BusinessRepositoryInterface::class)->findById($business->id);
        $this->assertFalse($updated->isActive());
    }

    public function test_reactivate_reactivatesBusiness(): void
    {
        $admin = $this->createAdmin();
        $business = $this->verifiedBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'test');

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.fraud.reactivate', $business->id));

        $response->assertRedirect(route('dashboard.nexus.fraud.index'));
        $updated = app(BusinessRepositoryInterface::class)->findById($business->id);
        $this->assertTrue($updated->isActive());
    }

    public function test_resolveAppeal_approve_reactivatesBusiness(): void
    {
        $admin = $this->createAdmin();
        $business = $this->verifiedBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'test');
        $appeal = app(SubmitSuspensionAppealAction::class)->execute($business->id, 'message');

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.fraud.appeals.resolve', $appeal->id), ['approve' => '1']);

        $response->assertRedirect(route('dashboard.nexus.fraud.index'));
        $updated = app(BusinessRepositoryInterface::class)->findById($business->id);
        $this->assertTrue($updated->isActive());
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.fraud.index'))->assertRedirect('/login');
    }
}
