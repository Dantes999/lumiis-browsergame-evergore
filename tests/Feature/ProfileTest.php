<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $originalName = $user->name;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'email' => 'test@example.com',
                'locale_preference' => 'de',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame($originalName, $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'email' => $user->email,
                'locale_preference' => 'de',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_locale_preference_can_be_saved_as_english(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile/locale', [
                'locale_preference' => 'en',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('en', $user->refresh()->locale);
        $this->assertSame('en', session('app_locale'));
    }

    public function test_authenticated_user_without_saved_locale_uses_browser_language_when_supported(): void
    {
        $user = User::factory()->create([
            'locale' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('Choose your interface language.');

        $this->assertNull(session('app_locale'));
    }

    public function test_guest_pages_fall_back_to_browser_language_when_no_preference_exists(): void
    {
        $response = $this
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/');

        $response
            ->assertOk()
            ->assertSee('Welcome Back')
            ->assertSee('Log in')
            ->assertDontSee('Willkommen zurück');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_unsupported_browser_language_falls_back_to_german(): void
    {
        $response = $this
            ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get('/');

        $response
            ->assertOk()
            ->assertSee('Willkommen zurück')
            ->assertSee('Einloggen')
            ->assertDontSee('Welcome Back');

        $this->assertSame('de', app()->getLocale());
    }

    public function test_removed_auto_locale_option_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile/locale', [
                'locale_preference' => 'auto',
            ]);

        $response
            ->assertSessionHasErrors('locale_preference')
            ->assertRedirect('/profile');
    }
}
