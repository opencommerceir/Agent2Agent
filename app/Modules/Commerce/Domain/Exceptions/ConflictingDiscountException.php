<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Not currently thrown by DiscountRuleEvaluator itself — an Exclusive
 * rule that can't stack simply isn't selected (a normal, silent outcome
 * of ApplyDiscountsToCartAction's own resolution loop, not an error;
 * see that Action's own docblock). Reserved for a real, MCP-facing
 * conflict this codebase doesn't have a caller for yet — e.g. a future
 * `commerce.rule.create` that lets two `Exclusive` rules collide at
 * definition time in some way that genuinely needs a 409, not just a
 * silent evaluation-time skip. Kept (rather than omitted) because the
 * request named it explicitly among only 3 exceptions.
 */
final class ConflictingDiscountException extends RuntimeException implements ConflictExceptionInterface
{
}
