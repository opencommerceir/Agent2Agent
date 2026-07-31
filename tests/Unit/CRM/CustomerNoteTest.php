<?php

namespace Tests\Unit\CRM;

use App\Modules\CRM\Domain\Entities\CustomerNote;
use PHPUnit\Framework\TestCase;

class CustomerNoteTest extends TestCase
{
    public function test_create_setsAllFieldsAndNoId(): void
    {
        $note = CustomerNote::create(tenantId: 1, customerId: 2, agentId: 3, content: 'Prefers email contact.');

        $this->assertNull($note->id());
        $this->assertSame(1, $note->tenantId());
        $this->assertSame(2, $note->customerId());
        $this->assertSame(3, $note->agentId());
        $this->assertSame('Prefers email contact.', $note->content());
    }
}
