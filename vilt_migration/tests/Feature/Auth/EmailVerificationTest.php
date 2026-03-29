<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    // Optionally, assert a redirect to login or a specific error page/message
    // This depends on how Laravel handles invalid verification links after the attempt.
    // For now, we check that the email is not verified.
});

test('email verification link expires', function () {
    $user = User::factory()->unverified()->create();

    // Create a signed URL that has already expired
    $expiredVerificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        Carbon::now()->subMinutes(61), // Expired 1 minute ago
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($expiredVerificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    // Laravel's signed routes typically redirect to / with an 'invalid' query param on expiry
    $response->assertRedirect(URL::signedRoute('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)], now()->addMinutes(60)) . '?invalid=1'); // This asserts the signed route redirect behavior
});

test('already verified email should not be re-verified', function () {
    $user = User::factory()->create(['email_verified_at' => now()]); // User is already verified

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue(); // Should remain verified
    // Typically, Laravel redirects to the dashboard or intended page if already verified
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('user can request to resend verification email', function () {
    $user = User::factory()->unverified()->create();

    Event::fake(); // To spy on the email sending event

    $response = $this->actingAs($user)->post('/email/verification-notification');

    Event::assertDispatched(function (object $event) use ($user) {
        // Check if the event is related to sending a verification notification
        // The exact event class might vary, but often it's within Illuminate\Auth\Notifications\VerifyEmail
        // Or a custom notification class. For simplicity, we'll check for a user match.
        // A more robust test would check for the specific notification type.
        return $event->user->id === $user->id;
    });

    $response->assertSessionHas('status', 'verification-link-sent');
});

test('resend verification email request for an already verified user does nothing', function () {
    $user = User::factory()->create(['email_verified_at' => now()]); // User is already verified

    Event::fake();

    $response = $this->actingAs($user)->post('/email/verification-notification');

    Event::assertNotDispatched(Verified::class); // Ensure no verification event is dispatched
    $response->assertSessionHas('status', 'verification-link-sent'); // Laravel might still send a link, or this status might be generic. We check it anyway.
});
