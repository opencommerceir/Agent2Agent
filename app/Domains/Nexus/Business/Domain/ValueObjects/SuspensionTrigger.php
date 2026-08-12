<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

enum SuspensionTrigger: string
{
    case Admin = 'admin';
    case AutoFraudDetection = 'auto_fraud_detection';
}
