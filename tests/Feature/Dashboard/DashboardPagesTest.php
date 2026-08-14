<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\CreateUserAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-tests the surviving Dashboard pages against real data created
 * through the same Actions the MCP layer itself uses — the literal
 * end-to-end scenario from Phase 4 Stage 5's own request (steps 4-13:
 * language/RTL, Tenants, Agents, Notifications). Products/Orders/Analytics
 * were Commerce-module pages; Commerce has been disabled since Nexus
 * Phase 0 and those routes/controllers/views were removed for good along
 * with these tests, rather than left to crash on every request.
 */
class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_languageSwitch_toFarsi_rendersRtlAndFarsiText(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get('/language/fa');

        // '/dashboard' itself just redirects to the real Nexus Overview
        // page (Commerce's KPI home page it used to render has been
        // retired since Nexus Phase 0) — follow that redirect to verify
        // the language switch on a page that actually renders.
        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertRedirect(route('dashboard.nexus.overview.index'));

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.overview.index'));

        $response->assertStatus(200);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('نمای کلی پلتفرم');
    }

    public function test_languageSwitch_toEnglish_rendersLtrAndEnglishText(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get('/language/en');

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertRedirect(route('dashboard.nexus.overview.index'));

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.overview.index'));

        $response->assertStatus(200);
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('Platform Overview');
    }

    public function test_tenantsIndex_listsCreatedTenants(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->get('/dashboard/tenants');

        $response->assertStatus(200);
        $response->assertSee('Acme Inc');
    }

    public function test_tenantsStore_createsANewTenant(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/dashboard/tenants', [
            'name' => 'Widgets Co',
            'slug' => 'widgets-co-'.uniqid(),
        ]);

        $response->assertRedirect(route('dashboard.tenants.index'));
        $this->assertDatabaseHas('tenants', ['name' => 'Widgets Co']);
    }

    public function test_tenantsUpdate_changesNameAndStatus(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->put("/dashboard/tenants/{$tenant->id}", [
            'name' => 'Acme Renamed',
            'status' => 'suspended',
        ]);

        $response->assertRedirect(route('dashboard.tenants.index'));
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Acme Renamed', 'status' => 'suspended']);
    }

    public function test_agentsIndex_filteredByTenant_showsOnlyThatTenantsAgents(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        [$tenantA, $orgA] = $this->createTenantWithOrganization('Acme A');
        [$tenantB, $orgB] = $this->createTenantWithOrganization('Acme B');
        app(RegisterAgentAction::class)->execute($tenantA->id, $orgA->id, 'Agent A', 'shopping');
        app(RegisterAgentAction::class)->execute($tenantB->id, $orgB->id, 'Agent B', 'shopping');

        $response = $this->actingAs($admin)->get("/dashboard/agents?tenant_id={$tenantA->id}");

        $response->assertStatus(200);
        $response->assertSee('Agent A');
        $response->assertDontSee('Agent B');
    }

    public function test_agentsStore_registersANewAgent(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        [$tenant, $organization] = $this->createTenantWithOrganization('Acme Inc');

        $response = $this->actingAs($admin)->post('/dashboard/agents', [
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'name' => 'New Agent',
            'type' => 'shopping',
        ]);

        $response->assertRedirect(route('dashboard.agents.index'));
        $this->assertDatabaseHas('agents', ['name' => 'New Agent', 'tenant_id' => $tenant->id]);
    }

    public function test_agentsSuspendThenActivate_togglesStatus(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        [$tenant, $organization] = $this->createTenantWithOrganization('Acme Inc');
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Agent A', 'shopping');

        $this->actingAs($admin)->post("/dashboard/agents/{$agent->id}/suspend")->assertRedirect();
        $this->assertSame('suspended', app(AgentRepositoryInterface::class)->findById($agent->id)->status()->value);

        $this->actingAs($admin)->post("/dashboard/agents/{$agent->id}/activate")->assertRedirect();
        $this->assertSame('active', app(AgentRepositoryInterface::class)->findById($agent->id)->status()->value);
    }

    public function test_settingsUpdate_changesTenantDefaultLanguage(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->put('/dashboard/settings', [
            'tenant_id' => $tenant->id,
            'default_language' => 'fa',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'default_language' => 'fa']);
    }

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    /**
     * @return array{0: \App\Core\Application\DTOs\TenantData, 1: \App\Core\Application\DTOs\OrganizationData}
     */
    private function createTenantWithOrganization(string $name): array
    {
        $tenant = app(CreateTenantAction::class)->execute($name, \Illuminate\Support\Str::slug($name).'-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, $name.' Org', \Illuminate\Support\Str::slug($name).'-org-'.uniqid());

        return [$tenant, $organization];
    }
}
