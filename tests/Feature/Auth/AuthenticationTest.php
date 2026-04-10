<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_successful_login_regenerates_the_session_id(): void
    {
        $user = User::factory()->create();
        $this->startSession();

        $beforeLoginSessionId = $this->app['session.store']->getId();

        $this->withSession(['pre_auth_probe' => 'present'])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotSame($beforeLoginSessionId, $this->app['session.store']->getId());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_requires_both_email_and_password(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertRedirect('/login');
        }

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_route_is_rate_limited_by_ip_even_when_emails_vary(): void
    {
        RateLimiter::for('login-requests', function (Request $request): array {
            return [
                Limit::perMinute(2)->by('test-login-requests:ip:'.$request->ip()),
            ];
        });

        $this->from('/login')->post('/login', [
            'email' => 'first@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->from('/login')->post('/login', [
            'email' => 'second@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->from('/login')->post('/login', [
            'email' => 'third@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_logout_invalidates_the_session_and_rotates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->startSession();

        $beforeLogoutSessionId = $this->app['session.store']->getId();
        $beforeLogoutToken = $this->app['session.store']->token();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($beforeLogoutSessionId, $this->app['session.store']->getId());
        $this->assertNotSame($beforeLogoutToken, $this->app['session.store']->token());
    }
}
