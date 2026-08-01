<?php

namespace App\Core\Domain\Services;

use App\Core\Domain\ValueObjects\Language;

/**
 * The outbound port TranslationService reads translation strings through —
 * mirrors ShippingProviderInterface/ConnectorInterface's own shape (a
 * Domain-owned contract for something Infrastructure supplies). The only
 * implementation this stage builds is JsonTranslationLoader
 * (Application/Services), but this is a real port, not a foregone
 * conclusion — a future stage could add e.g. a database-backed loader for
 * tenant-editable translations without TranslationService changing at all.
 */
interface TranslationLoaderInterface
{
    /**
     * Returns the full, decoded translation group for one language (e.g.
     * "messages", "validation", "errors") — the flat lang/{code}/{group}.json
     * file, decoded into a possibly-nested associative array. An unknown
     * group or a language with no file for it returns an empty array, never
     * an exception: "no translations configured yet" is not an error.
     *
     * @return array<string, mixed>
     */
    public function load(Language $language, string $group): array;
}
