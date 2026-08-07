<?php

namespace OpenCommerce\SDK\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OpenCommerce\SDK\MCPClient;

/**
 * @method static list<\OpenCommerce\SDK\DTOs\Capability> discoverCapabilities()
 * @method static \OpenCommerce\SDK\Execution\ExecutionResult execute(string $capabilityName, array $input = [])
 * @method static \OpenCommerce\SDK\DTOs\Capability getCapability(string $capabilityName)
 *
 * @see \OpenCommerce\SDK\MCPClient
 */
final class OpenCommerce extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MCPClient::class;
    }
}
