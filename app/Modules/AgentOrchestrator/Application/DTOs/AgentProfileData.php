<?php

namespace App\Modules\AgentOrchestrator\Application\DTOs;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;

final class AgentProfileData
{
    /**
     * @param array<string, list<string>> $planningRules
     * @param array<string, array<string, mixed>> $defaultInputs
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $description,
        public readonly array $planningRules,
        public readonly array $defaultInputs,
        public readonly array $permissions,
    ) {
    }

    public static function fromEntity(AgentProfile $profile): self
    {
        return new self(
            type: $profile->type->value,
            name: $profile->name,
            description: $profile->description,
            planningRules: $profile->planningRules(),
            defaultInputs: $profile->defaultInputs(),
            permissions: $profile->permissions,
        );
    }

    /**
     * @return array{type: string, name: string, description: string, planningRules: array, defaultInputs: array, permissions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'planningRules' => $this->planningRules,
            'defaultInputs' => $this->defaultInputs,
            'permissions' => $this->permissions,
        ];
    }
}
