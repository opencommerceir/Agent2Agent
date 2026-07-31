<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ProviderRateData;
use App\Modules\Shipping\Application\Services\ShippingProviderRegistry;
use App\Modules\Shipping\Domain\ValueObjects\Address;
use App\Modules\Shipping\Domain\ValueObjects\Weight;

/**
 * A pure preview, no side effects — same "preview vs. durable apply"
 * split `CalculateShippingRateAction` (the local-calculator equivalent)
 * already establishes. Resolves the named provider from
 * `ShippingProviderRegistry` (throws `ShippingProviderNotFoundException`,
 * a real 404, for an unregistered name) and asks it for live rates.
 */
final class GetProviderRatesAction
{
    public function __construct(
        private readonly ShippingProviderRegistry $providers,
    ) {
    }

    /**
     * @param array<string, mixed> $destination
     * @return list<ProviderRateData>
     */
    public function execute(int $tenantId, string $providerName, int $weightGrams, array $destination): array
    {
        $provider = $this->providers->get($providerName);

        $rates = $provider->getRates(new Weight($weightGrams), Address::fromArray($destination));

        return array_map(fn ($rate) => ProviderRateData::fromValueObject($rate), $rates);
    }
}
