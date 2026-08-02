<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\Services\CsvValidatorInterface;
use App\Modules\Commerce\Domain\ValueObjects\ValidationResult;

/**
 * The one real implementation of `CsvValidatorInterface` — see that
 * interface's own docblock for why this stays deliberately generic
 * (presence/blankness only, no type-specific rules).
 */
final class CsvValidator implements CsvValidatorInterface
{
    public function validateRow(array $row, array $requiredColumns): ValidationResult
    {
        $errors = [];

        foreach ($requiredColumns as $column) {
            if (! array_key_exists($column, $row) || trim((string) $row[$column]) === '') {
                $errors[] = "Missing required column [{$column}].";
            }
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid($errors);
    }
}
