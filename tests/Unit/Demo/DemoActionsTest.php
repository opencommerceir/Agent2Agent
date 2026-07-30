<?php

namespace Tests\Unit\Demo;

use App\Modules\Demo\Application\Actions\CalculateAction;
use App\Modules\Demo\Application\Actions\EchoAction;
use App\Modules\Demo\Application\Actions\GetCurrentTimeAction;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP — these Actions have no dependencies at all (no repositories,
 * no framework), so there is nothing to mock.
 */
class DemoActionsTest extends TestCase
{
    public function test_echoAction_withValidMessage_returnsEchoAndTimestamp(): void
    {
        $result = (new EchoAction())->execute(['message' => 'hello']);

        $this->assertSame('hello', $result['echo']);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_echoAction_withoutMessage_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EchoAction())->execute([]);
    }

    public function test_getCurrentTimeAction_returnsUtcAndUnixTimestamp(): void
    {
        $result = (new GetCurrentTimeAction())->execute([]);

        $this->assertArrayHasKey('utc', $result);
        $this->assertIsInt($result['unix']);
    }

    public function test_calculateAction_withAdd_returnsSum(): void
    {
        $result = (new CalculateAction())->execute(['operation' => 'add', 'a' => 2, 'b' => 3]);

        $this->assertSame(5.0, $result['result']);
    }

    public function test_calculateAction_withMultiply_returnsProduct(): void
    {
        $result = (new CalculateAction())->execute(['operation' => 'multiply', 'a' => 6, 'b' => 7]);

        $this->assertSame(42.0, $result['result']);
    }

    public function test_calculateAction_withDivide_returnsQuotient(): void
    {
        $result = (new CalculateAction())->execute(['operation' => 'divide', 'a' => 10, 'b' => 4]);

        $this->assertSame(2.5, $result['result']);
    }

    public function test_calculateAction_withDivisionByZero_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalculateAction())->execute(['operation' => 'divide', 'a' => 10, 'b' => 0]);
    }

    public function test_calculateAction_withInvalidOperation_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalculateAction())->execute(['operation' => 'modulo', 'a' => 10, 'b' => 3]);
    }

    public function test_calculateAction_withNonNumericOperand_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalculateAction())->execute(['operation' => 'add', 'a' => 'not-a-number', 'b' => 3]);
    }
}
