<?php

namespace Tests\Feature\Core;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_persistsAgentAsActive(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-inc');
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store');

        $result = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');

        $this->assertNotNull($result->id);
        $this->assertSame('active', $result->status);
        $this->assertSame('shopping', $result->type);
        $this->assertDatabaseHas('agents', [
            'name' => 'Shopping Assistant',
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    public function test_generateAgentToken_afterRegistration_persistsOnlyTheHashNeverThePlainToken(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-inc');
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store');
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');

        $tokenData = app(GenerateAgentTokenAction::class)->execute($agent->id, 'test-token');

        $this->assertNotEmpty($tokenData->plainToken);
        $this->assertDatabaseMissing('agent_tokens', ['token_hash' => $tokenData->plainToken]);
        $this->assertDatabaseHas('agent_tokens', ['agent_id' => $agent->id, 'label' => 'test-token']);
    }
}
