<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Application\Services\CsvParser;
use App\Modules\Commerce\Domain\Exceptions\InvalidCsvFormatException;
use PHPUnit\Framework\TestCase;

class CsvParserTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_parse_withValidCsv_yieldsRowsKeyedByHeaderStartingAtRowOne(): void
    {
        $path = $this->writeTempCsv([
            ['sku', 'name', 'price'],
            ['SKU-1', 'Widget', '19.99'],
            ['SKU-2', 'Gadget', '29.99'],
        ]);

        $rows = iterator_to_array((new CsvParser())->parse($path));

        $this->assertSame([1, 2], array_keys($rows));
        $this->assertSame(['sku' => 'SKU-1', 'name' => 'Widget', 'price' => '19.99'], $rows[1]);
        $this->assertSame(['sku' => 'SKU-2', 'name' => 'Gadget', 'price' => '29.99'], $rows[2]);
    }

    public function test_parse_neverLoadsWholeFileAtOnce_returnsAGenerator(): void
    {
        $path = $this->writeTempCsv([
            ['sku', 'name'],
            ['SKU-1', 'Widget'],
        ]);

        $result = (new CsvParser())->parse($path);

        $this->assertInstanceOf(\Generator::class, $result);
    }

    public function test_parse_withMissingFile_throwsInvalidCsvFormatException(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        iterator_to_array((new CsvParser())->parse('/path/does/not/exist/'.uniqid().'.csv'));
    }

    public function test_parse_withEmptyFile_throwsInvalidCsvFormatExceptionForMissingHeader(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_');
        $this->tempFiles[] = $path;
        file_put_contents($path, '');

        $this->expectException(InvalidCsvFormatException::class);

        iterator_to_array((new CsvParser())->parse($path));
    }

    public function test_parse_withRaggedRow_padsShortRowsToHeaderWidth(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_');
        $this->tempFiles[] = $path;
        file_put_contents($path, "sku,name,price\nSKU-1,Widget\n");

        $rows = iterator_to_array((new CsvParser())->parse($path));

        $this->assertSame(['sku' => 'SKU-1', 'name' => 'Widget', 'price' => ''], $rows[1]);
    }

    /**
     * @param list<list<string>> $rows including the header as the first row
     */
    private function writeTempCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_');
        $this->tempFiles[] = $path;

        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
