<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\ValueObjects\ValidationResult;

/**
 * A Domain-layer contract, pure/framework-free by convention (like every
 * other Domain Service in this codebase) — the one real implementation,
 * `App\Modules\Commerce\Application\Services\CsvValidator`, only checks
 * generic, format-shaped rules (required columns present and non-blank)
 * that are identical across every CSV shape this stage supports; it knows
 * nothing about what a "price" or an "email" specifically means. Each
 * `Import*Action`'s own row processor is responsible for the
 * type/business-rule checks specific to its own CSV shape (a bad SKU
 * format, an invalid email, a non-numeric price) — the same split
 * `MCPRequestValidationService` (generic MCP field presence) and each
 * Action's own domain logic (business rules) already have.
 */
interface CsvValidatorInterface
{
    /**
     * @param array<string, string> $row
     * @param list<string> $requiredColumns
     */
    public function validateRow(array $row, array $requiredColumns): ValidationResult;
}
