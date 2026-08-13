<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusSsoProvidersControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_reflectsGoogleLiveAndSamlLdapStubbed(): void
    {
        config(['services.google.client_id' => 'fake-id', 'services.google.client_secret' => 'fake-secret']);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.sso-providers.index'));

        $response->assertOk();
        $response->assertSee('Google');
        $response->assertSee('Saml');
        $response->assertSee('Ldap');
    }

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('dashboard.nexus.sso-providers.index'));

        $response->assertRedirect(route('login'));
    }
}
