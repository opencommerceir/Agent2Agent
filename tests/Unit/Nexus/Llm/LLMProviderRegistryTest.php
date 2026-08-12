<?php

namespace Tests\Unit\Nexus\Llm;

use App\Domains\Nexus\Llm\Application\Services\LLMProviderRegistry;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderNotFoundException;
use App\Domains\Nexus\Llm\Domain\Services\LLMProviderInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;
use PHPUnit\Framework\TestCase;

class LLMProviderRegistryTest extends TestCase
{
    private function fakeProvider(string $name): LLMProviderInterface
    {
        return new class($name) implements LLMProviderInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function chat(array $messages, array $options = []): LLMResponse
            {
                return LLMResponse::success('ok', $this->name, 'm', 1, 1, 0.0, 1.0);
            }

            public function estimateCost(array $messages): float
            {
                return 0.0;
            }

            public function supports(LLMFeature $feature): bool
            {
                return true;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
    }

    public function test_register_thenGet_returnsTheSameProvider(): void
    {
        $registry = new LLMProviderRegistry();
        $provider = $this->fakeProvider('openai');

        $registry->register('openai', $provider);

        $this->assertSame($provider, $registry->get('openai'));
    }

    public function test_get_withUnregisteredName_throws(): void
    {
        $registry = new LLMProviderRegistry();

        $this->expectException(LLMProviderNotFoundException::class);

        $registry->get('does-not-exist');
    }

    public function test_has_reflectsRegistrationState(): void
    {
        $registry = new LLMProviderRegistry();

        $this->assertFalse($registry->has('groq'));

        $registry->register('groq', $this->fakeProvider('groq'));

        $this->assertTrue($registry->has('groq'));
    }

    public function test_registered_returnsAllRegisteredKeys(): void
    {
        $registry = new LLMProviderRegistry();
        $registry->register('openai', $this->fakeProvider('openai'));
        $registry->register('claude', $this->fakeProvider('claude'));

        $this->assertSame(['openai', 'claude'], $registry->registered());
    }
}
