<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Sso\Application\Services\SsoProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard) read-only view of every registered
 * SsoProviderInterface — "what's actually live vs. stubbed," a small, real
 * answer to Phase 7's SSO scope rather than a bigger settings UI nothing
 * here would let an operator actually change yet (SAML/LDAP have no real
 * package/IdP wired, see SamlSsoProvider/LdapSsoProvider's own docblocks).
 */
class NexusSsoProvidersController extends Controller
{
    public function __construct(
        private readonly SsoProviderRegistry $providers,
    ) {
    }

    public function index(): View
    {
        $rows = array_map(fn ($provider) => [
            'key' => $provider->key(),
            'supportsInteractiveLogin' => $provider->supportsInteractiveLogin(),
            'isConfigured' => $provider->isConfigured(),
        ], $this->providers->all());

        return view('dashboard.nexus.sso-providers.index', ['providers' => $rows]);
    }
}
