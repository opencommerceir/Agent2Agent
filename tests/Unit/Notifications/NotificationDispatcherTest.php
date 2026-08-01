<?php

namespace Tests\Unit\Notifications;

use App\Modules\Notifications\Domain\Entities\NotificationPreference;
use App\Modules\Notifications\Domain\Services\NotificationDispatcher;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;
use PHPUnit\Framework\TestCase;

class NotificationDispatcherTest extends TestCase
{
    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new NotificationDispatcher();
    }

    public function test_shouldSend_withNoPreferenceAndActiveChannel_returnsTrue(): void
    {
        $this->assertTrue($this->dispatcher->shouldSend(null, channelActive: true));
    }

    public function test_shouldSend_withInactiveChannel_returnsFalseRegardlessOfPreference(): void
    {
        $enabledPreference = $this->preference(isEnabled: true);

        $this->assertFalse($this->dispatcher->shouldSend(null, channelActive: false));
        $this->assertFalse($this->dispatcher->shouldSend($enabledPreference, channelActive: false));
    }

    public function test_shouldSend_withDisabledPreference_returnsFalse(): void
    {
        $disabledPreference = $this->preference(isEnabled: false);

        $this->assertFalse($this->dispatcher->shouldSend($disabledPreference, channelActive: true));
    }

    public function test_shouldSend_withEnabledPreference_returnsTrue(): void
    {
        $enabledPreference = $this->preference(isEnabled: true);

        $this->assertTrue($this->dispatcher->shouldSend($enabledPreference, channelActive: true));
    }

    private function preference(bool $isEnabled): NotificationPreference
    {
        return NotificationPreference::create(
            1, RecipientType::Customer, 1, NotificationType::OrderPlaced, ChannelType::Email, $isEnabled,
        );
    }
}
