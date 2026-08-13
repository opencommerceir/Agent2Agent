<?php

namespace App\Domains\Nexus\Developer\Application\DTOs;

use App\Domains\Nexus\Developer\Domain\Entities\AgentStrategyTemplate;

final class AgentStrategyTemplateData
{
    public function __construct(
        public readonly int $id,
        public readonly int $publisherBusinessId,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly string $descriptionFa,
        public readonly string $descriptionEn,
        public readonly ?string $personality,
        public readonly ?string $tone,
        public readonly array $strategies,
        public readonly int $priceCredits,
        public readonly int $installCount,
        public readonly bool $isRevoked,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(AgentStrategyTemplate $template): self
    {
        return new self(
            id: $template->id(),
            publisherBusinessId: $template->publisherBusinessId(),
            nameFa: $template->nameFa(),
            nameEn: $template->nameEn(),
            descriptionFa: $template->descriptionFa(),
            descriptionEn: $template->descriptionEn(),
            personality: $template->personality(),
            tone: $template->tone(),
            strategies: $template->strategies(),
            priceCredits: $template->priceCredits(),
            installCount: $template->installCount(),
            isRevoked: $template->isRevoked(),
            createdAt: $template->createdAt()->format(DATE_ATOM),
        );
    }
}
