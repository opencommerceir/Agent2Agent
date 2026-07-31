<?php

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use PHPUnit\Framework\TestCase;

class ReportFilterTest extends TestCase
{
    public function test_empty_hasNoValues(): void
    {
        $filter = ReportFilter::empty();

        $this->assertSame([], $filter->toArray());
        $this->assertNull($filter->get('limit'));
    }

    public function test_get_withDefault_returnsDefaultWhenMissing(): void
    {
        $filter = ReportFilter::empty();

        $this->assertSame(10, $filter->get('limit', 10));
    }

    public function test_fromArray_getReturnsStoredValue(): void
    {
        $filter = ReportFilter::fromArray(['limit' => 25]);

        $this->assertSame(25, $filter->get('limit'));
        $this->assertSame(['limit' => 25], $filter->toArray());
    }
}
