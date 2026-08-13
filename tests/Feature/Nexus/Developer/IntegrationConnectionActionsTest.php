<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Developer\Application\Actions\CreateIntegrationConnectionAction;
use App\Domains\Nexus\Developer\Application\Actions\ListIntegrationConnectionsAction;
use App\Domains\Nexus\Developer\Application\Actions\RevokeIntegrationConnectionAction;
use App\Domains\Nexus\Developer\Application\Actions\SyncCatalogToIntegrationAction;
use App\Domains\Nexus\Developer\Domain\Exceptions\IntegrationConnectionRevokedException;
use App\Domains\Nexus\Developer\Domain\Exceptions\IntegrationSyncFailedException;
use App\Domains\Nexus\Developer\Domain\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\IntegrationCategory;
use App\Domains\Nexus\Catalog\Application\Actions\SearchCatalogAction;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class IntegrationConnectionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persistsConnectionWithEncryptedToken(): void
    {
        $business = $this->verifiedBusiness('Caller Co');

        $data = app(CreateIntegrationConnectionAction::class)->execute(
            $business->id, IntegrationCategory::Erp, 'My ERP', 'https://erp.example.com/products', 'secret-token', ['nameEn' => 'name'],
        );

        $this->assertSame('erp', $data->category);
        $this->assertDatabaseHas('nexus_integration_connections', ['business_id' => $business->id, 'name' => 'My ERP']);
        $this->assertDatabaseMissing('nexus_integration_connections', ['auth_token' => 'secret-token']);
    }

    public function test_list_returnsOnlyOwnConnections(): void
    {
        $businessA = $this->verifiedBusiness('Business A');
        $businessB = $this->verifiedBusiness('Business B');
        app(CreateIntegrationConnectionAction::class)->execute($businessA->id, IntegrationCategory::Crm, 'A', 'https://a.example.com', null, []);
        app(CreateIntegrationConnectionAction::class)->execute($businessB->id, IntegrationCategory::Crm, 'B', 'https://b.example.com', null, []);

        $connections = app(ListIntegrationConnectionsAction::class)->execute($businessA->id);

        $this->assertCount(1, $connections);
        $this->assertSame('A', $connections[0]->name);
    }

    public function test_revoke_someoneElsesConnection_throws(): void
    {
        $owner = $this->verifiedBusiness('Owner Co');
        $intruder = $this->verifiedBusiness('Intruder Co');
        $data = app(CreateIntegrationConnectionAction::class)->execute($owner->id, IntegrationCategory::Crm, 'A', 'https://a.example.com', null, []);

        $this->expectException(InvalidArgumentException::class);

        app(RevokeIntegrationConnectionAction::class)->execute($data->id, $intruder->id);
    }

    public function test_sync_appliesMappingAndSendsAuthHeader(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $data = app(CreateIntegrationConnectionAction::class)->execute(
            $business->id, IntegrationCategory::Erp, 'My ERP', 'https://erp.example.com/products', 'secret-token', ['nameEn' => 'product_name'],
        );

        $captured = null;
        $mock = new MockHandler([
            function (Psr7Request $request) use (&$captured) {
                $captured = $request;

                return new Response(200);
            },
        ]);

        $result = $this->action(new Client(['handler' => HandlerStack::create($mock)]))->execute($data->id, $business->id);

        $this->assertSame(1, $result['itemsSent']);
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('Bearer secret-token', $captured->getHeaderLine('Authorization'));
        $body = json_decode((string) $captured->getBody(), true);
        $this->assertSame('Widget', $body['items'][0]['product_name']);
        $this->assertArrayNotHasKey('nameEn', $body['items'][0]);
    }

    public function test_sync_revokedConnection_throws(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $data = app(CreateIntegrationConnectionAction::class)->execute($business->id, IntegrationCategory::Erp, 'My ERP', 'https://erp.example.com', null, []);
        app(RevokeIntegrationConnectionAction::class)->execute($data->id, $business->id);

        $this->expectException(IntegrationConnectionRevokedException::class);

        $this->action(new Client(['handler' => HandlerStack::create(new MockHandler([]))]))->execute($data->id, $business->id);
    }

    public function test_sync_httpFailure_throwsIntegrationSyncFailedException(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $data = app(CreateIntegrationConnectionAction::class)->execute($business->id, IntegrationCategory::Erp, 'My ERP', 'https://erp.example.com', null, []);

        $mock = new MockHandler([new ConnectException('refused', new Psr7Request('POST', 'https://erp.example.com'))]);

        $this->expectException(IntegrationSyncFailedException::class);

        $this->action(new Client(['handler' => HandlerStack::create($mock)]))->execute($data->id, $business->id);
    }

    private function action(Client $http): SyncCatalogToIntegrationAction
    {
        return new SyncCatalogToIntegrationAction(
            app(IntegrationConnectionRepositoryInterface::class),
            app(SearchCatalogAction::class),
            $http,
        );
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }
}
