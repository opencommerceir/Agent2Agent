<?php

namespace App\Modules\Shipping;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Shipping\Application\Actions\AddTrackingEventAction;
use App\Modules\Shipping\Application\Actions\CalculateShippingRateAction;
use App\Modules\Shipping\Application\Actions\CreateProviderShipmentAction;
use App\Modules\Shipping\Application\Actions\CreateShipmentAction;
use App\Modules\Shipping\Application\Actions\CreateShippingMethodAction;
use App\Modules\Shipping\Application\Actions\GetProviderRatesAction;
use App\Modules\Shipping\Application\Actions\GetShipmentAction;
use App\Modules\Shipping\Application\Actions\ListShipmentsAction;
use App\Modules\Shipping\Application\Actions\ListShippingMethodsAction;
use App\Modules\Shipping\Application\Actions\SyncTrackingAction;
use App\Modules\Shipping\Application\Actions\UpdateShipmentStatusAction;
use App\Modules\Shipping\Application\DTOs\ShipmentData;
use App\Modules\Shipping\Application\DTOs\ShippingMethodData;
use App\Modules\Shipping\Application\Services\ShippingHttpClientInterface;
use App\Modules\Shipping\Application\Services\ShippingProviderConfig;
use App\Modules\Shipping\Application\Services\ShippingProviderRegistry;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;
use App\Modules\Shipping\Infrastructure\Http\MockShippingHttpClient;
use App\Modules\Shipping\Infrastructure\Providers\MockShippingProviderAdapter;
use App\Modules\Shipping\Infrastructure\Repositories\EloquentShipmentRepository;
use App\Modules\Shipping\Infrastructure\Repositories\EloquentShippingMethodRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Shipping module — Phase 4, Stage 1, the natural
 * continuation of Commerce (rule §a). Unlike every Phase 3 module,
 * Shipping requires one additive change to Commerce itself:
 * `Order::assignShipping()` (three new nullable fields — see that
 * method's own docblock for the full reasoning, and
 * `2026_07_31_000042_add_shipping_to_orders_table.php` for the schema
 * side). Everything else follows the established one-directional
 * Module -> Module Dependency Inversion pattern: Shipping depends on
 * Commerce's `OrderRepositoryInterface`/`ProductRepositoryInterface`
 * (Interfaces, never Commerce's Infrastructure/Model classes).
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows the
 * established seeder pattern instead (ShippingCapabilitiesSeeder), same
 * RefreshDatabase-ordering reason documented there.
 *
 * Stage 2 (Shipping Provider Connector) added the module's own Connector
 * Pattern demonstration — `ShippingProviderRegistry`/`ShippingHttpClientInterface`/
 * `MockShippingProviderAdapter` mirror Commerce's own
 * `ConnectorRegistry`/`WooCommerceClientInterface`/`WooCommerceProductConnector`
 * exactly (see `MockShippingProviderAdapter`'s own docblock).
 */
class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShippingMethodRepositoryInterface::class, EloquentShippingMethodRepository::class);
        $this->app->bind(ShipmentRepositoryInterface::class, EloquentShipmentRepository::class);

        $this->app->singleton(ShippingProviderRegistry::class);

        $this->app->bind(ShippingHttpClientInterface::class, fn () => new MockShippingHttpClient());
    }

    public function boot(): void
    {
        $providers = $this->app->make(ShippingProviderRegistry::class);
        $providers->register('mock', new MockShippingProviderAdapter($this->app->make(ShippingHttpClientInterface::class)));

        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('shipping.method.create', function (array $input, AuthContext $context) {
            /** @var ShippingMethodData $method */
            $method = $this->app->make(CreateShippingMethodAction::class)->execute(
                tenantId: $context->tenantId,
                name: $input['name'],
                baseRate: (int) $input['base_rate'],
                ratePerKg: (int) $input['rate_per_kg'],
                estimatedDaysMin: (int) $input['estimated_days_min'],
                estimatedDaysMax: (int) $input['estimated_days_max'],
                currency: $input['currency'] ?? 'USD',
                description: $input['description'] ?? null,
            );

            return ['method' => $method->toArray()];
        });

        $handlers->register(
            'shipping.method.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListShippingMethodsAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('shipping.rate.calculate', function (array $input, AuthContext $context) {
            $rate = $this->app->make(CalculateShippingRateAction::class)->execute(
                $context->tenantId,
                (int) $input['shipping_method_id'],
                (int) $input['weight_grams'],
            );

            return ['rate' => $rate->toArray()];
        });

        $handlers->register('shipping.shipment.create', function (array $input, AuthContext $context) {
            /** @var ShipmentData $shipment */
            $shipment = $this->app->make(CreateShipmentAction::class)->execute(
                $context->tenantId,
                (int) $input['order_id'],
                (int) $input['shipping_method_id'],
            );

            return ['shipment' => $shipment->toArray()];
        });

        $handlers->register('shipping.shipment.get', function (array $input, AuthContext $context) {
            $shipment = $this->app->make(GetShipmentAction::class)->execute((int) $input['shipment_id'], $context->tenantId);

            return ['shipment' => $shipment->toArray()];
        });

        $handlers->register(
            'shipping.shipment.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListShipmentsAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('shipping.shipment.transition', function (array $input, AuthContext $context) {
            $shipment = $this->app->make(UpdateShipmentStatusAction::class)->execute(
                $context->tenantId,
                (int) $input['shipment_id'],
                $input['status'],
            );

            return ['shipment' => $shipment->toArray()];
        });

        $handlers->register('shipping.tracking.add', function (array $input, AuthContext $context) {
            $event = $this->app->make(AddTrackingEventAction::class)->execute(
                tenantId: $context->tenantId,
                shipmentId: (int) $input['shipment_id'],
                status: $input['status'],
                description: $input['description'],
                location: $input['location'] ?? null,
            );

            return ['event' => $event->toArray()];
        });

        $handlers->register('shipping.provider.rates', function (array $input, AuthContext $context) {
            $rates = $this->app->make(GetProviderRatesAction::class)->execute(
                $context->tenantId,
                $input['provider'] ?? ShippingProviderConfig::fromConfig()->defaultProvider,
                (int) $input['weight_grams'],
                $input['destination'],
            );

            return ['rates' => array_map(fn ($rate) => $rate->toArray(), $rates)];
        });

        $handlers->register('shipping.provider.fulfill', function (array $input, AuthContext $context) {
            $providerShipment = $this->app->make(CreateProviderShipmentAction::class)->execute(
                $context->tenantId,
                $input['provider'] ?? ShippingProviderConfig::fromConfig()->defaultProvider,
                (int) $input['shipment_id'],
            );

            return ['provider_shipment' => $providerShipment->toArray()];
        });

        $handlers->register('shipping.tracking.sync', fn (array $input, AuthContext $context) => $this->app->make(SyncTrackingAction::class)->execute(
            $context->tenantId,
            $input['provider'] ?? ShippingProviderConfig::fromConfig()->defaultProvider,
            $input['tracking_number'],
        ));
    }
}
