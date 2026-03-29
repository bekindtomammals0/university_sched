<?php

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ProfileTest extends TestCase
{
    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create(['email_verified_at' => now()]); // User is already verified

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated User Name',
                'email' => 'updated.email@example.com', // Changed email
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Updated User Name', $user->name);
        $this->assertSame('updated.email@example.com', $user->email);
        // After changing email, email_verified_at should be null, requiring re-verification.
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create(['email_verified_at' => now()]); // User is already verified

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email, // Unchanged email
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        // Email verified at should remain the same
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_update_with_invalid_email_format()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'invalid-email-format', // Invalid email
            ]);

        $response
            ->assertSessionHasErrors(['email']) // Expecting an error for the email field
            ->assertRedirect('/profile'); // Should redirect back to profile page with errors
    }

    public function test_user_can_delete_their_account()
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

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    // Consider adding tests for:
    // - Rate limiting on profile update and delete
    // - Password confirmation mismatch for account deletion (if applicable)
}
