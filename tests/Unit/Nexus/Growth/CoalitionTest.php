<?php

namespace Tests\Unit\Nexus\Growth;

use App\Domains\Nexus\Growth\Domain\Entities\Coalition;
use App\Domains\Nexus\Growth\Domain\Exceptions\InvalidCoalitionStateException;
use App\Domains\Nexus\Growth\Domain\ValueObjects\CoalitionStatus;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoalitionTest extends TestCase
{
    private function coalition(float $discountPercent = 10.0): Coalition
    {
        return Coalition::form(
            organizerBusinessId: 1,
            targetBusinessId: 2,
            catalogItemType: CatalogItemType::Product,
            catalogItemId: 5,
            unitPrice: Money::fromAmount(10000, 'IRT'),
            minParticipants: 3,
            discountPercent: $discountPercent,
        );
    }

    public function test_form_startsInForming(): void
    {
        $coalition = $this->coalition();

        $this->assertSame(CoalitionStatus::Forming, $coalition->status());
        $this->assertNull($coalition->negotiationId());
    }

    public function test_form_organizerEqualsTarget_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Coalition::form(1, 1, CatalogItemType::Product, 5, Money::fromAmount(1000, 'IRT'), 3, 10.0);
    }

    public function test_form_minParticipantsBelowTwo_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Coalition::form(1, 2, CatalogItemType::Product, 5, Money::fromAmount(1000, 'IRT'), 1, 10.0);
    }

    public function test_discountedUnitPrice_appliesPercentToAmount(): void
    {
        $coalition = $this->coalition(10.0);

        $this->assertSame(9000, $coalition->discountedUnitPrice()->amount());
        $this->assertSame('IRT', $coalition->discountedUnitPrice()->currency());
    }

    public function test_startNegotiating_setsNegotiationIdAndTransitions(): void
    {
        $coalition = $this->coalition();

        $coalition->startNegotiating(42);

        $this->assertSame(CoalitionStatus::Negotiating, $coalition->status());
        $this->assertSame(42, $coalition->negotiationId());
    }

    public function test_complete_fromForming_throws(): void
    {
        $coalition = $this->coalition();

        $this->expectException(InvalidCoalitionStateException::class);

        $coalition->complete();
    }

    public function test_cancel_fromNegotiating_isAllowed(): void
    {
        $coalition = $this->coalition();
        $coalition->startNegotiating(42);

        $coalition->cancel();

        $this->assertSame(CoalitionStatus::Cancelled, $coalition->status());
    }

    public function test_cancel_fromCompleted_throws(): void
    {
        $coalition = $this->coalition();
        $coalition->startNegotiating(42);
        $coalition->complete();

        $this->expectException(InvalidCoalitionStateException::class);

        $coalition->cancel();
    }
}
