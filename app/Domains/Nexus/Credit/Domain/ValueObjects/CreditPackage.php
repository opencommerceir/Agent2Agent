<?php

namespace App\Domains\Nexus\Credit\Domain\ValueObjects;

/**
 * The 3 fixed credit packages from docs/claude/monetization.md — no
 * arbitrary custom amounts in this phase, matching the roadmap's own
 * "خرید پکیج کردیت" (buy a package), not "buy any amount". Priced in
 * Toman (IRT) — Zibal's own native currency once converted to Rial at the
 * gateway call boundary (PurchaseCreditsAction), since Zibal's API only
 * accepts IRR.
 */
enum CreditPackage: string
{
    case Starter = 'starter';
    case Professional = 'professional';
    case Enterprise = 'enterprise';

    public function priceAmountToman(): int
    {
        return match ($this) {
            self::Starter => 500_000,
            self::Professional => 2_000_000,
            self::Enterprise => 10_000_000,
        };
    }

    public function creditsGranted(): int
    {
        return match ($this) {
            self::Starter => 1_000,
            self::Professional => 5_000,
            self::Enterprise => 30_000,
        };
    }
}
