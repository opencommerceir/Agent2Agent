<?php

namespace App\Domains\Nexus\Sso\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by SamlSsoProvider/LdapSsoProvider (Phase 7/M8) — registered,
 * real classes with real config-shaped constructors, but with no actual
 * SAML/LDAP package installed and no real Identity Provider reachable in
 * this environment to test against. Same documented-shortcut tier as
 * `gateway=stripe` being blocked in PurchaseCreditsAction (Phase 3/M3,
 * Stripe doesn't support Toman) and the local LLM providers pointing at a
 * real-but-unreachable-in-dev endpoint (Phase 4/M2) — a connector proven
 * wired, not a connector proven working end-to-end.
 */
final class SsoProviderNotConfiguredException extends RuntimeException
{
}
