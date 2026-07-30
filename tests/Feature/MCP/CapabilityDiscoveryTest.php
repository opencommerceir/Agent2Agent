<?php

namespace Tests\Feature\MCP;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withValidToken_returnsRegisteredCapabilities(): void
    {
        $token = $this->registerAuthenticatedAgent();

        app(RegisterCapabilityAction::class)->execute(
            name: 'commerce.product.search',
            description: 'Search the product catalog',
            inputSchema: ['query' => 'string'],
            requiredPermissions: ['commerce.products.read'],
        );

        $response = $this->getJson('/mcp/v1/capabilities', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('meta.count', 1);
        $response->assertJsonPath('data.capabilities.0.name', 'commerce.product.search');
    }

    public function test_index_withoutToken_returnsUnauthorized(): void
    {
        $response = $this->getJson('/mcp/v1/capabilities');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_index_withNoCapabilitiesRegistered_returnsEmptyListNotError(): void
    {
        $token = $this->registerAuthenticatedAgent();

        $response = $this->getJson('/mcp/v1/capabilities', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('meta.count', 0);
        $response->assertJsonPath('data.capabilities', []);
    }

    private function registerAuthenticatedAgent(): string
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;
    }
}
