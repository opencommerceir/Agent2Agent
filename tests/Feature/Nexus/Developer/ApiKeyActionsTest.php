<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Developer\Application\Actions\AuthenticateApiKeyAction;
use App\Domains\Nexus\Developer\Application\Actions\IssueApiKeyAction;
use App\Domains\Nexus\Developer\Application\Actions\ListApiKeysAction;
use App\Domains\Nexus\Developer\Application\Actions\RevokeApiKeyAction;
use App\Domains\Nexus\Developer\Domain\Exceptions\InvalidApiKeyException;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ApiKeyActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_returnsPlainKeyOnceAndPersistsOnlyHash(): void
    {
        $business = $this->verifiedBusiness('Caller Co');

        $result = app(IssueApiKeyAction::class)->execute($business->id, 'My Integration', [ApiKeyScope::CatalogRead]);

        $this->assertStringStartsWith('nx_', $result['plainKey']);
        $this->assertSame('My Integration', $result['apiKey']->label);
        $this->assertSame(['catalog.read'], $result['apiKey']->scopes);
        $this->assertDatabaseMissing('nexus_api_keys', ['key_hash' => $result['plainKey']]);
        $this->assertDatabaseHas('nexus_api_keys', ['business_id' => $business->id, 'label' => 'My Integration']);
    }

    public function test_authenticate_validPlainKey_returnsApiKeyAndMarksUsed(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $result = app(IssueApiKeyAction::class)->execute($business->id, null, [ApiKeyScope::CatalogRead]);

        $apiKey = app(AuthenticateApiKeyAction::class)->execute($result['plainKey']);

        $this->assertSame($business->id, $apiKey->businessId());
        $this->assertNotNull($apiKey->lastUsedAt());
    }

    public function test_authenticate_unknownKey_throws(): void
    {
        $this->expectException(InvalidApiKeyException::class);

        app(AuthenticateApiKeyAction::class)->execute('nx_doesnotexist');
    }

    public function test_authenticate_revokedKey_throws(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $result = app(IssueApiKeyAction::class)->execute($business->id, null, [ApiKeyScope::CatalogRead]);
        app(RevokeApiKeyAction::class)->execute($result['apiKey']->id, $business->id);

        $this->expectException(InvalidApiKeyException::class);

        app(AuthenticateApiKeyAction::class)->execute($result['plainKey']);
    }

    public function test_list_returnsOnlyOwnKeys(): void
    {
        $businessA = $this->verifiedBusiness('Business A');
        $businessB = $this->verifiedBusiness('Business B');
        app(IssueApiKeyAction::class)->execute($businessA->id, 'A key', [ApiKeyScope::CatalogRead]);
        app(IssueApiKeyAction::class)->execute($businessB->id, 'B key', [ApiKeyScope::CatalogRead]);

        $keys = app(ListApiKeysAction::class)->execute($businessA->id);

        $this->assertCount(1, $keys);
        $this->assertSame('A key', $keys[0]->label);
    }

    public function test_revoke_ownKey_succeeds(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $result = app(IssueApiKeyAction::class)->execute($business->id, null, [ApiKeyScope::CatalogRead]);

        app(RevokeApiKeyAction::class)->execute($result['apiKey']->id, $business->id);

        $this->assertTrue(app(ListApiKeysAction::class)->execute($business->id)[0]->isRevoked);
    }

    public function test_revoke_someoneElsesKey_throws(): void
    {
        $owner = $this->verifiedBusiness('Owner Co');
        $intruder = $this->verifiedBusiness('Intruder Co');
        $result = app(IssueApiKeyAction::class)->execute($owner->id, null, [ApiKeyScope::CatalogRead]);

        $this->expectException(InvalidArgumentException::class);

        app(RevokeApiKeyAction::class)->execute($result['apiKey']->id, $intruder->id);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }
}
