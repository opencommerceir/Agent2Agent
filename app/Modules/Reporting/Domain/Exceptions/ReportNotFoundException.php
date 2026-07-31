<?php

namespace App\Modules\Reporting\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when a saved Report is looked up by id and either doesn't exist
 * or belongs to a different tenant — findById() never leaks a
 * cross-tenant row, so a mismatched tenant produces the identical 404 a
 * genuinely nonexistent report would (same "cross-tenant id -> 404, not
 * 403" shape every other module's findById()-based lookup already
 * established).
 */
final class ReportNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
