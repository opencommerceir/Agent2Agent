<?php

namespace Tests\Unit\Nexus\Catalog;

use App\Domains\Nexus\Catalog\Domain\Entities\Service;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    public function test_add_withValidData_createsService(): void
    {
        $service = Service::add(1, 'خدمت آزمایشی', 'Test Service', Money::fromAmount(200000, 'IRT'), 60);

        $this->assertNull($service->id());
        $this->assertSame(1, $service->businessId());
        $this->assertSame('خدمت آزمایشی', $service->nameFa());
        $this->assertSame('Test Service', $service->nameEn());
        $this->assertSame(200000, $service->hourlyPrice()->amount());
        $this->assertSame(60, $service->durationMinutes());
    }

    public function test_update_changesAllMutableFields(): void
    {
        $service = Service::add(1, 'خدمت آزمایشی', 'Test Service', Money::fromAmount(200000, 'IRT'), 60);

        $service->update('خدمت جدید', 'New Service', Money::fromAmount(300000, 'IRT'), 90, ['location' => 'onsite']);

        $this->assertSame('خدمت جدید', $service->nameFa());
        $this->assertSame('New Service', $service->nameEn());
        $this->assertSame(300000, $service->hourlyPrice()->amount());
        $this->assertSame(90, $service->durationMinutes());
        $this->assertSame(['location' => 'onsite'], $service->attributes());
    }
}
