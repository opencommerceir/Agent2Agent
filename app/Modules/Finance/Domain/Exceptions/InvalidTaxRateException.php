<?php

namespace App\Modules\Finance\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Thrown when a TaxRate operation conflicts with an existing business
 * state — currently only "a tax rate for this region already exists for
 * this tenant" (CreateTaxRateAction). A legitimate business-state
 * conflict, not a malformed request — implements
 * ConflictExceptionInterface so MCPExceptionHandler maps it to 409
 * (same reasoning Commerce's InvalidOrderStatusException gives). Simple
 * out-of-range rate values (negative, over 100%) are rejected by
 * TaxRate's own constructor with a plain InvalidArgumentException
 * instead — that is a malformed input, not a state conflict.
 */
final class InvalidTaxRateException extends RuntimeException implements ConflictExceptionInterface
{
}
