<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\ValueObjects\Language;

/**
 * The richer result of TranslationService::resolve() — carries not just the
 * final string but whether it actually came from the requested language or
 * fell back to English. Exists specifically so a caller (chiefly this
 * stage's own fallback tests) can assert the fallback actually happened,
 * rather than only being able to compare the returned string against the
 * English value and infer it indirectly.
 */
final class TranslationData
{
    public function __construct(
        public readonly string $key,
        public readonly Language $language,
        public readonly string $value,
        public readonly bool $wasFallback,
    ) {
    }

    /**
     * @return array{key: string, language: string, value: string, wasFallback: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'language' => $this->language->value,
            'value' => $this->value,
            'wasFallback' => $this->wasFallback,
        ];
    }
}
