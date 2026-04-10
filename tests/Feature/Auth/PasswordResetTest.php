<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_request_does_not_leak_whether_an_email_exists(): void
    {
        Notification::fake();

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'ghost@example.test',
        ]);

        $response
            ->assertRedirect('/forgot-password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

        Notification::assertNothingSent();
    }

    public function test_reset_password_requests_are_rate_limited(): void
    {
        RateLimiter::for('password-reset-requests', function (Request $request): array {
            return [
                Limit::perMinute(2)->by('test-password-reset-requests:ip:'.$request->ip()),
            ];
        });

        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'first@example.test',
        ])->assertRedirect('/forgot-password');

        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'second@example.test',
        ])->assertRedirect('/forgot-password');

        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'third@example.test',
        ])->assertTooManyRequests();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_reset_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/forgot-password')->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
