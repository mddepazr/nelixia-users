<?php

use App\Models\Company;
use App\Models\DirectoryUser;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.users', 0)
            ->where('stats.companies', 0)
            ->where('stats.departments', 0)
            ->has('recentUsers', 0)
        );
});

test('dashboard shows real counts and the five newest directory users', function () {
    Storage::fake('s3');

    $admin = User::factory()->create(['is_admin' => true]);
    $company = Company::create(['name' => 'Nelixia']);
    $department = $company->departments()->create(['name' => 'Tecnología']);

    foreach (range(1, 6) as $number) {
        DirectoryUser::create([
            'first_name' => 'Persona',
            'last_name' => (string) $number,
            'email' => "persona{$number}@example.com",
            'department_id' => $department->getKey(),
            'photo_path' => "user-photos/{$number}.jpg",
        ]);
    }

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.users', 6)
            ->where('stats.companies', 1)
            ->where('stats.departments', 1)
            ->has('recentUsers', 5)
            ->where('recentUsers.0.full_name', 'Persona 6')
            ->where('recentUsers.0.company_name', 'Nelixia')
            ->where('recentUsers.0.department_name', 'Tecnología')
            ->missing('recentUsers.5')
        );
});
