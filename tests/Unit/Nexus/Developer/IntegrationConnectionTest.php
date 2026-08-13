<?php

namespace Tests\Unit\Nexus\Developer;

use App\Domains\Nexus\Developer\Domain\Entities\IntegrationConnection;
use App\Domains\Nexus\Developer\Domain\ValueObjects\IntegrationCategory;
use PHPUnit\Framework\TestCase;

class IntegrationConnectionTest extends TestCase
{
    public function test_mapItem_withMapping_onlyIncludesMappedKeysRenamed(): void
    {
        $connection = IntegrationConnection::create(1, IntegrationCategory::Erp, 'My ERP', 'https://erp.example.com', null, [
            'nameEn' => 'product_name',
            'priceAmount' => 'unit_price',
        ]);

        $mapped = $connection->mapItem(['nameEn' => 'Widget', 'priceAmount' => 10_000, 'nameFa' => 'ویجت']);

        $this->assertSame(['product_name' => 'Widget', 'unit_price' => 10_000], $mapped);
    }

    public function test_mapItem_withoutMapping_passesThroughUnchanged(): void
    {
        $connection = IntegrationConnection::create(1, IntegrationCategory::Crm, 'My CRM', 'https://crm.example.com', null, []);

        $item = ['nameEn' => 'Widget', 'priceAmount' => 10_000];

        $this->assertSame($item, $connection->mapItem($item));
    }

    public function test_revoke_marksRevoked(): void
    {
        $connection = IntegrationConnection::create(1, IntegrationCategory::Logistics, 'My WMS', 'https://wms.example.com', null, []);

        $connection->revoke();

        $this->assertTrue($connection->isRevoked());
    }
}
