<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\AgentData;
use App\Core\Domain\Entities\Agent;
use App\Core\Domain\Events\AgentWasRegistered;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\ValueObjects\AgentType;
use Illuminate\Support\Facades\Event;

/**
 * One Action = one business operation (Application Layer Rules):
 * register a new Agent identity and dispatch the corresponding domain event.
 *
 * No longer accepts a $permissions argument — granting access now happens
 * through CreateRoleAction + AssignPermissionToRoleAction +
 * AssignRoleToMemberAction (Phase 3), not at registration time.
 *
 * Does not verify that $organizationId belongs to $tenantId — the
 * Organization Repository/Action pair owns that check. The
 * `organizations.tenant_id` foreign key still protects data integrity in
 * the meantime.
 */
final class RegisterAgentAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(
        int $tenantId,
        int $organizationId,
        string $name,
        string $type,
    ): AgentData {
        $agent = Agent::register(
            tenantId: $tenantId,
            organizationId: $organizationId,
            name: $name,
            type: AgentType::from($type),
        );

        $agent = $this->agents->save($agent);

        Event::dispatch(new AgentWasRegistered($agent));

        return AgentData::fromEntity($agent);
    }
}
