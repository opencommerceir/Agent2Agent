<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\ValidationResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function test_valid_hasNoErrors(): void
    {
        $result = ValidationResult::valid();

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function test_invalid_carriesErrors(): void
    {
        $result = ValidationResult::invalid(['sku is required']);

        $this->assertFalse($result->isValid);
        $this->assertSame(['sku is required'], $result->errors);
    }

    public function test_valid_canStillCarryWarnings(): void
    {
        $result = ValidationResult::valid(['category not found, will be left unset']);

        $this->assertTrue($result->isValid);
        $this->assertSame(['category not found, will be left unset'], $result->warnings);
    }
}
