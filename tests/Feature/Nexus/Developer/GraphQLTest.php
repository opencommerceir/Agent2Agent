<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Developer\Application\Actions\IssueApiKeyAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphQLTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_withoutAuth_returns401(): void
    {
        $this->postJson('/nexus/api/v1/graphql', ['query' => '{ creditBalance { balance } }'])
            ->assertStatus(401);
    }

    public function test_query_singleField_withCorrectScope_returnsData(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $key = $this->issueKey($business->id, [ApiKeyScope::CreditRead]);

        $response = $this->withHeader('Authorization', "Bearer {$key}")
            ->postJson('/nexus/api/v1/graphql', ['query' => '{ creditBalance { businessId balance } }']);

        $response->assertOk();
        $response->assertJsonPath('data.creditBalance.balance', 100_000);
        $response->assertJsonMissing(['errors' => []]);
        $this->assertArrayNotHasKey('errors', $response->json());
    }

    public function test_query_fieldWithoutScope_returnsPartialErrorNotWholeFailure(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $key = $this->issueKey($business->id, [ApiKeyScope::CreditRead]);

        $response = $this->withHeader('Authorization', "Bearer {$key}")
            ->postJson('/nexus/api/v1/graphql', ['query' => '{ creditBalance { balance } catalog { products } }']);

        $response->assertOk();
        $response->assertJsonPath('data.creditBalance.balance', 100_000);
        $this->assertNotEmpty($response->json('errors'));
        $this->assertStringContainsString('catalog.read', $response->json('errors.0.message'));
    }

    public function test_query_multipleResourcesInOneRequest(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $key = $this->issueKey($business->id, [ApiKeyScope::CreditRead, ApiKeyScope::CatalogRead]);

        $response = $this->withHeader('Authorization', "Bearer {$key}")
            ->postJson('/nexus/api/v1/graphql', [
                'query' => '{ creditBalance { balance } catalog { products services } }',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.creditBalance.balance', 100_000);
        $this->assertCount(1, $response->json('data.catalog.products'));
    }

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
