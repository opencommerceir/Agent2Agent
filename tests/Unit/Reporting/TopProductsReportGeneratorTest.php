<?php

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Domain\Services\TopProductsReportGenerator;
use PHPUnit\Framework\TestCase;

class TopProductsReportGeneratorTest extends TestCase
{
    public function test_generate_mergesResolvedNamesIntoRows(): void
    {
        $generator = new TopProductsReportGenerator();

        $rows = [
            ['product_id' => 1, 'quantity_sold' => 10, 'total_revenue' => 5000],
            ['product_id' => 2, 'quantity_sold' => 5, 'total_revenue' => 2500],
        ];

        $result = $generator->generate($rows, [1 => 'Widget', 2 => 'Gadget']);

        $this->assertSame([
            ['product_id' => 1, 'name' => 'Widget', 'quantity_sold' => 10, 'total_revenue' => 5000],
            ['product_id' => 2, 'name' => 'Gadget', 'quantity_sold' => 5, 'total_revenue' => 2500],
        ], $result);
    }

    public function test_generate_withMissingName_fallsBackToPlaceholder(): void
    {
        $generator = new TopProductsReportGenerator();

        $result = $generator->generate([['product_id' => 99, 'quantity_sold' => 1, 'total_revenue' => 100]], []);

        $this->assertSame('Product #99', $result[0]['name']);
    }
}
