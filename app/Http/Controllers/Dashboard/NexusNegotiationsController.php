<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Business\Domain\Entities\Business;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\ContractRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationMessageData;
use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard, never `business.auth`) — the
 * roadmap's "Live Negotiation Monitor" (CLAUDE.md Admin Panel Must-Haves,
 * #4), never built until now. Platform-wide by nature: an admin isn't a
 * party to any Negotiation, so this reads NegotiationRepositoryInterface::
 * findAll() (new — every other Negotiation query is party-scoped by
 * design) rather than going through the business-facing, party-checked
 * Actions (ListMyNegotiationsAction/GetNegotiationAction/
 * ListNegotiationMessagesAction/PollNegotiationMessagesAction all throw if
 * the caller isn't initiator or counterparty).
 *
 * Follows the same thin-controller-calls-repositories-directly shape
 * NexusEscrowController/NexusDisputeController already established for
 * admin-only read pages (no intermediate Action/DTO layer for the read
 * side) — but unlike those two, this resolves both parties' bilingual
 * names, since "negotiation #14 between business #3 and business #9" is
 * meaningless to a human admin the way raw ids are for Escrow/Dispute's
 * simpler single-id displays.
 */
class NexusNegotiationsController extends Controller
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly NegotiationMessageRepositoryInterface $messages,
        private readonly BusinessRepositoryInterface $businesses,
        private readonly ContractRepositoryInterface $contracts,
        private readonly EscrowRepositoryInterface $escrows,
    ) {
    }

    public function index(): View
    {
        $rows = array_map(
            fn (Negotiation $negotiation) => [
                'negotiation' => $negotiation,
                ...$this->resolvePartyNames($negotiation),
            ],
            $this->negotiations->findAll(),
        );

        return view('dashboard.nexus.negotiations.index', ['rows' => $rows]);
    }

    public function show(int $negotiation): View
    {
        $entity = $this->negotiations->findById($negotiation);

        abort_if(! $entity, 404);

        return view('dashboard.nexus.negotiations.show', [
            'negotiation' => $entity,
            ...$this->resolvePartyNames($entity),
            'messages' => $this->messages->findByNegotiationId($negotiation),
            'contract' => $this->contracts->findByNegotiationId($negotiation),
            'escrow' => $this->escrows->findByNegotiationId($negotiation),
        ]);
    }

    /**
     * The admin equivalent of the business-facing polling endpoint
     * (PollNegotiationMessagesAction) — same setInterval+fetch shape (M7
     * decision: no WebSocket/broadcast infra exists in this codebase), but
     * without the isParty() check that Action enforces, since an admin
     * session is never one of the two negotiating Businesses.
     */
    public function messages(int $negotiation, Request $request): JsonResponse
    {
        abort_if(! $this->negotiations->findById($negotiation), 404);

        $after = $request->integer('after');

        $messages = array_map(
            fn ($message) => NegotiationMessageData::fromEntity($message)->toArray(),
            $this->messages->findAfter($negotiation, $after),
        );

        return response()->json(['messages' => $messages]);
    }

    /**
     * @return array{initiatorNameFa: string, initiatorNameEn: string, counterpartyNameFa: string, counterpartyNameEn: string}
     */
    private function resolvePartyNames(Negotiation $negotiation): array
    {
        $initiator = $this->businesses->findById($negotiation->initiatorBusinessId());
        $counterparty = $this->businesses->findById($negotiation->counterpartyBusinessId());

        return [
            'initiatorNameFa' => $this->displayName($initiator, fn (Business $b) => $b->nameFa()),
            'initiatorNameEn' => $this->displayName($initiator, fn (Business $b) => $b->nameEn()),
            'counterpartyNameFa' => $this->displayName($counterparty, fn (Business $b) => $b->nameFa()),
            'counterpartyNameEn' => $this->displayName($counterparty, fn (Business $b) => $b->nameEn()),
        ];
    }

    private function displayName(?Business $business, callable $pick): string
    {
        return $business ? $pick($business) : '—';
    }
}
