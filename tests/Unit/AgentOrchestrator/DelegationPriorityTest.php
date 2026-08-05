<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationPriority;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DelegationPriorityTest extends TestCase
{
    public function test_acceptsTheFullOneToTenRange(): void
    {
        $this->assertSame(1, (new DelegationPriority(1))->value());
        $this->assertSame(10, (new DelegationPriority(10))->value());
        $this->assertSame(5, (new DelegationPriority(5))->value());
    }

    public function test_rejectsBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DelegationPriority(0);
    }

    public function test_rejectsAboveTen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DelegationPriority(11);
    }
}
