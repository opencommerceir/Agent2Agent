<?php

namespace Tests\Unit\CRM;

use App\Modules\CRM\Domain\Entities\Tag;
use App\Modules\CRM\Domain\ValueObjects\TagName;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    public function test_create_setsAllFieldsAndNoId(): void
    {
        $tag = Tag::create(tenantId: 1, name: new TagName('VIP'), color: '#ff0000');

        $this->assertNull($tag->id());
        $this->assertSame(1, $tag->tenantId());
        $this->assertSame('VIP', $tag->name()->value());
        $this->assertSame('#ff0000', $tag->color());
    }

    public function test_create_withoutColor_defaultsToNull(): void
    {
        $tag = Tag::create(1, new TagName('VIP'));

        $this->assertNull($tag->color());
    }
}
