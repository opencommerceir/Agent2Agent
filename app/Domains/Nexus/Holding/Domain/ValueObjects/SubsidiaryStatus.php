<?php

namespace App\Domains\Nexus\Holding\Domain\ValueObjects;

/**
 * `Invited -> Active` (accept) or `Invited -> Removed` (reject/withdrawn
 * invite); `Active -> Removed` (parent removes, or the subsidiary itself
 * leaves). `Removed` is terminal — re-joining the same Holding later is out
 * of scope for this milestone (same bounded-shortcut tier as other
 * documented gaps in this codebase, e.g. Coalition's missing
 * NegotiationWasRejected reaction).
 */
enum SubsidiaryStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Removed = 'removed';
}
