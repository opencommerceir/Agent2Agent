<?php

namespace App\Domains\Nexus\Negotiation\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by Negotiation::counter() when round_count has already reached
 * max_rounds (config('nexus.platform.negotiation.max_rounds') by
 * default) — a business rule, not an illegal state transition, so this
 * is a distinct exception from InvalidNegotiationStateException.
 */
final class NegotiationRoundLimitExceededException extends RuntimeException
{
}
