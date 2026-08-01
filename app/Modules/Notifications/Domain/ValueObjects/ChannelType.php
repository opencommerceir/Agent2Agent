<?php

namespace App\Modules\Notifications\Domain\ValueObjects;

enum ChannelType: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Webhook = 'webhook';
    case InApp = 'in_app';
}
