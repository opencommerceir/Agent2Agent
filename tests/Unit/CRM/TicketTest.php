<?php

namespace Tests\Unit\CRM;

use App\Modules\CRM\Domain\Entities\Ticket;
use App\Modules\CRM\Domain\Exceptions\InvalidTicketStatusException;
use App\Modules\CRM\Domain\ValueObjects\TicketPriority;
use App\Modules\CRM\Domain\ValueObjects\TicketStatus;
use PHPUnit\Framework\TestCase;

class TicketTest extends TestCase
{
    public function test_create_setsOpenStatusAndGivenFields(): void
    {
        $ticket = Ticket::create(
            tenantId: 1,
            customerId: 2,
            agentId: 3,
            subject: 'Cannot log in',
            description: 'Getting a 500 error on login.',
            priority: TicketPriority::High,
        );

        $this->assertNull($ticket->id());
        $this->assertSame(TicketStatus::Open, $ticket->status());
        $this->assertSame(TicketPriority::High, $ticket->priority());
        $this->assertSame('Cannot log in', $ticket->subject());
    }

    public function test_create_withoutPriority_defaultsToMedium(): void
    {
        $ticket = Ticket::create(1, 2, 3, 'Subject', 'Description');

        $this->assertSame(TicketPriority::Medium, $ticket->priority());
    }

    public function test_changeStatus_followingTheSequence_succeeds(): void
    {
        $ticket = Ticket::create(1, 2, 3, 'Subject', 'Description');

        $ticket->changeStatus(TicketStatus::InProgress);
        $this->assertSame(TicketStatus::InProgress, $ticket->status());

        $ticket->changeStatus(TicketStatus::Resolved);
        $this->assertSame(TicketStatus::Resolved, $ticket->status());

        $ticket->changeStatus(TicketStatus::Closed);
        $this->assertSame(TicketStatus::Closed, $ticket->status());
    }

    public function test_changeStatus_skippingAheadInTheSequence_succeeds(): void
    {
        $ticket = Ticket::create(1, 2, 3, 'Subject', 'Description');

        $ticket->changeStatus(TicketStatus::Resolved);

        $this->assertSame(TicketStatus::Resolved, $ticket->status());
    }

    public function test_changeStatus_movingBackward_throwsInvalidTicketStatusException(): void
    {
        $ticket = Ticket::create(1, 2, 3, 'Subject', 'Description');
        $ticket->changeStatus(TicketStatus::InProgress);

        $this->expectException(InvalidTicketStatusException::class);

        $ticket->changeStatus(TicketStatus::Open);
    }

    public function test_changeStatus_toTheSameStatus_throwsInvalidTicketStatusException(): void
    {
        $ticket = Ticket::create(1, 2, 3, 'Subject', 'Description');
        $ticket->changeStatus(TicketStatus::InProgress);

        $this->expectException(InvalidTicketStatusException::class);

        $ticket->changeStatus(TicketStatus::InProgress);
    }

    public function test_changeStatus_afterClosed_throwsInvalidTicketStatusException(): void
    {
        $ticket = Ticket::create(1, 2, 3, 'Subject', 'Description');
        $ticket->changeStatus(TicketStatus::InProgress);
        $ticket->changeStatus(TicketStatus::Resolved);
        $ticket->changeStatus(TicketStatus::Closed);

        $this->expectException(InvalidTicketStatusException::class);

        $ticket->changeStatus(TicketStatus::Closed);
    }
}
