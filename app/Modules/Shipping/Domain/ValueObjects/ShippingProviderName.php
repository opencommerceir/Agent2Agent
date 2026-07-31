<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

/**
 * Only `Mock` has a real `ShippingProviderInterface` implementation this
 * stage (`MockShippingProviderAdapter`) — `Usps`/`Fedex`/`Dhl` are modeled,
 * real future intents with no implementation yet, the same
 * "modeled-but-unfulfilled" shape `RewardType::FreeProduct`/
 * `EventType::CartAbandoned` already have elsewhere in this codebase.
 */
enum ShippingProviderName: string
{
    case Mock = 'mock';
    case Usps = 'usps';
    case Fedex = 'fedex';
    case Dhl = 'dhl';
}
