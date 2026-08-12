<?php

namespace Tests\Unit\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use PHPUnit\Framework\TestCase;

class LLMFeatureTest extends TestCase
{
    public function test_cases_matchTheFourRoutableFeatures(): void
    {
        $this->assertSame(
            ['reasoning', 'negotiation', 'classification', 'fallback'],
            array_map(fn (LLMFeature $feature) => $feature->value, LLMFeature::cases()),
        );
    }

    public function test_from_resolvesByStringValue(): void
    {
        $this->assertSame(LLMFeature::Reasoning, LLMFeature::from('reasoning'));
        $this->assertSame(LLMFeature::Classification, LLMFeature::from('classification'));
    }
}
