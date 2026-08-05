<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentMessage;
use App\Modules\AgentOrchestrator\Domain\Entities\DelegationRequest;
use App\Modules\AgentOrchestrator\Domain\Exceptions\DelegationTimeoutException;
use App\Modules\AgentOrchestrator\Domain\Services\AgentCommunicationInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationPriority;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageType;
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\CRMCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `AgentCommunicationService` against real Repositories and the real
 * `ExecuteGoalAction` (Phase 6, Stage 5, §7.30) — every delegation in this
 * test runs under the *same* real `AuthContext`, proving delegation never
 * grants a new real permission (see this class's own docblock).
 */
class AgentCommunicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_persistsAMessageAndMarksItSent(): void
    {
        $tenantId = $this->tenant();
        $message = AgentMessage::create(
            tenantId: $tenantId,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Sales,
            messageType: MessageType::Delegation,
            content: ['task' => 'Create a coupon'],
            parentExecutionId: null,
        );

        app(AgentCommunicationInterface::class)->send($message);

        $this->assertNotNull($message->id());
        $this->assertDatabaseHas('agent_messages', [
            'id' => $message->id(),
            'tenant_id' => $tenantId,
            'status' => 'sent',
        ]);
    }

    public function test_receive_returnsMessagesInvolvingThisPersonaEitherDirection(): void
    {
        $tenantId = $this->tenant();
        $communication = app(AgentCommunicationInterface::class);

        $communication->send(AgentMessage::create($tenantId, AgentType::Ceo, AgentType::Sales, MessageType::Delegation, ['task' => 'a'], null));
        $communication->send(AgentMessage::create($tenantId, AgentType::Sales, AgentType::Ceo, MessageType::Response, ['status' => 'completed'], null));
        $communication->send(AgentMessage::create($tenantId, AgentType::Support, AgentType::Finance, MessageType::Delegation, ['task' => 'b'], null));

        $messages = $communication->receive($tenantId, AgentType::Sales, 20);

        $this->assertCount(2, $messages);
    }

    public function test_requestDelegation_onSuccessCompletesAndRecordsTwoMessages(): void
    {
        [$tenantId, $agentId] = $this->registerAgentWithPermissions(['agent.collaboration.delegate', 'crm.tickets.read']);
        $context = new AuthContext(tenantId: $tenantId, agentId: $agentId);

        $request = DelegationRequest::create(
            tenantId: $tenantId,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Support,
            task: 'Review open support tickets',
            priority: new DelegationPriority(8),
            timeoutSeconds: 30,
        );

        $result = app(AgentCommunicationInterface::class)->requestDelegation($request, $context);

        $this->assertSame('completed', $result->status);
        $this->assertSame(['crm.ticket.list'], array_column($result->toArray()['steps'], 'capability'));

        $this->assertDatabaseHas('delegation_requests', [
            'id' => $request->id(),
            'tenant_id' => $tenantId,
            'from_agent_type' => 'ceo',
            'to_agent_type' => 'support',
            'status' => 'completed',
        ]);
        $this->assertDatabaseCount('agent_messages', 2);
        $this->assertDatabaseHas('agent_messages', ['from_agent_type' => 'ceo', 'to_agent_type' => 'support', 'message_type' => 'delegation']);
        $this->assertDatabaseHas('agent_messages', ['from_agent_type' => 'support', 'to_agent_type' => 'ceo', 'message_type' => 'response', 'status' => 'processed']);
    }

    public function test_requestDelegation_delegatingDoesNotGrantANewRealPermission(): void
    {
        // The caller has agent.collaboration.delegate but NOT crm.tickets.read
        // — delegating to "support" does not magically grant it, since both
        // personas share the exact same real Agent identity/permissions.
        // PlanExecutor already catches a step's own PermissionDeniedException
        // and marks that step Failed rather than aborting (unchanged, §7.26)
        // — so the delegation *mechanism* still completes normally here; it's
        // the *nested* ExecutionResult's own status that reports the real
        // failure, exactly the way commerce.goal.execute itself already
        // behaves for any plan with an unauthorized step.
        [$tenantId, $agentId] = $this->registerAgentWithPermissions(['agent.collaboration.delegate']);
        $context = new AuthContext(tenantId: $tenantId, agentId: $agentId);

        $request = DelegationRequest::create(
            tenantId: $tenantId,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Support,
            task: 'Review open support tickets',
            priority: new DelegationPriority(5),
            timeoutSeconds: 30,
        );

        $result = app(AgentCommunicationInterface::class)->requestDelegation($request, $context);

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('Permission', $result->toArray()['steps'][0]['error']);

        $this->assertDatabaseHas('delegation_requests', ['id' => $request->id(), 'status' => 'completed']);
        $this->assertSame('failed', $request->result()['status']);
    }

    public function test_requestDelegation_exceedingItsOwnTimeoutMarksTimeoutAndThrows(): void
    {
        [$tenantId, $agentId] = $this->registerAgentWithPermissions(['agent.collaboration.delegate', 'crm.tickets.read']);
        $context = new AuthContext(tenantId: $tenantId, agentId: $agentId);

        $request = DelegationRequest::create(
            tenantId: $tenantId,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Support,
            task: 'Review open support tickets',
            priority: new DelegationPriority(5),
            timeoutSeconds: 0, // any real elapsed time exceeds a 0-second budget
        );

        $this->expectException(DelegationTimeoutException::class);

        try {
            app(AgentCommunicationInterface::class)->requestDelegation($request, $context);
        } finally {
            $this->assertDatabaseHas('delegation_requests', ['id' => $request->id(), 'status' => 'timeout']);
        }
    }

    public function test_requestDelegation_isTenantIsolated(): void
    {
        [$tenantA, $agentA] = $this->registerAgentWithPermissions(['agent.collaboration.delegate', 'crm.tickets.read']);
        app(AgentCommunicationInterface::class)->requestDelegation(
            DelegationRequest::create($tenantA, AgentType::Ceo, AgentType::Support, 'Review open support tickets', new DelegationPriority(5), 30),
            new AuthContext(tenantId: $tenantA, agentId: $agentA),
        );

        [$tenantB] = $this->registerAgentWithPermissions([]);

        $messagesForB = app(AgentCommunicationInterface::class)->receive($tenantB, AgentType::Support, 20);
        $this->assertCount(0, $messagesForB);
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(CRMCapabilitiesSeeder::class);
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Orchestrator', 'orchestrator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        return [$tenant->id, $agent->id];
    }

    private function tenant(): int
    {
        return app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid())->id;
    }
}
