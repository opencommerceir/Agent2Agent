<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Developer\Application\Actions\IssueApiKeyAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_withoutAuthorizationHeader_returns401(): void
    {
        $this->getJson('/nexus/api/v1/business')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_business_withInvalidKey_returns401(): void
    {
        $this->withHeader('Authorization', 'Bearer nx_doesnotexist')
            ->getJson('/nexus/api/v1/business')
            ->assertStatus(401);
    }

    public function test_business_withWrongScope_returns403(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $plainKey = $this->issueKey($business->id, [ApiKeyScope::CatalogRead]);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/business')
            ->assertStatus(403);
    }

    public function test_business_withCorrectScope_returnsProfile(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $plainKey = $this->issueKey($business->id, [ApiKeyScope::BusinessRead]);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/business')
            ->assertOk()
            ->assertJsonPath('data.business.id', $business->id)
            ->assertJsonPath('data.business.nameEn', 'Caller Co');
    }

    public function test_business_suspendedBusiness_returns403(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $plainKey = $this->issueKey($business->id, [ApiKeyScope::BusinessRead]);
        app(SuspendBusinessAction::class)->execute($business->id, 'test suspension');

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/business')
            ->assertStatus(403);
    }

    public function test_catalog_returnsOwnProductsAndServices(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $plainKey = $this->issueKey($business->id, [ApiKeyScope::CatalogRead]);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/catalog')
            ->assertOk()
            ->assertJsonCount(1, 'data.products');
    }

    public function test_marketplaceSearch_returnsOtherVerifiedBusinesses(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $this->verifiedBusiness('Other Co');
        $plainKey = $this->issueKey($caller->id, [ApiKeyScope::MarketplaceRead]);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/marketplace/search')
            ->assertOk()
            ->assertJsonStructure(['data' => ['listings']]);
    }

    public function test_negotiationShow_partyCanRead_othersCannot(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $outsider = $this->verifiedBusiness('Outsider Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null),
        );

        $buyerKey = $this->issueKey($buyer->id, [ApiKeyScope::NegotiationRead]);
        $this->withHeader('Authorization', "Bearer {$buyerKey}")
            ->getJson("/nexus/api/v1/negotiations/{$negotiation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $negotiation->id);

        $outsiderKey = $this->issueKey($outsider->id, [ApiKeyScope::NegotiationRead]);
        $this->withHeader('Authorization', "Bearer {$outsiderKey}")
            ->getJson("/nexus/api/v1/negotiations/{$negotiation->id}")
            ->assertStatus(422);
    }

    public function test_creditBalance_returnsCallersOwnBalance(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $plainKey = $this->issueKey($business->id, [ApiKeyScope::CreditRead]);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/credit/balance')
            ->assertOk()
            ->assertJsonPath('data.balance', 100_000);
    }

    public function test_rateLimit_secondRequestWithinSameMinute_returns429(): void
    {
        config(['nexus.platform.api.rate_limit_per_minute' => 1]);
        $business = $this->verifiedBusiness('Caller Co');
        $plainKey = $this->issueKey($business->id, [ApiKeyScope::CreditRead]);

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/credit/balance')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$plainKey}")
            ->getJson('/nexus/api/v1/credit/balance')
            ->assertStatus(429);
    }

    /**
     * @param list<ApiKeyScope> $scopes
     */
    private function issueKey(int $businessId, array $scopes): string
    {
        return app(IssueApiKeyAction::class)->execute($businessId, 'Test key', $scopes)['plainKey'];
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
