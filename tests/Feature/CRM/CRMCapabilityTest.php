<?php

namespace Tests\Feature\CRM;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use Database\Seeders\CRMCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full Phase 3 / Stage 1 (CRM Foundation) scenario over real MCP HTTP
 * requests: create a Ticket for a Customer -> add a Comment -> move the
 * Ticket to in_progress -> a different tenant's Agent gets
 * TicketNotFoundException trying to read it (tenant isolation) -> add a
 * Note to the Customer -> list Tickets filtered by status.
 *
 * Tag creation/assignment (spec steps 6-7 of the underlying scenario) are
 * exercised in TagActionsTest instead, not here — CreateTagAction/
 * AssignTagToCustomerAction were never wired to MCP this stage (see
 * CRMCapabilities' own docblock), so there is no HTTP path to exercise
 * them through.
 */
class CRMCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullTicketLifecycleScenario(): void
    {
        $this->seed(CRMCapabilitiesSeeder::class);

        [$tenantA, $tokenA] = $this->registerAgentWithPermissions([
            'crm.tickets.create', 'crm.tickets.read', 'crm.tickets.update', 'crm.customers.update',
        ]);

        $customer = app(CreateCustomerAction::class)->execute(
            tenantId: $tenantA,
            firstName: 'Jane',
            lastName: 'Doe',
            email: 'jane-'.uniqid().'@example.com',
        );

        // Step 1: create a Ticket for this Customer.
        $create = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.ticket.create',
            'input' => [
                'customer_id' => $customer->id,
                'subject' => 'Cannot log in',
                'description' => 'Getting a 500 error on login.',
                'priority' => 'high',
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $create->assertStatus(200);
        $create->assertJsonPath('data.ticket.status', 'open');
        $create->assertJsonPath('data.ticket.priority', 'high');
        $ticketId = $create->json('data.ticket.id');

        // Step 2: add a comment to the Ticket.
        $comment = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.comment.create',
            'input' => ['ticket_id' => $ticketId, 'content' => 'Looking into it.'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $comment->assertStatus(200);
        $comment->assertJsonPath('data.comment.content', 'Looking into it.');

        // Step 3: move the Ticket to in_progress (UpdateTicketAction — not
        // wired to MCP this stage, see its own docblock).
        app(\App\Modules\CRM\Application\Actions\UpdateTicketAction::class)->execute($ticketId, $tenantA, 'in_progress');

        $get = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.ticket.get',
            'input' => ['ticket_id' => $ticketId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $get->assertJsonPath('data.ticket.status', 'in_progress');

        // Step 4: a different tenant's Agent cannot see Tenant A's Ticket.
        [, $tokenB] = $this->registerAgentWithPermissions(['crm.tickets.read']);

        $crossTenant = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.ticket.get',
            'input' => ['ticket_id' => $ticketId],
        ], ['Authorization' => "Bearer {$tokenB}"]);

        $crossTenant->assertStatus(404);
        $crossTenant->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 5: add a Note to the Customer.
        $note = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.note.create',
            'input' => ['customer_id' => $customer->id, 'content' => 'Prefers email contact.'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $note->assertStatus(200);
        $note->assertJsonPath('data.note.content', 'Prefers email contact.');

        // Step 6: list Tickets filtered by status.
        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.ticket.list',
            'input' => ['status' => 'in_progress'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $list->assertStatus(200);
        $subjects = collect($list->json('data.tickets'))->pluck('subject');
        $this->assertTrue($subjects->contains('Cannot log in'));
    }

    public function test_createTicket_forNonexistentCustomer_returnsNotFound(): void
    {
        $this->seed(CRMCapabilitiesSeeder::class);
        [, $token] = $this->registerAgentWithPermissions(['crm.tickets.create']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.ticket.create',
            'input' => ['customer_id' => 999999, 'subject' => 'Subject', 'description' => 'Description'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_createTicket_withoutPermission_returnsForbidden(): void
    {
        $this->seed(CRMCapabilitiesSeeder::class);
        [, $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.ticket.create',
            'input' => ['customer_id' => 1, 'subject' => 'Subject', 'description' => 'Description'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_addComment_toNonexistentTicket_returnsNotFound(): void
    {
        $this->seed(CRMCapabilitiesSeeder::class);
        [, $token] = $this->registerAgentWithPermissions(['crm.tickets.update']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.comment.create',
            'input' => ['ticket_id' => 999999, 'content' => 'Hello'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_addNote_forNonexistentCustomer_returnsNotFound(): void
    {
        $this->seed(CRMCapabilitiesSeeder::class);
        [, $token] = $this->registerAgentWithPermissions(['crm.customers.update']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'crm.note.create',
            'input' => ['customer_id' => 999999, 'content' => 'Hello'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Support', 'acme-support-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Support Bot', 'customer_service');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Support Agent', 'support-agent-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}
