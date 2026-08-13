<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Application\Actions\ConfirmMfaSetupAction;
use App\Domains\Nexus\Business\Application\Actions\DisableMfaAction;
use App\Domains\Nexus\Business\Application\Actions\EnableMfaAction;
use App\Domains\Nexus\Business\Domain\Services\TotpService;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * One page, state driven entirely by the owner row itself rather than
 * session-flashed wizard state across several routes: `mfa_secret` set but
 * `mfa_enabled_at` null means "setup started, awaiting confirmation" (the
 * same secret/otpauth URI is simply recomputed from the stored secret on
 * every GET, so refreshing mid-setup never invalidates what was already
 * scanned) — only the freshly-generated recovery codes need a one-hop
 * session flash, since those genuinely cannot be recomputed later.
 */
class BusinessMfaController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly EnableMfaAction $enableMfa,
        private readonly ConfirmMfaSetupAction $confirmSetup,
        private readonly DisableMfaAction $disableMfa,
    ) {
    }

    public function edit(): View
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        $setup = null;
        if (! $owner->mfa_enabled_at && $owner->mfa_secret) {
            $setup = [
                'secret' => $owner->mfa_secret,
                'otpauthUri' => $this->totp->otpauthUri($owner->mfa_secret, $owner->email),
            ];
        }

        return view('nexus::business.mfa.edit', [
            'owner' => $owner,
            'setup' => $setup,
            'recoveryCodes' => session('nexus.mfa.recovery_codes'),
        ]);
    }

    public function start(): RedirectResponse
    {
        $this->enableMfa->execute(Auth::guard('business')->id());

        return redirect()->route('nexus.business.mfa.edit');
    }

    public function confirm(Request $request): RedirectResponse
    {
        try {
            $codes = $this->confirmSetup->execute(Auth::guard('business')->id(), $request->string('code')->toString());
        } catch (InvalidArgumentException) {
            return back()->withErrors(['code' => t('messages.nexus.business.mfa.setup.invalid_code')]);
        }

        return redirect()->route('nexus.business.mfa.edit')->with('nexus.mfa.recovery_codes', $codes);
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            $this->disableMfa->execute(Auth::guard('business')->id(), $request->string('password')->toString());
        } catch (InvalidArgumentException) {
            return back()->withErrors(['password' => t('messages.nexus.business.mfa.disable.wrong_password')]);
        }

        return redirect()->route('nexus.business.mfa.edit')->with('status', t('messages.nexus.business.mfa.disable.done'));
    }
}
