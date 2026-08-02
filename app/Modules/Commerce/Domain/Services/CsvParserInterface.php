<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\Exceptions\InvalidCsvFormatException;

/**
 * A Domain-layer *contract* only — `App\Modules\Commerce\Application\Services\CsvParser`
 * is the one real implementation, since actually reading a file is an
 * Infrastructure/framework-touching concern (`fopen`/`fgetcsv`), the same
 * split `TranslationServiceInterface`/`TranslationLoaderInterface`
 * already establish for Core's i18n subsystem (§7.16) — Domain owns the
 * shape, Application owns the filesystem call.
 *
 * Deliberately streams one row at a time (a `Generator`, via `iterable`)
 * rather than returning the whole file as an array — a 1000+ row import
 * must never require the entire CSV in memory at once. Batching
 * (chunks of 100, rule §د.2) is the *caller's* concern (the owning Job),
 * not this interface's — keeping one responsibility ("read the next raw
 * row") per class, the same "Domain Service only does the one thing its
 * name says" discipline every other Domain Service in this codebase
 * follows.
 */
interface CsvParserInterface
{
    /**
     * @return iterable<int, array<string, string>> row number (1-indexed,
     *         header excluded) => associative row keyed by the file's own
     *         header column names
     *
     * @throws InvalidCsvFormatException if the file doesn't exist, can't
     *         be read, or has no header row at all
     */
    public function parse(string $filePath): iterable;
}
