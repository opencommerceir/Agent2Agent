<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * `BulkOperation::ALLOWED_TRANSITIONS` enforces the legal state graph —
 * Pending -> Processing -> {Completed, Partial, Failed}, or Pending ->
 * Failed directly (an unrecoverable error, e.g. a missing/unreadable
 * file, before any row is ever processed). `complete()` itself decides
 * Completed vs. Partial vs. Failed from the final row counts — see that
 * method's own docblock.
 */
enum BulkOperationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';
}
