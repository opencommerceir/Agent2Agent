<?php

namespace Tests\Feature\Nexus\Marketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Marketplace\Application\Actions\RankSuppliersAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankSuppliersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_ranksBusinessesWithMoreCatalogItemsFirst(): void
    {
        $small = app(RegisterBusinessAction::class)->execute('کوچک', 'Small Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($small->id);
        app(AddProductAction::class)->execute($small->id, 'محصول', 'Product', 1000, 'IRT');

        $big = app(RegisterBusinessAction::class)->execute('بزرگ', 'Big Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($big->id);
        app(AddProductAction::class)->execute($big->id, 'محصول یک', 'Product One', 1000, 'IRT');
        app(AddProductAction::class)->execute($big->id, 'محصول دو', 'Product Two', 1000, 'IRT');
        app(AddProductAction::class)->execute($big->id, 'محصول سه', 'Product Three', 1000, 'IRT');

        $result = app(RankSuppliersAction::class)->execute([$small->id, $big->id]);

        $businessIds = array_column($result['listings'], 'businessId');
        $this->assertSame([$big->id, $small->id], $businessIds);
    }
}
