<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralCode;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Idempotent get-or-create — same shape GrantCreditsAction's own
 * CreditBalance::open()-on-first-use follows. Called both from
 * IssueReferralCodeOnBusinessVerifiedListener (every verified Business gets
 * one automatically) and defensively by GetReferralStatusAction, so a
 * Business verified before this milestone existed still gets a code the
 * first time its dashboard is opened.
 */
final class IssueReferralCodeAction
{
    private const CODE_LENGTH = 6;

    public function __construct(
        private readonly ReferralCodeRepositoryInterface $codes,
    ) {
    }

    public function execute(int $businessId): ReferralCode
    {
        $existing = $this->codes->findByBusinessId($businessId);

        if ($existing) {
            return $existing;
        }

        do {
            $code = 'REF-'.Str::upper(Str::random(self::CODE_LENGTH));
        } while ($this->codes->codeExists($code));

        return $this->codes->save(ReferralCode::issue($businessId, $code));
    }
}
