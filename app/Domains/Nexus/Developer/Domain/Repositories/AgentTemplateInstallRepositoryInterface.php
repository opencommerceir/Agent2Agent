<?php

namespace App\Domains\Nexus\Developer\Domain\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\AgentTemplateInstall;

interface AgentTemplateInstallRepositoryInterface
{
    /**
     * @return list<AgentTemplateInstall>
     */
    public function findByInstallingBusinessId(int $businessId): array;

    public function save(AgentTemplateInstall $install): AgentTemplateInstall;
}
