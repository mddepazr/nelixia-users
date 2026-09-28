<?php

use App\Models\User;

test('guests visiting home are redirected to login', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

test('administrators visiting home are redirected to the dashboard', function () {
    $administrator = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($administrator)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

test('non-admin users cannot access home', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertForbidden();
});
