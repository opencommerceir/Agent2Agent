<?php

namespace Tests\Feature\Showcase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `/showcase/*` passcode gate (Phase 3, §7.33) — a session flag,
 * completely independent of Core's own `User`/`auth`/`admin` system
 * (HANDOFF §3 pattern #19's usual boundary, applied to a second,
 * unrelated Interfaces-layer concern this time). Every Phase 1/2 Showcase
 * test already proves the gate-disabled default (no `SHOWCASE_PASSCODE`
 * anywhere in the test environment) doesn't block anything — these tests
 * cover the gate actually being turned on.
 */
class ShowcaseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_withNoPasscodeConfigured_showcaseIsDirectlyReachable(): void
    {
        config(['showcase.passcode' => null]);

        $response = $this->get('/showcase');

        $response->assertStatus(200);
    }

    public function test_withAPasscodeConfigured_showcaseRedirectsToTheEnterForm(): void
    {
        config(['showcase.passcode' => 'let-me-in']);

        $response = $this->get('/showcase');

        $response->assertRedirect(route('showcase.enter'));
    }

    public function test_theEnterFormItselfIsAlwaysReachable(): void
    {
        config(['showcase.passcode' => 'let-me-in']);

        $response = $this->get('/showcase/enter');

        $response->assertStatus(200);
    }

    public function test_submittingTheWrongPasscodeRedirectsBackWithAnError(): void
    {
        config(['showcase.passcode' => 'let-me-in']);

        $response = $this->post('/showcase/enter', ['passcode' => 'wrong']);

        $response->assertRedirect(route('showcase.enter'));
        $response->assertSessionHasErrors('passcode');
        $this->assertNotTrue(session('showcase_access_granted'));
    }

    public function test_submittingTheRightPasscodeGrantsAccess(): void
    {
        config(['showcase.passcode' => 'let-me-in']);

        $enter = $this->post('/showcase/enter', ['passcode' => 'let-me-in']);
        $enter->assertRedirect(route('showcase.index'));

        $response = $this->get('/showcase');
        $response->assertStatus(200);
    }

    public function test_panelAndHistoryRoutesAreAlsoGated(): void
    {
        config(['showcase.passcode' => 'let-me-in']);

        $this->get('/showcase/panel/kpis')->assertRedirect(route('showcase.enter'));
        $this->get('/showcase/history')->assertRedirect(route('showcase.enter'));
    }
}
