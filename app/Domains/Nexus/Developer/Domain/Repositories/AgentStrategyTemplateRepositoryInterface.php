<?php

namespace App\Domains\Nexus\Developer\Domain\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\AgentStrategyTemplate;

interface AgentStrategyTemplateRepositoryInterface
{
    public function findById(int $id): ?AgentStrategyTemplate;

    /**
     * @return list<AgentStrategyTemplate>
     */
    public function findActive(?string $query): array;

    /**
     * @return list<AgentStrategyTemplate>
     */
    public function findByPublisherBusinessId(int $businessId): array;

    public function save(AgentStrategyTemplate $template): AgentStrategyTemplate;
}
