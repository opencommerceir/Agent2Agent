<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfidenceScoreTest extends TestCase
{
    public function test_acceptsTheFullZeroToOneRange(): void
    {
        $this->assertSame(0.0, ConfidenceScore::fromFloat(0.0)->value);
        $this->assertSame(1.0, ConfidenceScore::fromFloat(1.0)->value);
        $this->assertSame(0.85, ConfidenceScore::fromFloat(0.85)->value);
    }

    public function test_rejectsBelowZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfidenceScore::fromFloat(-0.01);
    }

    public function test_rejectsAboveOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfidenceScore::fromFloat(1.01);
    }

    public function test_asPercentage_convertsAndRoundsToOneDecimal(): void
    {
        $this->assertSame(85.0, ConfidenceScore::fromFloat(0.85)->asPercentage());
        $this->assertSame(33.3, ConfidenceScore::fromFloat(1 / 3)->asPercentage());
    }
}
