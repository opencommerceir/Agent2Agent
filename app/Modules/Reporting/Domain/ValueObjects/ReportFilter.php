<?php

namespace App\Modules\Reporting\Domain\ValueObjects;

/**
 * The optional, per-report-type extra parameters a Report was run with
 * (e.g. `limit` for a top_products/top_customers Report) — a thin,
 * typed wrapper around an associative array rather than a fixed set of
 * named properties, since which filters make sense varies by
 * ReportType and this stage doesn't need more than `limit` for any of
 * them. Persisted verbatim as `reports.filters` (JSON) so a later
 * `report.definition.get`-style read can show exactly what a saved
 * Report was run with.
 */
final class ReportFilter
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(
        private readonly array $values,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
