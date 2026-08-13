<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Application\Actions\FindBusinessOwnerByOauthIdentityAction;
use App\Domains\Nexus\Business\Application\Actions\LinkOauthIdentityToOwnerAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\Concerns\FinishesBusinessLogin;
use App\Domains\Nexus\Sso\Application\Services\SsoProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Google OAuth login for the Business portal (Phase 7/M6) — never creates a
 * new Business (documented scope boundary): a real Nexus account must
 * already exist for the OAuth email, either linked already (fast path) or
 * matched by email and confirmed with that account's own password (the
 * "explicit confirmation" the Phase 7 plan calls for — proving ownership,
 * not just a bare "yes, link it" click).
 */
class BusinessOauthController extends Controller
{
    use FinishesBusinessLogin;

    public function __construct(
        private readonly SsoProviderRegistry $providers,
        private readonly FindBusinessOwnerByOauthIdentityAction $findByIdentity,
        private readonly LinkOauthIdentityToOwnerAction $linkIdentity,
    ) {
    }

    public function redirect(string $provider): RedirectResponse
    {
        return redirect()->away($this->providers->get($provider)->redirectUrl());
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $identity = $this->providers->get($provider)->handleCallback();

        $owner = $this->findByIdentity->execute($identity->providerKey, $identity->providerUserId);

        if ($owner) {
            $this->finishBusinessLogin($owner, $request);

            return redirect()->intended(route('nexus.business.dashboard'));
        }

        $matchingOwner = BusinessOwner::query()->where('email', $identity->email)->first();

        if (! $matchingOwner) {
            return redirect()->route('nexus.business.login')
                ->withErrors(['email' => t('messages.nexus.business.oauth.no_account_found', ['email' => $identity->email])]);
        }

        $request->session()->put('nexus.sso.pending_link', [
            'provider' => $identity->providerKey,
            'provider_user_id' => $identity->providerUserId,
            'business_owner_id' => $matchingOwner->id,
        ]);

        return redirect()->route('nexus.business.oauth.link.show');
    }

    public function showLinkConfirmation(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('nexus.sso.pending_link');

        if (! $pending) {
            return redirect()->route('nexus.business.login');
        }

        $owner = BusinessOwner::query()->find($pending['business_owner_id']);

        return view('nexus::business.oauth.link', ['email' => $owner->email, 'provider' => $pending['provider']]);
    }

    public function confirmLink(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('nexus.sso.pending_link');

        if (! $pending) {
            return redirect()->route('nexus.business.login');
        }

        $owner = BusinessOwner::query()->find($pending['business_owner_id']);

        if (! $owner || ! Auth::guard('business')->validate(['email' => $owner->email, 'password' => $request->string('password')->toString()])) {
            return back()->withErrors(['password' => t('messages.nexus.business.oauth.link_wrong_password')]);
        }

        $this->linkIdentity->execute($owner->id, $pending['provider'], $pending['provider_user_id']);
        $request->session()->forget('nexus.sso.pending_link');

        $this->finishBusinessLogin($owner, $request);

        return redirect()->route('nexus.business.dashboard')->with('status', t('messages.nexus.business.oauth.linked'));
    }
}
