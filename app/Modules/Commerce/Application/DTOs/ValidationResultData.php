<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\ValueObjects\ValidationResult;

/**
 * Not returned by any MCP capability this stage (row-level validation
 * outcomes end up as `BulkOperationItem` success/failed records instead,
 * not surfaced individually through MCP) — built for the same reason
 * every DTO in this codebase mirrors its Domain ValueObject 1:1, so a
 * future capability that does want to hand back a raw validation result
 * (e.g. a "dry run" preview) doesn't need a new DTO invented for it.
 */
final class ValidationResultData
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors,
        public readonly array $warnings,
    ) {
    }

    public static function fromValueObject(ValidationResult $result): self
    {
        return new self($result->isValid, $result->errors, $result->warnings);
    }

    /**
     * @return array{isValid: bool, errors: list<string>, warnings: list<string>}
     */
    public function toArray(): array
    {
        return [
            'isValid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
