<?php

namespace Tests\Feature\CRM;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\CRM\Application\Actions\CreateTicketAction;
use App\Modules\CRM\Application\Actions\UpdateTicketAction;
use App\Modules\CRM\Domain\Exceptions\InvalidTicketStatusException;
use App\Modules\CRM\Domain\Exceptions\TicketNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UpdateTicketAction exercised directly — it isn't wired to any MCP
 * capability this stage (see its own docblock). Covers the same
 * forward-only state machine TicketTest already proves at the Entity
 * level, but end to end through the Repository this time (real DB save
 * + reload), plus the Action's own not-found guard.
 */
class UpdateTicketActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_movingForward_persistsNewStatus(): void
    {
        [$tenantId, $agentId, $customerId] = $this->setUpTenantAgentCustomer();

        $ticket = app(CreateTicketAction::class)->execute($tenantId, $agentId, $customerId, 'Subject', 'Description');

        $result = app(UpdateTicketAction::class)->execute($ticket->id, $tenantId, 'in_progress');

        $this->assertSame('in_progress', $result->status);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
    }

    public function test_execute_movingBackward_throwsInvalidTicketStatusException(): void
    {
        [$tenantId, $agentId, $customerId] = $this->setUpTenantAgentCustomer();

        $ticket = app(CreateTicketAction::class)->execute($tenantId, $agentId, $customerId, 'Subject', 'Description');
        app(UpdateTicketAction::class)->execute($ticket->id, $tenantId, 'in_progress');

        $this->expectException(InvalidTicketStatusException::class);

        app(UpdateTicketAction::class)->execute($ticket->id, $tenantId, 'open');
    }

    public function test_execute_forNonexistentTicket_throwsTicketNotFoundException(): void
    {
        [$tenantId] = $this->setUpTenantAgentCustomer();

        $this->expectException(TicketNotFoundException::class);

        app(UpdateTicketAction::class)->execute(999999, $tenantId, 'in_progress');
    }

    public function test_execute_forTicketInAnotherTenant_throwsTicketNotFoundException(): void
    {
        [$tenantA, $agentA, $customerA] = $this->setUpTenantAgentCustomer();
        [$tenantB] = $this->setUpTenantAgentCustomer();

        $ticket = app(CreateTicketAction::class)->execute($tenantA, $agentA, $customerA, 'Subject', 'Description');

        $this->expectException(TicketNotFoundException::class);

        app(UpdateTicketAction::class)->execute($ticket->id, $tenantB, 'in_progress');
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
            email: 'jane-'.uniqid().'@example.com',
        );

        return [$tenant->id, $agent->id, $customer->id];
    }
}
