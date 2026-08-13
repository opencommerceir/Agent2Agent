<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;
use App\Domains\Nexus\Audit\Domain\ValueObjects\AuditOutcome;
use App\Domains\Nexus\Audit\Infrastructure\Models\AuditLogEntry as AuditLogEntryModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-only compliance surface (Phase 7/M9) — core `auth`/`admin` guard,
 * same createAdmin() pattern NexusFraudControllerTest establishes.
 */
class NexusAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_listsRecentEntries(): void
    {
        $admin = $this->createAdmin();
        app(AuditLogEntryRepositoryInterface::class)->append(
            capabilityName: 'nexus.credit.balance',
            businessId: null,
            coreAgentId: 5,
            status: AuditOutcome::Success,
            inputSummary: [],
            executionTimeMs: 12,
        );

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.audit.index'));

        $response->assertStatus(200);
        $response->assertSee('nexus.credit.balance');
    }

    public function test_verify_onIntactChain_showsSuccessStatus(): void
    {
        $admin = $this->createAdmin();
        app(AuditLogEntryRepositoryInterface::class)->append('nexus.credit.balance', null, 5, AuditOutcome::Success, [], 12);

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.audit.verify'));

        $response->assertRedirect(route('dashboard.nexus.audit.index'));
        $response->assertSessionHas('status');
    }

    public function test_verify_onTamperedChain_flashesABrokenStatusMessage(): void
    {
        $admin = $this->createAdmin();
        app(AuditLogEntryRepositoryInterface::class)->append('nexus.credit.balance', null, 5, AuditOutcome::Success, [], 12);
        AuditLogEntryModel::query()->where('sequence', 1)->update(['status' => 'denied']);

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.audit.verify'));

        $response->assertRedirect(route('dashboard.nexus.audit.index'));
        $response->assertSessionHas('status', function (string $message) {
            return str_contains($message, '1');
        });
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.audit.index'))->assertRedirect('/login');
    }
}
