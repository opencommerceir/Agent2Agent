<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\DelegationRequest;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationPriority;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationStatus;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

class DelegationRequestTest extends TestCase
{
    public function test_create_startsAsPending(): void
    {
        $request = $this->request();

        $this->assertSame(DelegationStatus::Pending, $request->status());
        $this->assertNull($request->id());
        $this->assertNull($request->result());
        $this->assertNull($request->completedAt());
    }

    public function test_create_rejectsDelegatingToTheSamePersona(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('to itself');

        DelegationRequest::create(
            tenantId: 1,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Ceo,
            task: 'Do something',
            priority: new DelegationPriority(5),
            timeoutSeconds: 30,
        );
    }

    public function test_create_rejectsAnEmptyTask(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DelegationRequest::create(
            tenantId: 1,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Sales,
            task: '   ',
            priority: new DelegationPriority(5),
            timeoutSeconds: 30,
        );
    }

    public function test_markCompleted_movesFromInProgressAndStoresTheResult(): void
    {
        $request = $this->request();
        $request->markInProgress();
        $request->markCompleted(['status' => 'completed', 'summary' => 'done']);

        $this->assertSame(DelegationStatus::Completed, $request->status());
        $this->assertSame(['status' => 'completed', 'summary' => 'done'], $request->result());
        $this->assertNotNull($request->completedAt());
    }

    public function test_markFailed_storesTheReasonUnderTheErrorKey(): void
    {
        $request = $this->request();
        $request->markInProgress();
        $request->markFailed('Permission denied');

        $this->assertSame(DelegationStatus::Failed, $request->status());
        $this->assertSame(['error' => 'Permission denied'], $request->result());
    }

    public function test_markTimeout_storesADescriptiveError(): void
    {
        $request = $this->request();
        $request->markInProgress();
        $request->markTimeout(45.2);

        $this->assertSame(DelegationStatus::Timeout, $request->status());
        $this->assertStringContainsString('45.2s elapsed', $request->result()['error']);
    }

    public function test_cannotCompleteWithoutFirstMovingToInProgress(): void
    {
        $request = $this->request();

        $this->expectException(LogicException::class);
        $request->markCompleted(['status' => 'completed']);
    }

    public function test_cannotTransitionOutOfATerminalState(): void
    {
        $request = $this->request();
        $request->markInProgress();
        $request->markCompleted(['status' => 'completed']);

        $this->expectException(LogicException::class);
        $request->markFailed('too late');
    }

    public function test_assignId_isOneTimeOnly(): void
    {
        $request = $this->request();
        $request->assignId(7);

        $this->assertSame(7, $request->id());

        $this->expectException(LogicException::class);
        $request->assignId(8);
    }

    private function request(): DelegationRequest
    {
        return DelegationRequest::create(
            tenantId: 1,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Sales,
            task: 'Create a 15% discount coupon for summer promotion',
            priority: new DelegationPriority(8),
            timeoutSeconds: 30,
        );
    }
}
