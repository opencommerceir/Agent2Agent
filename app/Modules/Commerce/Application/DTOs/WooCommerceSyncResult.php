<?php

namespace App\Modules\Commerce\Application\DTOs;

/**
 * Structured report of one SyncWooCommerceProductsAction run. Represents
 * data only — no business logic (DTO Conventions) — errors are collected
 * per item rather than aborting the whole sync on the first bad row.
 */
final class WooCommerceSyncResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly int $successCount,
        public readonly int $failedCount,
        public readonly array $errors = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success_count' => $this->successCount,
            'failed_count' => $this->failedCount,
            'errors' => $this->errors,
        ];
    }
}
