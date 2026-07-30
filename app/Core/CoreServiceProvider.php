<?php

namespace App\Core;

use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\AgentTokenRepositoryInterface;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Infrastructure\Repositories\EloquentAgentRepository;
use App\Core\Infrastructure\Repositories\EloquentAgentTokenRepository;
use App\Core\Infrastructure\Repositories\EloquentTenantRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Core module bindings. Core is domain-independent (Decision 005)
 * and must never bind or reference classes belonging to a business domain.
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantRepositoryInterface::class, EloquentTenantRepository::class);
        $this->app->bind(AgentRepositoryInterface::class, EloquentAgentRepository::class);
        $this->app->bind(AgentTokenRepositoryInterface::class, EloquentAgentTokenRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
