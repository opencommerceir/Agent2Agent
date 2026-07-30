<?php

namespace Tests\Unit\MCP;

use App\Core\Application\Actions\AuthenticateAgentAction;
use App\Core\Domain\Entities\Agent;
use App\Core\Domain\Entities\AgentToken;
use App\Core\Domain\Exceptions\AgentNotActiveException;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\AgentTokenRepositoryInterface;
use App\Core\Domain\ValueObjects\AgentStatus;
use App\Core\Domain\ValueObjects\AgentType;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * AuthenticateAgentAction depends only on two Repository interfaces and
 * takes no Facades — it can be tested in complete isolation from Laravel
 * and the database with Mockery fakes standing in for persistence.
 */
class AuthenticationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_execute_withValidToken_returnsMatchingAgentData(): void
    {
        $plainToken = 'oc_agent_valid_token';
        $hash = AgentToken::hash($plainToken);
        $token = AgentToken::issue(agentId: 5, tokenHash: $hash);
        $agent = new Agent(5, 1, 1, 'Shopping Assistant', AgentType::Shopping, AgentStatus::Active, new DateTimeImmutable());

        $tokens = Mockery::mock(AgentTokenRepositoryInterface::class);
        $tokens->shouldReceive('findByHash')->once()->with($hash)->andReturn($token);
        $tokens->shouldReceive('save')->once()->andReturnArg(0);

        $agents = Mockery::mock(AgentRepositoryInterface::class);
        $agents->shouldReceive('findById')->once()->with(5)->andReturn($agent);

        $result = (new AuthenticateAgentAction($tokens, $agents))->execute($plainToken);

        $this->assertSame(5, $result->id);
        $this->assertSame('Shopping Assistant', $result->name);
    }

    public function test_execute_withUnknownToken_throwsInvalidAgentTokenException(): void
    {
        $tokens = Mockery::mock(AgentTokenRepositoryInterface::class);
        $tokens->shouldReceive('findByHash')->once()->andReturn(null);

        $agents = Mockery::mock(AgentRepositoryInterface::class);

        $this->expectException(InvalidAgentTokenException::class);

        (new AuthenticateAgentAction($tokens, $agents))->execute('oc_agent_wrong');
    }

    public function test_execute_withRevokedToken_throwsInvalidAgentTokenException(): void
    {
        $plainToken = 'oc_agent_revoked_token';
        $hash = AgentToken::hash($plainToken);
        $token = AgentToken::issue(agentId: 5, tokenHash: $hash);
        $token->revoke();

        $tokens = Mockery::mock(AgentTokenRepositoryInterface::class);
        $tokens->shouldReceive('findByHash')->once()->with($hash)->andReturn($token);

        $agents = Mockery::mock(AgentRepositoryInterface::class);

        $this->expectException(InvalidAgentTokenException::class);

        (new AuthenticateAgentAction($tokens, $agents))->execute($plainToken);
    }

    public function test_execute_withExpiredToken_throwsInvalidAgentTokenException(): void
    {
        $plainToken = 'oc_agent_expired_token';
        $hash = AgentToken::hash($plainToken);
        $token = AgentToken::issue(agentId: 5, tokenHash: $hash, expiresAt: new DateTimeImmutable('-1 hour'));

        $tokens = Mockery::mock(AgentTokenRepositoryInterface::class);
        $tokens->shouldReceive('findByHash')->once()->with($hash)->andReturn($token);

        $agents = Mockery::mock(AgentRepositoryInterface::class);

        $this->expectException(InvalidAgentTokenException::class);

        (new AuthenticateAgentAction($tokens, $agents))->execute($plainToken);
    }

    public function test_execute_withInactiveAgent_throwsAgentNotActiveException(): void
    {
        $plainToken = 'oc_agent_inactive_agent_token';
        $hash = AgentToken::hash($plainToken);
        $token = AgentToken::issue(agentId: 7, tokenHash: $hash);
        $agent = new Agent(7, 1, 1, 'Suspended Agent', AgentType::Shopping, AgentStatus::Suspended, new DateTimeImmutable());

        $tokens = Mockery::mock(AgentTokenRepositoryInterface::class);
        $tokens->shouldReceive('findByHash')->once()->with($hash)->andReturn($token);

        $agents = Mockery::mock(AgentRepositoryInterface::class);
        $agents->shouldReceive('findById')->once()->with(7)->andReturn($agent);

        $this->expectException(AgentNotActiveException::class);

        (new AuthenticateAgentAction($tokens, $agents))->execute($plainToken);
    }
}
