<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('login displays a throttle message and allows access after the cooldown', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');
    }

    // Incluso la contraseña correcta debe rechazarse durante el bloqueo.
    $response = $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ], [
            'X-Inertia' => 'true',
        ]);

    $response
        ->assertStatus(303)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => __('auth.throttle', [
                'seconds' => 60,
                'minutes' => 1,
            ]),
        ])
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();

    $this->travel(61)->seconds();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('JSON login requests retain the 429 response when rate limited', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', __('auth.throttle', [
            'seconds' => 60,
            'minutes' => 1,
        ]));

    $this->assertGuest();
});
