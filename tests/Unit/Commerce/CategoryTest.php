<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Category;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    public function test_create_withValidData_setsAllFields(): void
    {
        $category = Category::create(1, 'Electronics', 'electronics', 'Gadgets and gear.');

        $this->assertNull($category->id());
        $this->assertSame(1, $category->tenantId());
        $this->assertSame('Electronics', $category->name());
        $this->assertSame('electronics', $category->slug());
        $this->assertSame('Gadgets and gear.', $category->description());
    }

    public function test_create_withoutDescription_defaultsToNull(): void
    {
        $category = Category::create(1, 'Electronics', 'electronics');

        $this->assertNull($category->description());
    }
}
