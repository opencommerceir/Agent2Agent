<?php

namespace App\Modules\Loyalty\Domain\ValueObjects;

/**
 * What kind of ledger entry a PointTransaction records. `Earn`/`Bonus`
 * carry a positive `points` delta, `Redeem`/`Expire` a negative one,
 * `Adjust` either (a manual correction) — enforced by
 * PointTransaction::record()'s own sign check, not by this enum itself.
 */
enum TransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjust = 'adjust';
    case Bonus = 'bonus';
}
