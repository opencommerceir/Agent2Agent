<?php

namespace Tests\Unit\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;
use PHPUnit\Framework\TestCase;

class LLMResponseTest extends TestCase
{
    public function test_success_capturesAllFieldsAndSumsTotalTokens(): void
    {
        $response = LLMResponse::success(
            content: 'hello',
            provider: 'openai',
            model: 'gpt-4o',
            promptTokens: 10,
            completionTokens: 5,
            estimatedCost: 0.002,
            latencyMs: 123.4,
        );

        $this->assertSame('hello', $response->content);
        $this->assertSame('openai', $response->provider);
        $this->assertSame('gpt-4o', $response->model);
        $this->assertSame(10, $response->promptTokens);
        $this->assertSame(5, $response->completionTokens);
        $this->assertSame(15, $response->totalTokens);
        $this->assertSame(0.002, $response->estimatedCost);
        $this->assertSame(123.4, $response->latencyMs);
        $this->assertFalse($response->fromFallback);
        $this->assertNull($response->error);
    }

    public function test_success_defaultsFromFallbackToFalse(): void
    {
        $response = LLMResponse::success('c', 'p', 'm', 1, 1, 0.0, 1.0);

        $this->assertFalse($response->fromFallback);
    }

    public function test_withFallbackFlag_returnsNewInstanceWithFlagSetAndFieldsPreserved(): void
    {
        $original = LLMResponse::success('c', 'openrouter', 'm', 1, 2, 0.0, 5.0);

        $marked = $original->withFallbackFlag(true);

        $this->assertFalse($original->fromFallback);
        $this->assertTrue($marked->fromFallback);
        $this->assertSame($original->content, $marked->content);
        $this->assertSame($original->totalTokens, $marked->totalTokens);
    }
}
