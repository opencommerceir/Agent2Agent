<?php

namespace OpenCommerce\SDK\Tests;

use OpenCommerce\SDK\Config\MCPConfig;
use PHPUnit\Framework\TestCase;

class MCPConfigTest extends TestCase
{
    public function test_constructor_stillAcceptsAFullyQualifiedBaseUrlDirectly(): void
    {
        $config = new MCPConfig(baseUrl: 'https://api.opencommerce.ir/mcp/v1', token: 'agent_token');

        $this->assertSame('https://api.opencommerce.ir/mcp/v1', $config->baseUrl);
    }

    public function test_forVersion_buildsTheBaseUrlFromHostAndVersion(): void
    {
        $config = MCPConfig::forVersion(host: 'https://api.opencommerce.ir', version: 'v2', token: 'agent_token');

        $this->assertSame('https://api.opencommerce.ir/mcp/v2', $config->baseUrl);
        $this->assertSame('agent_token', $config->token);
    }

    public function test_forVersion_trimsATrailingSlashFromTheHost(): void
    {
        $config = MCPConfig::forVersion(host: 'https://api.opencommerce.ir/', version: 'v1', token: 'agent_token');

        $this->assertSame('https://api.opencommerce.ir/mcp/v1', $config->baseUrl);
    }

    public function test_forVersion_passesThroughTimeoutAndVerifySSL(): void
    {
        $config = MCPConfig::forVersion(
            host: 'https://api.opencommerce.ir',
            version: 'v2',
            token: 'agent_token',
            timeout: 5,
            verifySSL: false,
        );

        $this->assertSame(5, $config->timeout);
        $this->assertFalse($config->verifySSL);
    }
}
