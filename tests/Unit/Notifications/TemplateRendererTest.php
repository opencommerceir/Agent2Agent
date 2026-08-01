<?php

namespace Tests\Unit\Notifications;

use App\Modules\Notifications\Domain\Services\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TemplateRenderer();
    }

    public function test_render_substitutesEveryMatchingVariable(): void
    {
        $result = $this->renderer->render(
            'Your order {{order_number}} is now {{status}}',
            ['order_number' => 'ORD-1', 'status' => 'shipped'],
        );

        $this->assertSame('Your order ORD-1 is now shipped', $result);
    }

    public function test_render_leavesUnmatchedPlaceholdersAsLiteralText(): void
    {
        $result = $this->renderer->render('Hello {{customer_name}}, {{unknown_var}}', ['customer_name' => 'Ada']);

        $this->assertSame('Hello Ada, {{unknown_var}}', $result);
    }

    public function test_render_toleratesExtraWhitespaceInsidePlaceholders(): void
    {
        $result = $this->renderer->render('{{  points  }} points earned', ['points' => 100]);

        $this->assertSame('100 points earned', $result);
    }

    public function test_render_withNoPlaceholders_returnsTemplateUnchanged(): void
    {
        $this->assertSame('Plain text', $this->renderer->render('Plain text', ['x' => 'y']));
    }
}
