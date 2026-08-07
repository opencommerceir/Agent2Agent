<?php

namespace OpenCommerce\SDK\Laravel\Tests;

use OpenCommerce\SDK\Laravel\Facades\OpenCommerce;
use OpenCommerce\SDK\Laravel\OpenCommerceServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [OpenCommerceServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['OpenCommerce' => OpenCommerce::class];
    }
}
