<?php

namespace Tests\Feature\MCP;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 Stage 4 (i18n) — MCPExceptionHandler's own new
 * `error.localized_message` field. `error.message` itself is asserted
 * unchanged elsewhere (MCPExceptionHandlerTest) — this file only covers
 * the new, additive field.
 */
class MCPLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalidToken_withLangQueryParameter_returnsLocalizedMessageInThatLanguage(): void
    {
        $response = $this->postJson('/mcp/v1/execute?lang=fa', [
            'capability' => 'commerce.product.search',
        ], ['Authorization' => 'Bearer oc_agent_does_not_exist']);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
        $response->assertJsonPath('error.localized_message', 'توکن نامعتبر یا منقضی شده است');
    }

    public function test_invalidToken_withAcceptLanguageHeader_returnsLocalizedMessageInThatLanguage(): void
    {
        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
        ], [
            'Authorization' => 'Bearer oc_agent_does_not_exist',
            'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.7',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.localized_message', 'توکن نامعتبر یا منقضی شده است');
    }

    public function test_invalidToken_withNoLanguageSignal_defaultsToEnglish(): void
    {
        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
        ], ['Authorization' => 'Bearer oc_agent_does_not_exist']);

        $response->assertStatus(401);
        $response->assertJsonPath('error.localized_message', 'Invalid or expired token');
        // The original, specific exception message is untouched by any of
        // this — still the exact text AuthenticateAgentAction throws, not
        // the new generic localized_message.
        $response->assertJsonPath('error.message', 'The provided agent token is invalid, revoked, or expired.');
    }
}
