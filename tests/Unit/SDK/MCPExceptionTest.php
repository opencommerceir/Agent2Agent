<?php

namespace Tests\Unit\SDK;

use App\SDK\Exceptions\AuthenticationException;
use App\SDK\Exceptions\AuthorizationException;
use App\SDK\Exceptions\MCPException;
use App\SDK\Exceptions\NotFoundException;
use App\SDK\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP — no HTTP, no Laravel bootstrap. fromArray()/fromResponse()
 * work on plain arrays, so the status-code -> exception-class mapping is
 * testable in complete isolation from the network.
 */
class MCPExceptionTest extends TestCase
{
    public function test_fromResponse_with401_returnsAuthenticationException(): void
    {
        $e = MCPException::fromResponse(401, ['error' => ['code' => 'UNAUTHORIZED', 'message' => 'bad token']]);

        $this->assertInstanceOf(AuthenticationException::class, $e);
        $this->assertSame('UNAUTHORIZED', $e->errorCode);
        $this->assertSame('bad token', $e->getMessage());
        $this->assertSame(401, $e->statusCode);
    }

    public function test_fromResponse_with403_returnsAuthorizationException(): void
    {
        $e = MCPException::fromResponse(403, ['error' => ['code' => 'FORBIDDEN', 'message' => 'no permission']]);

        $this->assertInstanceOf(AuthorizationException::class, $e);
    }

    public function test_fromResponse_with404_returnsNotFoundException(): void
    {
        $e = MCPException::fromResponse(404, ['error' => ['code' => 'NOT_FOUND', 'message' => 'missing']]);

        $this->assertInstanceOf(NotFoundException::class, $e);
    }

    public function test_fromResponse_with422_returnsValidationException(): void
    {
        $e = MCPException::fromResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'bad input']]);

        $this->assertInstanceOf(ValidationException::class, $e);
    }

    public function test_fromResponse_with500_returnsBaseMCPException(): void
    {
        $e = MCPException::fromResponse(500, ['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'oops']]);

        $this->assertSame(MCPException::class, get_class($e));
    }

    public function test_fromResponse_withMalformedBody_fallsBackToGenericMessage(): void
    {
        $e = MCPException::fromResponse(500, []);

        $this->assertSame('UNKNOWN_ERROR', $e->errorCode);
        $this->assertStringContainsString('500', $e->getMessage());
    }
}
