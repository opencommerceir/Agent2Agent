<?php

namespace App\Domains\Nexus\Negotiation\Application\Services;

use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;

/**
 * "قبل از هر پاسخ: think() ... بعد از هر پاسخ: reflect()"
 * (docs/nexus-roadmap.md, Phase 2). AgentOrchestrator's own
 * ReasoningEngineInterface/ReasoningTrace is hard-wired to Goal/
 * ExecutionResult/a fixed 4-persona AgentType and doesn't transfer here
 * (confirmed by research before this milestone) — this is a new,
 * Negotiation-owned service producing the same *shape* (thoughts[],
 * confidence, decision, explanation) from plain deterministic rules,
 * consistent with the project's own "Rule Engine 80%, zero-cost by
 * default" LLM strategy (docs/claude/llm-strategy.md). Real LLM-backed
 * negotiation reasoning is a natural follow-up, not this phase — nothing
 * here calls out to any LLMClientInterface.
 *
 * Each `for*()` method corresponds to one NegotiationMessageType — called
 * by the matching Negotiation Action right before it persists the
 * NegotiationMessage, populating its `reasoning` column.
 */
final class NegotiationReasoningService
{
    /**
     * @return array{thoughts: list<string>, confidence: float, decision: string, explanation: string}
     */
    public function forProposal(NegotiationTerms $terms): array
    {
        return [
            'thoughts' => [
                sprintf('Opening proposal: %s for %d unit(s).', (string) $terms->price(), $terms->quantity()),
            ],
            'confidence' => 0.7,
            'decision' => 'propose',
            'explanation' => 'Initial offer based on the requested terms; no prior counter-offer to compare against yet.',
        ];
    }

    /**
     * @return array{thoughts: list<string>, confidence: float, decision: string, explanation: string}
     */
    public function forCounter(NegotiationTerms $previousTerms, NegotiationTerms $newTerms, int $roundCount, int $maxRounds): array
    {
        $previousAmount = $previousTerms->price()->amount();
        $newAmount = $newTerms->price()->amount();
        $deltaPercent = $previousAmount === 0 ? 0.0 : round((($newAmount - $previousAmount) / $previousAmount) * 100, 1);

        $thoughts = [
            sprintf('Previous terms: %s. New terms: %s (%.1f%% change).', (string) $previousTerms->price(), (string) $newTerms->price(), $deltaPercent),
            sprintf('Round %d of a maximum of %d.', $roundCount + 1, $maxRounds),
        ];

        $roundsRemaining = $maxRounds - $roundCount;
        if ($roundsRemaining <= 1) {
            $thoughts[] = 'Approaching the round limit — consider accepting or rejecting soon rather than countering again.';
        }

        // Confidence rises as the gap between offers narrows — a small
        // delta suggests both sides are converging toward agreement.
        $confidence = max(0.3, min(0.95, 1 - (abs($deltaPercent) / 100)));

        return [
            'thoughts' => $thoughts,
            'confidence' => round($confidence, 2),
            'decision' => 'counter',
            'explanation' => sprintf('Countering with a %.1f%% adjustment from the previous terms.', $deltaPercent),
        ];
    }

    /**
     * @return array{thoughts: list<string>, confidence: float, decision: string, explanation: string}
     */
    public function forAccept(NegotiationTerms $terms, bool $exceedsAuthorityLimit): array
    {
        if ($exceedsAuthorityLimit) {
            return [
                'thoughts' => [
                    sprintf('Terms total %s, which exceeds this Agent\'s configured authority limit.', number_format($terms->totalAmount() / 100, 2)),
                    'Pausing for human approval rather than committing autonomously.',
                ],
                'confidence' => 0.5,
                'decision' => 'request_approval',
                'explanation' => "Agentها نمی‌توانند بدون تأیید انسان اقدامات پرارزش انجام دهند — deal value is above the configured max_deal_value.",
            ];
        }

        return [
            'thoughts' => [
                sprintf('Terms total %s, within this Agent\'s authority limit.', number_format($terms->totalAmount() / 100, 2)),
            ],
            'confidence' => 0.95,
            'decision' => 'accept',
            'explanation' => 'Terms are within authorized limits — accepting autonomously.',
        ];
    }

    /**
     * @return array{thoughts: list<string>, confidence: float, decision: string, explanation: string}
     */
    public function forReject(NegotiationTerms $terms, ?string $reason): array
    {
        return [
            'thoughts' => array_values(array_filter([
                sprintf('Rejecting terms of %s.', (string) $terms->price()),
                $reason ? "Reason: {$reason}" : null,
            ])),
            'confidence' => 0.9,
            'decision' => 'reject',
            'explanation' => $reason ?? 'Terms were not acceptable.',
        ];
    }
}
