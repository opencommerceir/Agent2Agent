<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\Exceptions\InvalidCsvFormatException;
use App\Modules\Commerce\Domain\Services\CsvParserInterface;

/**
 * The one real implementation of `CsvParserInterface` — the Infrastructure/
 * framework-touching half of the Domain-owned contract (see that
 * interface's own docblock for the split rationale). Plain `fopen`/
 * `fgetcsv`, no library: nothing about a bulk import CSV needs quoting
 * edge cases beyond what PHP's own C-level CSV parser already handles.
 *
 * Because this method's body contains `yield`, calling parse() returns a
 * Generator immediately without running any code — the file isn't even
 * opened until the caller starts iterating. `InvalidCsvFormatException` is
 * therefore thrown on first iteration, not on call, ordinary PHP generator
 * semantics that `ProcessBulkImportJob`'s own two-pass iteration already
 * expects (each pass re-opens and re-throws identically for a bad file).
 */
final class CsvParser implements CsvParserInterface
{
    public function parse(string $filePath): iterable
    {
        // Checked explicitly (rather than suppressing fopen()'s own
        // warning with `@`) — this codebase's test bootstrap converts a
        // raw PHP warning into a PHPUnit warning regardless of `@`
        // suppression, which would otherwise fail an otherwise-passing
        // "throws on a missing file" test.
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidCsvFormatException("CSV file [{$filePath}] does not exist or cannot be read.");
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new InvalidCsvFormatException("CSV file [{$filePath}] could not be opened.");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new InvalidCsvFormatException("CSV file [{$filePath}] has no header row.");
            }

            $header = array_map(static fn (?string $column): string => trim((string) $column), $header);
            $columnCount = count($header);
            $rowNumber = 0;

            while (($raw = fgetcsv($handle)) !== false) {
                // fgetcsv yields a single-null-element array for a genuinely
                // blank line (e.g. a trailing newline at EOF) — not a real
                // data row, skip it rather than yielding a bogus row.
                if ($raw === [null]) {
                    continue;
                }

                $rowNumber++;

                // Pad/truncate a ragged row to the header's own width so
                // array_combine() below never fails on a short/long line —
                // a malformed row is this Domain's concern (row-level
                // validation), not this Parser's.
                $rawCount = count($raw);

                if ($rawCount < $columnCount) {
                    $raw = array_pad($raw, $columnCount, '');
                } elseif ($rawCount > $columnCount) {
                    $raw = array_slice($raw, 0, $columnCount);
                }

                yield $rowNumber => array_combine($header, $raw);
            }
        } finally {
            fclose($handle);
        }
    }
}
