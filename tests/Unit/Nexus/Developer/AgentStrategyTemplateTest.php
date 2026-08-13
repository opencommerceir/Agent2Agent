<?php

namespace Tests\Unit\Nexus\Developer;

use App\Domains\Nexus\Developer\Domain\Entities\AgentStrategyTemplate;
use PHPUnit\Framework\TestCase;

class AgentStrategyTemplateTest extends TestCase
{
    public function test_publish_startsUnrevokedWithZeroInstalls(): void
    {
        $template = $this->publish();

        $this->assertFalse($template->isRevoked());
        $this->assertSame(0, $template->installCount());
    }

    public function test_recordInstall_incrementsCount(): void
    {
        $template = $this->publish();

        $template->recordInstall();
        $template->recordInstall();

        $this->assertSame(2, $template->installCount());
    }

    public function test_revoke_marksRevoked(): void
    {
        $template = $this->publish();

        $template->revoke();

        $this->assertTrue($template->isRevoked());
    }

    private function publish(): AgentStrategyTemplate
    {
        return AgentStrategyTemplate::publish(
            publisherBusinessId: 1,
            nameFa: 'قالب',
            nameEn: 'Template',
            descriptionFa: 'توضیح',
            descriptionEn: 'Description',
            personality: 'friendly',
            tone: 'formal',
            strategies: ['opening_discount_percent' => 5],
            priceCredits: 100,
        );
    }
}
