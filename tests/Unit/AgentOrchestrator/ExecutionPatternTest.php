<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

class ExecutionPatternTest extends TestCase
{
    public function test_create_startsAtUsageOneAndFullSuccessRate(): void
    {
        $pattern = ExecutionPattern::create(
            tenantId: 1,
            goalPattern: 'sales|revenue',
            agentType: AgentType::Ceo,
            successfulCapabilities: ['report.sales.generate'],
            now: new DateTimeImmutable('2026-08-12 00:00:00'),
        );

        $this->assertNull($pattern->id());
        $this->assertSame(1, $pattern->usageCount());
        $this->assertSame(1.0, $pattern->successRate());
        $this->assertSame(['report.sales.generate'], $pattern->successfulCapabilities());
        $this->assertSame([], $pattern->failedCapabilities());
    }

    public function test_matches_isTrueWhenAnyKeywordAppearsInTheGoalText(): void
    {
        $pattern = $this->pattern('sales|revenue');

        $this->assertTrue($pattern->matches('Increase our sales by 20%'));
        $this->assertTrue($pattern->matches('Review revenue this quarter'));
        $this->assertFalse($pattern->matches('Check inventory levels'));
    }

    public function test_matches_isCaseInsensitive(): void
    {
        $pattern = $this->pattern('sales');

        $this->assertTrue($pattern->matches('BOOST SALES THIS WEEK'));
    }

    public function test_matches_generalPatternNeverMatchesAnything(): void
    {
        $pattern = $this->pattern('general');

        $this->assertFalse($pattern->matches('Water the plants'));
        $this->assertFalse($pattern->matches('sales revenue inventory customer report'));
    }

    public function test_recordOutcome_onSuccessBlendsSuccessRateUpwardAndBumpsUsage(): void
    {
        $pattern = $this->pattern('sales', successRate: 1.0, usageCount: 1);

        $pattern->recordOutcome(true, ['analytics.kpi.calculate'], new DateTimeImmutable('2026-08-13 00:00:00'));

        $this->assertSame(2, $pattern->usageCount());
        $this->assertSame(1.0, $pattern->successRate());
        $this->assertSame(['report.sales.generate', 'analytics.kpi.calculate'], $pattern->successfulCapabilities());
    }

    public function test_recordOutcome_onFailureDegradesSuccessRateWithoutRemovingSuccessfulCapabilities(): void
    {
        $pattern = $this->pattern('sales', successRate: 1.0, usageCount: 1);

        $pattern->recordOutcome(false, ['commerce.coupon.create'], new DateTimeImmutable('2026-08-13 00:00:00'));

        $this->assertSame(2, $pattern->usageCount());
        $this->assertSame(0.5, $pattern->successRate());
        $this->assertSame(['report.sales.generate'], $pattern->successfulCapabilities());
        $this->assertSame(['commerce.coupon.create'], $pattern->failedCapabilities());
    }

    public function test_assignId_isOneTimeOnly(): void
    {
        $pattern = ExecutionPattern::create(
            tenantId: 1,
            goalPattern: 'sales',
            agentType: AgentType::Ceo,
            successfulCapabilities: ['report.sales.generate'],
            now: new DateTimeImmutable('2026-08-12 00:00:00'),
        );

        $this->assertNull($pattern->id());
        $pattern->assignId(42);
        $this->assertSame(42, $pattern->id());

        $this->expectException(LogicException::class);
        $pattern->assignId(43);
    }

    private function pattern(string $goalPattern, float $successRate = 1.0, int $usageCount = 1): ExecutionPattern
    {
        return ExecutionPattern::reconstruct(
            id: 1,
            tenantId: 1,
            goalPattern: $goalPattern,
            agentType: AgentType::Ceo,
            successfulCapabilities: ['report.sales.generate'],
            failedCapabilities: [],
            usageCount: $usageCount,
            successRate: $successRate,
            lastUsedAt: new DateTimeImmutable('2026-08-12 00:00:00'),
        );
    }
}
