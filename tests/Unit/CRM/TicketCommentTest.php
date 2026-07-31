<?php

namespace Tests\Unit\CRM;

use App\Modules\CRM\Domain\Entities\TicketComment;
use PHPUnit\Framework\TestCase;

class TicketCommentTest extends TestCase
{
    public function test_create_setsAllFieldsAndNoId(): void
    {
        $comment = TicketComment::create(ticketId: 5, agentId: 9, content: 'Looking into it.');

        $this->assertNull($comment->id());
        $this->assertSame(5, $comment->ticketId());
        $this->assertSame(9, $comment->agentId());
        $this->assertSame('Looking into it.', $comment->content());
    }
}
