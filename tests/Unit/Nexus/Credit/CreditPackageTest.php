<?php

namespace Tests\Unit\Nexus\Credit;

use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use PHPUnit\Framework\TestCase;

class CreditPackageTest extends TestCase
{
    public function test_starter_priceAndCredits(): void
    {
        $this->assertSame(500_000, CreditPackage::Starter->priceAmountToman());
        $this->assertSame(1_000, CreditPackage::Starter->creditsGranted());
    }

    public function test_professional_priceAndCredits(): void
    {
        $this->assertSame(2_000_000, CreditPackage::Professional->priceAmountToman());
        $this->assertSame(5_000, CreditPackage::Professional->creditsGranted());
    }

    public function test_enterprise_priceAndCredits(): void
    {
        $this->assertSame(10_000_000, CreditPackage::Enterprise->priceAmountToman());
        $this->assertSame(30_000, CreditPackage::Enterprise->creditsGranted());
    }

    public function test_from_roundTripsTheValue(): void
    {
        $this->assertSame(CreditPackage::Professional, CreditPackage::from('professional'));
    }
}
