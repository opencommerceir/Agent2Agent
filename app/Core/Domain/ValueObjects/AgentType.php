<?php

namespace App\Core\Domain\ValueObjects;

enum AgentType: string
{
    case Shopping = 'shopping';
    case Analytics = 'analytics';
    case CustomerService = 'customer_service';
    case Custom = 'custom';
}
