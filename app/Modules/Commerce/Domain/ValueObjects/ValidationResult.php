<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * A plain, immutable outcome of validating one CSV row against
 * `CsvValidatorInterface` — deliberately not an exception (a row failing
 * validation is an entirely ordinary, expected outcome of a bulk import,
 * not an exceptional one; the caller decides what to do with an invalid
 * result, typically recording it via `BulkOperation`'s own per-row
 * bookkeeping and moving on to the next row).
 */
final class ValidationResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    private function __construct(
        public readonly bool $isValid,
        public readonly array $errors,
        public readonly array $warnings,
    ) {
    }

    public static function valid(array $warnings = []): self
    {
        return new self(true, [], $warnings);
    }

    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public static function invalid(array $errors, array $warnings = []): self
    {
        return new self(false, $errors, $warnings);
    }
}
