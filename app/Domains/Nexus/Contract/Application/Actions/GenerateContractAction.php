<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Contract\Application\DTOs\ContractData;
use App\Domains\Nexus\Contract\Domain\Entities\Contract;
use App\Domains\Nexus\Contract\Domain\Events\ContractWasGenerated;
use App\Domains\Nexus\Contract\Domain\Repositories\ContractRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * "تولید خودکار قرارداد از روی negotiation ... امضای دیجیتال (hash) و
 * خروجی PDF" (docs/nexus-roadmap.md, Phase 2) — called by
 * GenerateContractOnNegotiationAcceptedListener reacting to
 * NegotiationWasAccepted (event-driven, same rule Phase 1's Business ->
 * Agent auto-creation already established), never a direct cross-domain
 * call from Negotiation.
 *
 * $terms is a language-neutral structured snapshot (business names in
 * both languages, price/quantity as plain numbers — numbers have no
 * language). The bilingual *document* itself is the PDF Blade view
 * rendering this same snapshot twice, once per language section — a
 * single bilingual document, not two separate monolingual ones, the
 * common real-world shape for cross-border trade contracts.
 */
final class GenerateContractAction
{
    public function __construct(
        private readonly ContractRepositoryInterface $contracts,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function execute(Negotiation $negotiation): ContractData
    {
        $initiator = $this->businesses->findById($negotiation->initiatorBusinessId());
        $counterparty = $this->businesses->findById($negotiation->counterpartyBusinessId());

        if (! $initiator || ! $counterparty) {
            throw new InvalidArgumentException('Both parties to the Negotiation must exist to generate a Contract.');
        }

        $terms = [
            'negotiationId' => $negotiation->id(),
            'initiator' => ['businessId' => $initiator->id(), 'nameFa' => $initiator->nameFa(), 'nameEn' => $initiator->nameEn()],
            'counterparty' => ['businessId' => $counterparty->id(), 'nameFa' => $counterparty->nameFa(), 'nameEn' => $counterparty->nameEn()],
            'catalogItemType' => $negotiation->catalogItemType()->value,
            'catalogItemId' => $negotiation->catalogItemId(),
            'priceAmount' => $negotiation->currentTerms()->price()->amount(),
            'priceCurrency' => $negotiation->currentTerms()->price()->currency(),
            'quantity' => $negotiation->currentTerms()->quantity(),
            'agreedAt' => now()->toIso8601String(),
        ];

        $contract = Contract::generate(
            negotiationId: $negotiation->id(),
            businessAId: $negotiation->initiatorBusinessId(),
            businessBId: $negotiation->counterpartyBusinessId(),
            terms: $terms,
        );
        $contract = $this->contracts->save($contract);

        $pdfBytes = Pdf::loadView('nexus::contracts.pdf', ['contract' => $contract])->output();
        $path = "nexus/contracts/{$contract->id()}.pdf";
        Storage::disk('public')->put($path, $pdfBytes);

        $contract->attachPdf($path);
        $contract = $this->contracts->save($contract);

        Event::dispatch(new ContractWasGenerated($contract));

        return ContractData::fromEntity($contract);
    }
}
