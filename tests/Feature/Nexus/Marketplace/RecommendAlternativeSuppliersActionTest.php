<?php

namespace Tests\Feature\Nexus\Marketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Marketplace\Application\Actions\RecommendAlternativeSuppliersAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RecommendAlternativeSuppliersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_unknownTarget_throws(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');

        $this->expectException(InvalidArgumentException::class);

        app(RecommendAlternativeSuppliersAction::class)->execute($caller->id, 999999);
    }

    public function test_execute_returnsSameIndustryAlternatives_excludingTargetAndCaller(): void
    {
        $caller = $this->verifiedBusiness('Caller Co', 'technology');
        $target = $this->verifiedBusiness('Target Co', 'technology');
        $alternative = $this->verifiedBusiness('Alternative Co', 'technology');
        $otherIndustry = $this->verifiedBusiness('Other Industry Co', 'retail');

        $result = app(RecommendAlternativeSuppliersAction::class)->execute($caller->id, $target->id);

        $businessIds = array_column($result['listings'], 'businessId');
        $this->assertContains($alternative->id, $businessIds);
        $this->assertNotContains($target->id, $businessIds);
        $this->assertNotContains($caller->id, $businessIds);
        $this->assertNotContains($otherIndustry->id, $businessIds);
    }

    private function verifiedBusiness(string $nameEn, string $industry = 'technology'): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::from($industry));
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
