<?php

namespace Tests\Unit\Core;

use App\Core\Application\Services\TranslationService;
use App\Core\Domain\Services\TranslationLoaderInterface;
use App\Core\Domain\ValueObjects\Language;
use PHPUnit\Framework\TestCase;

/**
 * Framework-free — TranslationService only depends on the
 * TranslationLoaderInterface contract, so a fixed, in-memory fake loader
 * exercises every rule (exact match, placeholder replace, fallback to
 * English, ultimate fallback to the key itself) without touching the
 * filesystem or the container. JsonTranslationLoader itself (which does
 * touch the filesystem via lang_path()) is exercised indirectly by the
 * Feature tests that hit real MCP/Notifications capabilities.
 */
class TranslationServiceTest extends TestCase
{
    public function test_translate_withExactMatch_returnsTheRequestedLanguageValue(): void
    {
        $service = $this->makeService();

        $this->assertSame('Hello', $service->translate('messages.greeting', Language::English));
        $this->assertSame('سلام', $service->translate('messages.greeting', Language::Persian));
    }

    public function test_translate_withPlaceholder_replacesIt(): void
    {
        $service = $this->makeService();

        $this->assertSame('Welcome, Ada', $service->translate('messages.welcome', Language::English, ['name' => 'Ada']));
    }

    public function test_resolve_whenRequestedLanguageMissingKey_fallsBackToEnglishAndFlagsIt(): void
    {
        $service = $this->makeService();

        $result = $service->resolve('messages.only_in_english', Language::Persian);

        $this->assertSame('Only in English', $result->value);
        $this->assertTrue($result->wasFallback);
    }

    public function test_resolve_whenPresentInRequestedLanguage_doesNotFlagFallback(): void
    {
        $service = $this->makeService();

        $result = $service->resolve('messages.greeting', Language::Persian);

        $this->assertSame('سلام', $result->value);
        $this->assertFalse($result->wasFallback);
    }

    public function test_translate_whenMissingEverywhere_returnsTheKeyItselfLiterally(): void
    {
        $service = $this->makeService();

        $this->assertSame('messages.nonexistent.key', $service->translate('messages.nonexistent.key', Language::Persian));
    }

    private function makeService(): TranslationService
    {
        $loader = new class implements TranslationLoaderInterface {
            public function load(Language $language, string $group): array
            {
                if ($group !== 'messages') {
                    return [];
                }

                return match ($language) {
                    Language::English => [
                        'greeting' => 'Hello',
                        'welcome' => 'Welcome, :name',
                        'only_in_english' => 'Only in English',
                    ],
                    Language::Persian => [
                        'greeting' => 'سلام',
                    ],
                };
            }
        };

        return new TranslationService($loader);
    }
}
