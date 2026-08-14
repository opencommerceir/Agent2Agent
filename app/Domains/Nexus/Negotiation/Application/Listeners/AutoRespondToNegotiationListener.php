<?php

namespace App\Domains\Nexus\Negotiation\Application\Listeners;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\SendCounterOfferAction;
use App\Domains\Nexus\Negotiation\Application\Services\AutonomousNegotiationStrategy;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationMessageWasRecorded;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The reactive half of the Autonomous Agent Runtime — a Business whose
 * Agent has `autoRespondEnabled()` reacts to an incoming Proposal/Counter
 * on its own, without waiting for a human or an external Agent client to
 * call MCP. Deliberately a plain, synchronous Listener (no `ShouldQueue`):
 * `QUEUE_CONNECTION=database` in this project means a queued Job would
 * silently never run without a real `php artisan queue:work` process —
 * running inline instead makes autonomous negotiation work out of the box,
 * and the decision itself is cheap rule-based arithmetic (AutonomousNegotiationStrategy),
 * not a network LLM call, so inline execution costs milliseconds.
 *
 * This naturally cascades: calling SendCounterOfferAction here dispatches
 * NegotiationMessageWasRecorded again, so the *other* party's own
 * auto-responder (if enabled) reacts in turn, within the same call stack —
 * bounded by Negotiation's own round-limit guard (default 5 rounds), so a
 * negotiation between two autonomous Agents can resolve to Accepted/Rejected
 * entirely within the original propose() call, in well under a second.
 *
 * Must never let an exception escape — this runs inline inside whatever
 * request/command originally recorded the message (an MCP call, the admin
 * demo command, an AutoDiscover automation run); a failure here degrades
 * to "no autonomous response this time," never breaks the caller.
 */
final class AutoRespondToNegotiationListener
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly AgentRepositoryInterface $agents,
        private readonly AutonomousNegotiationStrategy $strategy,
        private readonly SendCounterOfferAction $sendCounterOffer,
        private readonly AcceptDealAction $acceptDeal,
        private readonly RejectDealAction $rejectDeal,
    ) {
    }

    public function handle(NegotiationMessageWasRecorded $event): void
    {
        try {
            $this->respond($event);
        } catch (Throwable $e) {
            Log::warning('AutoRespondToNegotiationListener failed — no autonomous response sent.', [
                'negotiationId' => $event->negotiation->id(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function respond(NegotiationMessageWasRecorded $event): void
    {
        $respondingBusinessId = $event->negotiation->otherParty($event->message->senderBusinessId());

        $agent = $this->agents->findByBusinessId($respondingBusinessId);

        if (! $agent || ! $agent->autoRespondEnabled()) {
            return;
        }

        // Re-fetch fresh state rather than trusting the event's own
        // snapshot — cheap, and correct if anything else already acted on
        // this Negotiation earlier in the same synchronous cascade.
        $negotiation = $this->negotiations->findById($event->negotiation->id());

        if (! $negotiation || ! in_array($negotiation->status(), [NegotiationStatus::Proposed, NegotiationStatus::Countered], true)) {
            return;
        }

        $decision = $this->strategy->decide(
            $negotiation,
            $event->message->terms(),
            $respondingBusinessId,
            $agent->negotiationTolerancePercent(),
        );

        match ($decision['action']) {
            'accept' => $this->acceptDeal->execute($negotiation->id(), $respondingBusinessId),
            'counter' => $this->sendCounterOffer->execute($negotiation->id(), $respondingBusinessId, $decision['terms']),
            'reject' => $this->rejectDeal->execute($negotiation->id(), $respondingBusinessId, 'Outside my Agent\'s acceptable range.'),
        };
    }
}
