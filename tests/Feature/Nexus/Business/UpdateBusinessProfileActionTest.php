<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\UpdateBusinessProfileAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UpdateBusinessProfileActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_updatesBusinessProfile(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $result = app(UpdateBusinessProfileAction::class)->execute(
            $business->id,
            'شرکت جدید',
            'New Company',
            BusinessType::Individual,
            Industry::Retail,
        );

        $this->assertSame('شرکت جدید', $result->nameFa);
        $this->assertSame('New Company', $result->nameEn);
        $this->assertSame('individual', $result->type);
        $this->assertSame('retail', $result->industry);
        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name_en' => 'New Company',
            'type' => 'individual',
            'industry' => 'retail',
        ]);
    }

    public function test_execute_withNonExistentBusiness_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(UpdateBusinessProfileAction::class)->execute(9999, 'شرکت جدید', 'New Company', BusinessType::Company, Industry::Retail);
    }
}
