<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

enum PaymentMethod: string
{
    case CreditCard = 'credit_card';
    case PayPal = 'paypal';
    case BankTransfer = 'bank_transfer';
    case CashOnDelivery = 'cash_on_delivery';
}
