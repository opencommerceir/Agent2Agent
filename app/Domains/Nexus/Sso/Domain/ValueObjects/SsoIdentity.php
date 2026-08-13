<?php

namespace App\Domains\Nexus\Sso\Domain\ValueObjects;

/**
 * What any SsoProviderInterface::handleCallback() returns — the minimum a
 * caller needs to find-or-link a BusinessOwner, regardless of which real
 * protocol (OAuth today, SAML/LDAP stubbed in M8) produced it. Framework-free
 * (Domain Layer Rules) even though every real implementation is inherently
 * HTTP-driven — this VO is the seam that keeps that HTTP detail out of the
 * Application layer's own find-or-link logic.
 */
final class SsoIdentity
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $providerUserId,
        public readonly string $email,
        public readonly string $name,
    ) {
    }
}
