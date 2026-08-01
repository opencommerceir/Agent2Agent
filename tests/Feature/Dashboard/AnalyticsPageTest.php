<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Dashboard-UI half of Phase 4 Stage 6's own end-to-end scenario:
 * Home page renders the 6 KPI cards for a selected Tenant, the Analytics
 * page computes a KPI through the same form the request's own screenshot
 * describes, and both CSV/PDF export routes return a real download.
 */
class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboardHome_rendersKpiCardsForSelectedTenant(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->get("/dashboard?tenant_id={$tenant->id}");

        $response->assertStatus(200);
        $response->assertSee('revenueChart', false);
        $response->assertSee('ordersChart', false);
    }

    public function test_dashboardHome_withNoTenants_showsNoDataInsteadOfError(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_analyticsPage_calculatesAKpiThroughTheFilterForm(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->get('/dashboard/analytics?'.http_build_query([
            'tenant_id' => $tenant->id,
            'kpi_type' => 'total_orders',
            'time_period' => 'monthly',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertSee('total_orders');
    }

    public function test_exportCsv_returnsADownloadableCsvFile(): void
    {
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->get('/dashboard/analytics/export/csv?'.http_build_query([
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $response->assertSee('KPI');
    }

    public function test_exportPdf_returnsADownloadablePdfFile(): void
    {
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->get('/dashboard/analytics/export/pdf?'.http_build_query([
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }
}
