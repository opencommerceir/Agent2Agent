<?php

namespace Tests\Unit\Shipping;

use App\Modules\Shipping\Application\Services\ShippingProviderRegistry;
use App\Modules\Shipping\Domain\Exceptions\ShippingProviderNotFoundException;
use App\Modules\Shipping\Infrastructure\Http\MockShippingHttpClient;
use App\Modules\Shipping\Infrastructure\Providers\MockShippingProviderAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors Commerce's own ConnectorRegistry test coverage shape — a plain
 * in-memory lookup keyed by provider name.
 */
class ShippingProviderRegistryTest extends TestCase
{
    public function test_register_thenGet_returnsTheSameProvider(): void
    {
        $registry = new ShippingProviderRegistry();
        $provider = new MockShippingProviderAdapter(new MockShippingHttpClient());

        $registry->register('mock', $provider);

        $this->assertSame($provider, $registry->get('mock'));
    }

    public function test_get_forUnregisteredName_throwsShippingProviderNotFoundException(): void
    {
        $registry = new ShippingProviderRegistry();

        $this->expectException(ShippingProviderNotFoundException::class);

        $registry->get('usps');
    }

    public function test_registered_listsEveryRegisteredName(): void
    {
        $registry = new ShippingProviderRegistry();
        $registry->register('mock', new MockShippingProviderAdapter(new MockShippingHttpClient()));

        $this->assertSame(['mock'], $registry->registered());
    }
}
