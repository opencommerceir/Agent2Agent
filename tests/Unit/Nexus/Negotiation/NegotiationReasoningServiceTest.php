<?php

namespace Tests\Unit\Nexus\Negotiation;

use App\Domains\Nexus\Negotiation\Application\Services\NegotiationReasoningService;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use PHPUnit\Framework\TestCase;

/**
 * Deterministic, rule-based — no LLM/network involved (decision #5,
 * docs/nexus/nexus_handoff.md Phase 2/M5). Framework-free, so plain
 * PHPUnit is enough (no Laravel bootstrap needed).
 */
class NegotiationReasoningServiceTest extends TestCase
{
    private NegotiationReasoningService $reasoning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reasoning = new NegotiationReasoningService();
    }

    public function test_forProposal_returnsProposeDecisionWithThoughts(): void
    {
        $result = $this->reasoning->forProposal(new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null));

        $this->assertSame('propose', $result['decision']);
        $this->assertNotEmpty($result['thoughts']);
        $this->assertGreaterThan(0, $result['confidence']);
    }

    public function test_forCounter_computesPriceDeltaPercent(): void
    {
        $previous = new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null);
        $new = new NegotiationTerms(Money::fromAmount(90000, 'IRT'), 1, null);

        $result = $this->reasoning->forCounter($previous, $new, roundCount: 1, maxRounds: 5);

        $this->assertSame('counter', $result['decision']);
        $this->assertStringContainsString('-10.0%', $result['thoughts'][0]);
    }

    public function test_forCounter_nearRoundLimit_addsWarningThought(): void
    {
        $terms = new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null);

        $result = $this->reasoning->forCounter($terms, $terms, roundCount: 4, maxRounds: 5);

        $this->assertStringContainsString('Approaching the round limit', implode(' ', $result['thoughts']));
    }

    public function test_forAccept_withinLimit_decidesAccept(): void
    {
        $terms = new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null);

        $result = $this->reasoning->forAccept($terms, exceedsAuthorityLimit: false);

        $this->assertSame('accept', $result['decision']);
        $this->assertGreaterThan(0.9, $result['confidence']);
    }

    public function test_forAccept_beyondLimit_decidesRequestApproval(): void
    {
        $terms = new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null);

        $result = $this->reasoning->forAccept($terms, exceedsAuthorityLimit: true);

        $this->assertSame('request_approval', $result['decision']);
    }

    public function test_forReject_includesReasonInThoughts(): void
    {
        $terms = new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null);

        $result = $this->reasoning->forReject($terms, 'too expensive');

        $this->assertSame('reject', $result['decision']);
        $this->assertStringContainsString('too expensive', implode(' ', $result['thoughts']));
    }

    public function test_forReject_withoutReason_stillProducesThoughts(): void
    {
        $terms = new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null);

        $result = $this->reasoning->forReject($terms, null);

        $this->assertCount(1, $result['thoughts']);
        $this->assertSame(array_values($result['thoughts']), $result['thoughts']);
    }
}
