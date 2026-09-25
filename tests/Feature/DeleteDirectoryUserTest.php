<?php

use App\Models\Company;
use App\Models\DirectoryUser;
use App\Models\User;
use App\Services\UserPhotoStorage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');

    $company = Company::create([
        'name' => 'Empresa de prueba',
    ]);

    $department = $company->departments()->create([
        'name' => 'Tecnología',
    ]);

    $this->photoPath = 'user-photos/delete-test.jpg';

    Storage::disk('s3')->put($this->photoPath, 'Fotografía de prueba');

    $this->directoryUser = DirectoryUser::create([
        'first_name' => 'Usuario',
        'last_name' => 'De prueba',
        'email' => 'delete@example.com',
        'department_id' => $department->getKey(),
        'photo_path' => $this->photoPath,
    ]);
});

test('deleting requires administrator access', function (bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create([
            'is_admin' => false,
        ]));
    }

    $response = $this->delete(
        route('directory-users.destroy', $this->directoryUser),
    );

    if ($authenticated) {
        $response->assertForbidden();
    } else {
        $response->assertRedirect(route('login'));
    }

    $this->assertDatabaseHas('directory_users', [
        'id' => $this->directoryUser->getKey(),
    ]);

    $this->assertDatabaseCount('pending_photo_deletions', 0);

    Storage::disk('s3')->assertExists($this->photoPath);
})->with([
    'visitante' => [false],
    'usuario sin permisos' => [true],
]);

test('administrators can delete a directory user and its photo', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->delete(route('directory-users.destroy', $this->directoryUser))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('directory-users.index'));

    $this->assertDatabaseCount('directory_users', 0);
    $this->assertDatabaseCount('pending_photo_deletions', 0);

    $this->assertDatabaseHas('users', [
        'id' => $admin->getKey(),
    ]);

    Storage::disk('s3')->assertMissing($this->photoPath);
});

test('a storage failure leaves a pending deletion that can be retried', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $this->mock(UserPhotoStorage::class)
        ->shouldReceive('delete')
        ->once()
        ->with($this->photoPath)
        ->andThrow(new RuntimeException('Fallo simulado de almacenamiento.'));

    $this->delete(route('directory-users.destroy', $this->directoryUser))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('directory-users.index'));

    $this->assertDatabaseCount('directory_users', 0);

    $this->assertDatabaseHas('pending_photo_deletions', [
        'path' => $this->photoPath,
    ]);

    Storage::disk('s3')->assertExists($this->photoPath);

    // El siguiente intento vuelve a utilizar el almacenamiento simulado normal.
    $this->app->instance(UserPhotoStorage::class, new UserPhotoStorage);

    $this->artisan('app:cleanup-user-photos')->assertExitCode(0);

    $this->assertDatabaseCount('pending_photo_deletions', 0);

    Storage::disk('s3')->assertMissing($this->photoPath);
});

test('a database failure preserves the user and its photo', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $eventName = 'eloquent.deleting: '.DirectoryUser::class;

    Event::listen($eventName, function (): void {
        throw new RuntimeException('Fallo simulado al eliminar el registro.');
    });

    try {
        $this->from(route('directory-users.index'))
            ->delete(route('directory-users.destroy', $this->directoryUser))
            ->assertSessionHasErrors('deletion');
    } finally {
        Event::forget($eventName);
    }

    $this->assertDatabaseHas('directory_users', [
        'id' => $this->directoryUser->getKey(),
        'photo_path' => $this->photoPath,
    ]);

    $this->assertDatabaseCount('pending_photo_deletions', 0);

    expect(Storage::disk('s3')->get($this->photoPath))
        ->toBe('Fotografía de prueba');
});

test('deleting a nonexistent user returns not found', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $this->delete(route('directory-users.destroy', 999999))
        ->assertNotFound();

    $this->assertDatabaseCount('directory_users', 1);
    $this->assertDatabaseCount('pending_photo_deletions', 0);

    Storage::disk('s3')->assertExists($this->photoPath);
});
