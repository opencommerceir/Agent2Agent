<?php

namespace Tests\Feature\CRM;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\CRM\Application\Actions\AddNoteToCustomerAction;
use App\Modules\CRM\Application\Actions\GetCustomerNotesAction;
use App\Modules\CRM\Domain\Exceptions\CustomerNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GetCustomerNotesAction exercised directly — not wired to any MCP
 * capability this stage (see its own docblock).
 *
 * customer_notes.agent_id is a real, non-nullable foreign key to `agents`
 * (same shape orders.agent_id already has — HANDOFF gotcha #8) — every
 * note here is created against a real registered Agent, never a bare
 * integer.
 */
class GetCustomerNotesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returnsNotesInCreationOrder(): void
    {
        [$tenantId, $agentId, $customerId] = $this->setUpTenantAgentCustomer();

        app(AddNoteToCustomerAction::class)->execute($tenantId, $agentId, $customerId, 'First note.');
        app(AddNoteToCustomerAction::class)->execute($tenantId, $agentId, $customerId, 'Second note.');

        $notes = app(GetCustomerNotesAction::class)->execute($customerId, $tenantId);

        $this->assertCount(2, $notes);
        $this->assertSame('First note.', $notes[0]->content);
        $this->assertSame('Second note.', $notes[1]->content);
    }

    public function test_execute_scopedToTenant_excludesOtherTenantsNotes(): void
    {
        [$tenantA, $agentA, $customerId] = $this->setUpTenantAgentCustomer();
        $tenantB = app(CreateTenantAction::class)->execute('Globex Inc', 'globex-'.uniqid());

        app(AddNoteToCustomerAction::class)->execute($tenantA, $agentA, $customerId, 'Tenant A note.');

        $this->expectException(CustomerNotFoundException::class);

        app(GetCustomerNotesAction::class)->execute($customerId, $tenantB->id);
    }

    public function test_execute_forNonexistentCustomer_throwsCustomerNotFoundException(): void
    {
        [$tenantId] = $this->setUpTenantAgentCustomer();

        $this->expectException(CustomerNotFoundException::class);

        app(GetCustomerNotesAction::class)->execute(999999, $tenantId);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function setUpTenantAgentCustomer(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Support', 'acme-support-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Support Bot', 'customer_service');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $customer = app(CreateCustomerAction::class)->execute(
            tenantId: $tenant->id,
            firstName: 'Jane',
            lastName: 'Doe',
            email: 'jane-'.Str::random(8).'@example.com',
        );

        return [$tenant->id, $agent->id, $customer->id];
    }
}
