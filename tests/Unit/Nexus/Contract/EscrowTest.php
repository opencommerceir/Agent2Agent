<?php

namespace Tests\Unit\Nexus\Contract;

use App\Domains\Nexus\Contract\Domain\Entities\Escrow;
use App\Domains\Nexus\Contract\Domain\ValueObjects\EscrowStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EscrowTest extends TestCase
{
    private function newEscrow(int $grossAmount = 10_000_000, float $feePercent = 0.5): Escrow
    {
        return Escrow::hold(
            contractId: 1,
            negotiationId: 1,
            businessAId: 10,
            businessBId: 20,
            grossAmount: $grossAmount,
            currency: 'IRT',
            platformFeePercent: $feePercent,
        );
    }

    public function test_hold_computesFeeAndNetAmount(): void
    {
        $escrow = $this->newEscrow(10_000_000, 0.5);

        $this->assertSame(10_000_000, $escrow->grossAmount());
        $this->assertSame(50_000, $escrow->platformFeeAmount());
        $this->assertSame(9_950_000, $escrow->netAmount());
        $this->assertSame(EscrowStatus::Held, $escrow->status());
    }

    public function test_isParty_recognizesBothSides(): void
    {
        $escrow = $this->newEscrow();

        $this->assertTrue($escrow->isParty(10));
        $this->assertTrue($escrow->isParty(20));
        $this->assertFalse($escrow->isParty(30));
    }

    public function test_release_transitionsFromHeld(): void
    {
        $escrow = $this->newEscrow();

        $escrow->release();

        $this->assertSame(EscrowStatus::Released, $escrow->status());
        $this->assertNotNull($escrow->releasedAt());
    }

    public function test_dispute_transitionsFromHeldAndRecordsReason(): void
    {
        $escrow = $this->newEscrow();

        $escrow->dispute('item never arrived');

        $this->assertSame(EscrowStatus::Disputed, $escrow->status());
        $this->assertSame('item never arrived', $escrow->disputeReason());
    }

    public function test_refund_transitionsFromDisputed(): void
    {
        $escrow = $this->newEscrow();
        $escrow->dispute('reason');

        $escrow->refund();

        $this->assertSame(EscrowStatus::Refunded, $escrow->status());
    }

    public function test_refund_fromHeld_throws(): void
    {
        $escrow = $this->newEscrow();

        $this->expectException(InvalidArgumentException::class);

        $escrow->refund();
    }

    public function test_release_afterReleased_throws(): void
    {
        $escrow = $this->newEscrow();
        $escrow->release();

        $this->expectException(InvalidArgumentException::class);

        $escrow->release();
    }
}
