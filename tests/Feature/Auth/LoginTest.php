<?php

namespace Tests\Feature\Auth;

use App\Core\Application\Actions\CreateUserAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The end-to-end human-auth scenario from Phase 4 Stage 5's own request:
 * create a User, log in with correct/incorrect credentials, guard the
 * Dashboard behind both `auth` and `admin`, and log out.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_withValidCredentials_redirectsToDashboard(): void
    {
        $this->withoutVite();
        $this->createUser('admin@example.com', 'password123', 'admin');

        $response = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password123']);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticated();
    }

    public function test_login_withWrongPassword_returnsTranslatedError(): void
    {
        $this->withoutVite();
        $this->createUser('admin@example.com', 'password123', 'admin');

        $response = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_login_withWrongPassword_inFarsi_returnsFarsiError(): void
    {
        $this->withoutVite();
        $this->createUser('admin@example.com', 'password123', 'admin');
        $this->get('/language/fa'); // sets the dashboard_language session key

        $response = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors(['email' => 'این اطلاعات با سوابق ما مطابقت ندارد.']);
    }

    public function test_login_forInactiveUser_isRejected(): void
    {
        $this->withoutVite();
        $this->createUser('admin@example.com', 'password123', 'admin', active: false);

        $response = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password123']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dashboard_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_forNonAdminUser_isForbidden(): void
    {
        $this->withoutVite();
        $user = $this->createUser('operator@example.com', 'password123', 'operator');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_forAdmin_isAllowed(): void
    {
        $this->withoutVite();
        $user = $this->createUser('admin@example.com', 'password123', 'admin');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_alreadyAuthenticated_visitingLogin_redirectsToDashboard(): void
    {
        $user = $this->createUser('admin@example.com', 'password123', 'admin');

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect(route('dashboard.index'));
    }

    public function test_logout_redirectsToLoginAndEndsSession(): void
    {
        $user = $this->createUser('admin@example.com', 'password123', 'admin');

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function createUser(string $email, string $password, string $role, bool $active = true): \App\Core\Infrastructure\Models\User
    {
        $data = app(CreateUserAction::class)->execute('Test User', $email, $password, $role);

        if (! $active) {
            \App\Core\Infrastructure\Models\User::query()->where('id', $data->id)->update(['is_active' => false]);
        }

        return \App\Core\Infrastructure\Models\User::query()->find($data->id);
    }
}
