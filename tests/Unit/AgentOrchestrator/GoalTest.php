<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GoalTest extends TestCase
{
    public function test_fromText_trimsAndKeepsAgentType(): void
    {
        $goal = Goal::fromText('  Increase sales by 15%  ', AgentType::Ceo);

        $this->assertSame('Increase sales by 15%', $goal->text);
        $this->assertSame(AgentType::Ceo, $goal->agentType);
    }

    public function test_fromText_rejectsEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Goal::fromText('   ', AgentType::Sales);
    }
}
