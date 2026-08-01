<?php

namespace App\Core\Application\Services;

/**
 * Detects likely N+1 query patterns from an already-captured query log
 * (the shape `DB::getQueryLog()` returns — the caller enables query
 * logging, runs whatever scenario it wants inspected, then hands the
 * result here; this class never touches DB::* itself).
 *
 * Deliberately **not** the heuristic an earlier draft of this stage's own
 * request proposed ("a `select *` query taking under 10ms might be
 * N+1") — that signature is unreliable in both directions: a fast query
 * is just as often a well-indexed, perfectly normal lookup as it is part
 * of an N+1 chain, and a slow N+1 query (a big JOIN-free table, a cold
 * cache) would be missed entirely. The real, standard signature of N+1 is
 * **the same query shape repeated many times in one request/scenario**,
 * differing only in which literal id it filters by — this class groups
 * queries by their shape (every numeric literal replaced with `?`) and
 * flags any shape appearing at or above a small repeat threshold,
 * regardless of how fast or slow each individual occurrence was.
 */
final class LazyLoadingDetector
{
    private const REPEAT_THRESHOLD = 3;

    /**
     * @param list<array{query: string, bindings?: array, time?: float}> $queries
     * @return list<array{sql: string, occurrences: int}> sorted by occurrences, most first
     */
    public function analyze(array $queries): array
    {
        $occurrencesByShape = [];

        foreach ($queries as $entry) {
            $shape = $this->normalize($entry['query'] ?? '');
            $occurrencesByShape[$shape] = ($occurrencesByShape[$shape] ?? 0) + 1;
        }

        $suspects = [];

        foreach ($occurrencesByShape as $shape => $occurrences) {
            if ($occurrences >= self::REPEAT_THRESHOLD) {
                $suspects[] = ['sql' => $shape, 'occurrences' => $occurrences];
            }
        }

        usort($suspects, fn (array $a, array $b) => $b['occurrences'] <=> $a['occurrences']);

        return $suspects;
    }

    private function normalize(string $sql): string
    {
        return preg_replace('/\b\d+\b/', '?', $sql) ?? $sql;
    }
}
