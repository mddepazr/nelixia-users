<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('non-admin users cannot access the dashboard', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('administrators can access the dashboard', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();
});
