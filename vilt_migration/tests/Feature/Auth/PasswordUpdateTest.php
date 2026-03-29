<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');
});

test('password confirmation mismatch fails on update', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password', // Mismatch
        ]);

    $response
        ->assertSessionHasErrors(['password'])
        ->assertRedirect('/profile');
});

test('password update attempts are rate limited', function () {
    $user = User::factory()->create();

    // Simulate exceeding the rate limit for password updates
    // Assuming default is 5 attempts within a time window
    for ($i = 0; $i < config('fortify.limiters.update_password', 5); $i++) {
        $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password-' . $i,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
    }

    // Attempt one more request, which should be rate limited
    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password', // This one might pass if current_password was the limiter, but it's not.
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertSessionHasErrors(['current_password' => trans('auth.throttle_current_password')]); // Check for throttling error
    $response->assertStatus(429); // Too Many Requests

    // Verify rate limiter status for the key
    $key = Str::lower('update-password.'.$user->email).config('app.key');
    expect(RateLimiter::tooManyAttempts($key, config('fortify.limiters.update_password', 5)))->toBeTrue();
});
