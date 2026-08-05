<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\PatternExtractor;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use PHPUnit\Framework\TestCase;

class PatternExtractorTest extends TestCase
{
    private PatternExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new PatternExtractor();
    }

    public function test_patternFor_joinsEveryRecognizedKeywordFoundInTheGoalText(): void
    {
        $goal = Goal::fromText('Review sales and revenue this quarter', AgentType::Ceo);

        $this->assertSame('sales|revenue', $this->extractor->patternFor($goal));
    }

    public function test_patternFor_isGeneralWhenNoKeywordIsRecognized(): void
    {
        $goal = Goal::fromText('Water the plants', AgentType::Ceo);

        $this->assertSame('general', $this->extractor->patternFor($goal));
    }

    public function test_extract_returnsNullForAnUnsuccessfulResult(): void
    {
        $goal = Goal::fromText('Increase sales this week', AgentType::Ceo);
        $failed = new ExecutionStep('commerce.coupon.create', []);
        $failed->markAsRunning();
        $failed->markAsFailed('boom');

        $result = ExecutionResult::fromSteps($goal, [$failed], 1.0);

        $this->assertNull($this->extractor->extract($result, tenantId: 1));
    }

    public function test_extract_returnsNullWhenGoalHasNoRecognizedKeyword(): void
    {
        $goal = Goal::fromText('Water the plants', AgentType::Ceo);
        $completed = new ExecutionStep('commerce.coupon.create', []);
        $completed->markAsRunning();
        $completed->markAsCompleted(['coupon' => []]);

        $result = ExecutionResult::fromSteps($goal, [$completed], 1.0);

        $this->assertNull($this->extractor->extract($result, tenantId: 1));
    }

    public function test_extract_buildsAFreshPatternFromASuccessfulResult(): void
    {
        $goal = Goal::fromText('Increase sales by 15% this week', AgentType::Ceo);

        $reportStep = new ExecutionStep('report.sales.generate', []);
        $reportStep->markAsRunning();
        $reportStep->markAsCompleted(['report' => []]);

        $couponStep = new ExecutionStep('commerce.coupon.create', []);
        $couponStep->markAsRunning();
        $couponStep->markAsCompleted(['coupon' => []]);

        $result = ExecutionResult::fromSteps($goal, [$reportStep, $couponStep], 1.2);

        $pattern = $this->extractor->extract($result, tenantId: 7);

        $this->assertNotNull($pattern);
        $this->assertSame(7, $pattern->tenantId);
        $this->assertSame('sales', $pattern->goalPattern);
        $this->assertSame(AgentType::Ceo, $pattern->agentType);
        $this->assertSame(['report.sales.generate', 'commerce.coupon.create'], $pattern->successfulCapabilities());
        $this->assertSame(1, $pattern->usageCount());
        $this->assertSame(1.0, $pattern->successRate());
    }
}
