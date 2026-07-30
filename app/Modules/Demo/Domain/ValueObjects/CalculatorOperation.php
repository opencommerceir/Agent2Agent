<?php

namespace App\Modules\Demo\Domain\ValueObjects;

enum CalculatorOperation: string
{
    case Add = 'add';
    case Subtract = 'subtract';
    case Multiply = 'multiply';
    case Divide = 'divide';
}
