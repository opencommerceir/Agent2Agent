<?php

namespace Tests\Unit\Nexus\Developer;

use App\Domains\Nexus\Developer\Domain\Entities\ApiKey;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ApiKeyTest extends TestCase
{
    public function test_issue_startsUnrevokedAndUnused(): void
    {
        $apiKey = $this->issue();

        $this->assertNull($apiKey->id());
        $this->assertNull($apiKey->lastUsedAt());
        $this->assertFalse($apiKey->isRevoked());
        $this->assertTrue($apiKey->isValid());
    }

    public function test_matches_correctPlainKey_returnsTrue(): void
    {
        $apiKey = ApiKey::issue(1, ApiKey::hash('nx_secret'), 'nx_', null, []);

        $this->assertTrue($apiKey->matches('nx_secret'));
    }

    public function test_matches_wrongPlainKey_returnsFalse(): void
    {
        $apiKey = ApiKey::issue(1, ApiKey::hash('nx_secret'), 'nx_', null, []);

        $this->assertFalse($apiKey->matches('nx_wrong'));
    }

    public function test_revoke_setsRevokedAtAndInvalidatesKey(): void
    {
        $apiKey = $this->issue();

        $apiKey->revoke();

        $this->assertTrue($apiKey->isRevoked());
        $this->assertFalse($apiKey->isValid());
        $this->assertInstanceOf(DateTimeImmutable::class, $apiKey->revokedAt());
    }

    public function test_isExpired_pastExpiryDate_isTrueAndInvalidatesKey(): void
    {
        $apiKey = ApiKey::issue(1, ApiKey::hash('nx_secret'), 'nx_', null, [], new DateTimeImmutable('-1 day'));

        $this->assertTrue($apiKey->isExpired());
        $this->assertFalse($apiKey->isValid());
    }

    public function test_isExpired_futureExpiryDate_isFalse(): void
    {
        $apiKey = ApiKey::issue(1, ApiKey::hash('nx_secret'), 'nx_', null, [], new DateTimeImmutable('+1 day'));

        $this->assertFalse($apiKey->isExpired());
        $this->assertTrue($apiKey->isValid());
    }

    public function test_isExpired_noExpiryDate_isFalse(): void
    {
        $apiKey = $this->issue();

        $this->assertFalse($apiKey->isExpired());
    }

    public function test_hasScope_grantedScope_returnsTrue(): void
    {
        $apiKey = ApiKey::issue(1, ApiKey::hash('nx_secret'), 'nx_', null, [ApiKeyScope::CatalogRead]);

        $this->assertTrue($apiKey->hasScope(ApiKeyScope::CatalogRead));
        $this->assertFalse($apiKey->hasScope(ApiKeyScope::CreditRead));
    }

    public function test_markUsed_setsLastUsedAt(): void
    {
        $apiKey = $this->issue();

        $apiKey->markUsed();

        $this->assertInstanceOf(DateTimeImmutable::class, $apiKey->lastUsedAt());
    }

    private function issue(): ApiKey
    {
        return ApiKey::issue(
            businessId: 1,
            keyHash: ApiKey::hash('nx_secret'),
            keyPrefix: 'nx_abc12345',
            label: 'Test key',
            scopes: [ApiKeyScope::CatalogRead, ApiKeyScope::CreditRead],
        );
    }
}
