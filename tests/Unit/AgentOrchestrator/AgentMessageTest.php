<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentMessage;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageStatus;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageType;
use LogicException;
use PHPUnit\Framework\TestCase;

class AgentMessageTest extends TestCase
{
    public function test_create_startsAsPendingWithNoProcessedAt(): void
    {
        $message = AgentMessage::create(
            tenantId: 1,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Sales,
            messageType: MessageType::Delegation,
            content: ['task' => 'Create a coupon'],
            parentExecutionId: null,
        );

        $this->assertSame(MessageStatus::Pending, $message->status());
        $this->assertNull($message->processedAt());
        $this->assertNull($message->id());
        $this->assertSame(['task' => 'Create a coupon'], $message->content());
    }

    public function test_markAsSent_movesToSentWithoutTouchingProcessedAt(): void
    {
        $message = $this->message();
        $message->markAsSent();

        $this->assertSame(MessageStatus::Sent, $message->status());
        $this->assertNull($message->processedAt());
    }

    public function test_markAsProcessed_stampsProcessedAt(): void
    {
        $message = $this->message();
        $message->markAsSent();
        $message->markAsProcessed();

        $this->assertSame(MessageStatus::Processed, $message->status());
        $this->assertNotNull($message->processedAt());
    }

    public function test_assignId_isOneTimeOnly(): void
    {
        $message = $this->message();
        $message->assignId(3);

        $this->assertSame(3, $message->id());

        $this->expectException(LogicException::class);
        $message->assignId(4);
    }

    private function message(): AgentMessage
    {
        return AgentMessage::create(
            tenantId: 1,
            fromAgentType: AgentType::Ceo,
            toAgentType: AgentType::Sales,
            messageType: MessageType::Delegation,
            content: ['task' => 'Create a coupon'],
            parentExecutionId: null,
        );
    }
}
