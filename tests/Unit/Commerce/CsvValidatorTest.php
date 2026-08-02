<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Application\Services\CsvValidator;
use PHPUnit\Framework\TestCase;

class CsvValidatorTest extends TestCase
{
    public function test_validateRow_withAllRequiredColumnsPresent_isValid(): void
    {
        $result = (new CsvValidator())->validateRow(
            ['sku' => 'SKU-1', 'name' => 'Widget', 'price' => '19.99'],
            ['sku', 'name', 'price'],
        );

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function test_validateRow_withOneMissingColumn_isInvalidAndNamesIt(): void
    {
        $result = (new CsvValidator())->validateRow(
            ['sku' => 'SKU-1', 'price' => '19.99'],
            ['sku', 'name', 'price'],
        );

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('name', $result->errors[0]);
    }

    public function test_validateRow_withOneBlankColumn_isInvalid(): void
    {
        $result = (new CsvValidator())->validateRow(
            ['sku' => 'SKU-1', 'name' => '   ', 'price' => '19.99'],
            ['sku', 'name', 'price'],
        );

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('name', $result->errors[0]);
    }

    public function test_validateRow_withMultipleMissingColumns_namesEveryOneOfThem(): void
    {
        $result = (new CsvValidator())->validateRow(
            ['price' => '19.99'],
            ['sku', 'name', 'price', 'currency'],
        );

        $this->assertFalse($result->isValid);
        $this->assertCount(3, $result->errors);

        $joined = implode(' ', $result->errors);
        $this->assertStringContainsString('sku', $joined);
        $this->assertStringContainsString('name', $joined);
        $this->assertStringContainsString('currency', $joined);
    }

    public function test_validateRow_withNoRequiredColumns_isAlwaysValid(): void
    {
        $result = (new CsvValidator())->validateRow(['anything' => 'here'], []);

        $this->assertTrue($result->isValid);
    }
}
