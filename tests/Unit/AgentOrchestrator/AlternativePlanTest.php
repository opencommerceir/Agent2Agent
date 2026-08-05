<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AlternativePlanTest extends TestCase
{
    public function test_create_wrapsAValidatedConfidenceScore(): void
    {
        $alternative = AlternativePlan::create('Focus on high-margin products only', 0.7, 'Lower volume but higher profit');

        $this->assertSame('Focus on high-margin products only', $alternative->plan);
        $this->assertSame(0.7, $alternative->confidence->value);
        $this->assertSame('Lower volume but higher profit', $alternative->reason);
    }

    public function test_create_rejectsAnOutOfRangeConfidence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AlternativePlan::create('Broad discount', 1.5, 'Higher volume');
    }

    public function test_toArray_returnsThePlainConfidenceFloat(): void
    {
        $alternative = AlternativePlan::create('Broad discount across all products', 0.5, 'Higher volume but lower margins');

        $this->assertSame([
            'plan' => 'Broad discount across all products',
            'confidence' => 0.5,
            'reason' => 'Higher volume but lower margins',
        ], $alternative->toArray());
    }
}
