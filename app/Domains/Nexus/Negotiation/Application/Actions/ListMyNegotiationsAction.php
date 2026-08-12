<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;

/**
 * Backs the Live Negotiation Viewer's entry point (M7) — every
 * Negotiation a Business is a party to, newest first
 * (NegotiationRepositoryInterface::findVisibleTo() already orders this
 * way), with the counterparty's bilingual name resolved for display
 * (the list view is meaningless with only raw business ids).
 */
final class ListMyNegotiationsAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    /**
     * @return list<array{negotiation: NegotiationData, counterpartyNameFa: string, counterpartyNameEn: string}>
     */
    public function execute(int $businessId): array
    {
        return array_map(function ($negotiation) use ($businessId) {
            $counterparty = $this->businesses->findById($negotiation->otherParty($businessId));

            return [
                'negotiation' => NegotiationData::fromEntity($negotiation),
                'counterpartyNameFa' => $counterparty?->nameFa() ?? '—',
                'counterpartyNameEn' => $counterparty?->nameEn() ?? '—',
            ];
        }, $this->negotiations->findVisibleTo($businessId));
    }
}
