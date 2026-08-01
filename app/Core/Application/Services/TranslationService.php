<?php

namespace App\Core\Application\Services;

use App\Core\Application\DTOs\TranslationData;
use App\Core\Domain\Services\TranslationLoaderInterface;
use App\Core\Domain\Services\TranslationServiceInterface;
use App\Core\Domain\ValueObjects\Language;

/**
 * The one TranslationServiceInterface implementation. Fallback rule (this
 * stage's own requirement): a key missing from the requested language falls
 * back to English; a key missing from English too returns the key itself
 * literally (e.g. "messages.some.missing.key") rather than an empty string —
 * the same "obviously wrong, not quietly wrong" precedent
 * Notifications' own TemplateRenderer already established for an unmatched
 * `{{variable}}`.
 */
final class TranslationService implements TranslationServiceInterface
{
    public function __construct(
        private readonly TranslationLoaderInterface $loader,
    ) {
    }

    public function translate(string $key, Language $language, array $replace = []): string
    {
        return $this->resolve($key, $language, $replace)->value;
    }

    /**
     * The richer entry point — see TranslationServiceInterface's own
     * docblock for why this isn't part of the Domain contract.
     *
     * @param array<string, scalar> $replace
     */
    public function resolve(string $key, Language $language, array $replace = []): TranslationData
    {
        [$group, $path] = array_pad(explode('.', $key, 2), 2, '');

        $value = $this->lookup($this->loader->load($language, $group), $path);
        $wasFallback = false;

        if ($value === null && $language !== Language::English) {
            $value = $this->lookup($this->loader->load(Language::English, $group), $path);
            $wasFallback = true;
        }

        if ($value === null) {
            $value = $key;
            $wasFallback = false;
        }

        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(":{$placeholder}", (string) $replacement, $value);
        }

        return new TranslationData($key, $language, $value, $wasFallback);
    }

    /**
     * @param array<string, mixed> $translations
     */
    private function lookup(array $translations, string $path): ?string
    {
        $cursor = $translations;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return is_string($cursor) ? $cursor : null;
    }
}
