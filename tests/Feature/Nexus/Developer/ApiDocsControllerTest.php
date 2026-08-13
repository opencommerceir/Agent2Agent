<?php

namespace Tests\Feature\Nexus\Developer;

use Tests\TestCase;

class ApiDocsControllerTest extends TestCase
{
    public function test_index_isPubliclyAccessibleWithoutLogin(): void
    {
        $response = $this->get(route('nexus.developer.docs.index'));

        $response->assertOk();
        $response->assertViewHas('endpoints');
        $response->assertViewHas('scopes');
        $response->assertViewHas('webhookEvents');
        $response->assertSee('/nexus/api/v1/business', false);
        $response->assertSee('negotiation.accepted', false);
    }
}
