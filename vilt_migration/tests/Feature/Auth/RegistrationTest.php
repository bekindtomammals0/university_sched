<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('password confirmation does not match', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password-different',
    ]);

    $response->assertSessionHasErrors(['password']);
    $response->assertStatus(422); // Unprocessable Entity for validation errors
});

test('email already taken', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->post('/register', [
        'name' => 'Another User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
    $response->assertStatus(422);
});

test('invalid email format', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'invalid-email-format',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
    $response->assertStatus(422);
});
