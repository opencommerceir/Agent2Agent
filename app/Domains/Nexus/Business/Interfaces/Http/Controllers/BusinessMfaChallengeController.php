<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Application\Actions\VerifyMfaChallengeAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\Concerns\FinishesBusinessLogin;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The login-time MFA gate — reachable only via BusinessLoginController/
 * BusinessOauthController stashing `nexus.mfa.pending` first
 * (FinishesBusinessLogin::startMfaChallenge()); a direct GET/POST here with
 * no pending challenge in session has nothing to verify and bounces to the
 * login form. Deliberately does not reveal MFA-enabled status pre-password
 * (no "this email requires a code" hint anywhere before credentials are
 * already validated) — the pending session state only exists after that.
 */
class BusinessMfaChallengeController extends Controller
{
    use FinishesBusinessLogin;

    public function __construct(
        private readonly VerifyMfaChallengeAction $verifyChallenge,
    ) {
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('nexus.mfa.pending')) {
            return redirect()->route('nexus.business.login');
        }

        return view('nexus::business.mfa.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('nexus.mfa.pending');

        if (! $pending) {
            return redirect()->route('nexus.business.login');
        }

        $code = $request->string('code')->toString();

        try {
            $verified = $this->verifyChallenge->execute($pending['owner_id'], $code);
        } catch (InvalidArgumentException) {
            $verified = false;
        }

        if (! $verified) {
            return back()->withErrors(['code' => t('messages.nexus.business.mfa.challenge.invalid_code')]);
        }

        $owner = BusinessOwner::query()->findOrFail($pending['owner_id']);
        $request->session()->forget('nexus.mfa.pending');
        $this->finishBusinessLogin($owner, $request, $pending['remember'] ?? false);

        return $this->redirectAfterLogin($owner);
    }
}
