<?php

namespace App\Core;

use App\Core\Application\Listeners\RevokeRolesWhenMemberRemovedFromOrganization;
use App\Core\Domain\Events\MemberRemovedFromOrganization;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\AgentTokenRepositoryInterface;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;
use App\Core\Domain\Repositories\MemberRoleRepositoryInterface;
use App\Core\Domain\Repositories\OrganizationMemberRepositoryInterface;
use App\Core\Domain\Repositories\OrganizationRepositoryInterface;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\Repositories\RoleRepositoryInterface;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Infrastructure\Repositories\EloquentAgentRepository;
use App\Core\Infrastructure\Repositories\EloquentAgentTokenRepository;
use App\Core\Infrastructure\Repositories\EloquentCapabilityRepository;
use App\Core\Infrastructure\Repositories\EloquentMemberRoleRepository;
use App\Core\Infrastructure\Repositories\EloquentOrganizationMemberRepository;
use App\Core\Infrastructure\Repositories\EloquentOrganizationRepository;
use App\Core\Infrastructure\Repositories\EloquentPermissionRepository;
use App\Core\Infrastructure\Repositories\EloquentRoleRepository;
use App\Core\Infrastructure\Repositories\EloquentTenantRepository;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(OrganizationRepositoryInterface::class, EloquentOrganizationRepository::class);
        $this->app->bind(OrganizationMemberRepositoryInterface::class, EloquentOrganizationMemberRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->bind(MemberRoleRepositoryInterface::class, EloquentMemberRoleRepository::class);
        $this->app->bind(CapabilityRepositoryInterface::class, EloquentCapabilityRepository::class);
    }

    public function boot(): void
    {
        Event::listen(MemberRemovedFromOrganization::class, RevokeRolesWhenMemberRemovedFromOrganization::class);

        $this->loadRoutesFrom(base_path('routes/mcp.php'));
    }
}
