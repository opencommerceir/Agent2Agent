<?php

namespace App\Core\Application\Services;

use App\Core\Domain\Services\TranslationLoaderInterface;
use App\Core\Domain\ValueObjects\Language;

/**
 * The only TranslationLoaderInterface implementation this stage builds —
 * reads lang/{code}/{group}.json (e.g. lang/fa/messages.json) via
 * lang_path(), the same project-root `lang/` directory Laravel itself uses
 * since v9, though this is a deliberately separate, custom scheme: Laravel's
 * own JSON translation feature expects one flat lang/{locale}.json file
 * keyed by literal source strings, not this stage's requested
 * lang/{code}/{group}.json-per-group, dot-path-addressable shape. Building
 * a small dedicated loader was simpler and more explicit than bending
 * Laravel's own translator to a structure it wasn't designed for.
 *
 * Caches each (language, group) pair in memory for the lifetime of the
 * instance — plenty for a single request/test, and avoids re-reading the
 * same file on every translate() call within it. A missing file or invalid
 * JSON is treated as "no translations for this group yet" (empty array),
 * never an exception.
 */
final class JsonTranslationLoader implements TranslationLoaderInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function load(Language $language, string $group): array
    {
        $cacheKey = "{$language->value}.{$group}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $path = lang_path("{$language->value}/{$group}.json");

        if (! is_file($path)) {
            return $this->cache[$cacheKey] = [];
        }

        $decoded = json_decode(file_get_contents($path), true);

        return $this->cache[$cacheKey] = is_array($decoded) ? $decoded : [];
    }
}
