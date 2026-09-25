<?php

use App\Models\Company;
use App\Models\DirectoryUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');

    $this->company = Company::create([
        'name' => 'Empresa de prueba',
    ]);

    $this->department = $this->company->departments()->create([
        'name' => 'Tecnología',
    ]);

    $this->originalPhotoPath = 'user-photos/original.jpg';

    Storage::disk('s3')->put(
        $this->originalPhotoPath,
        'Contenido de la fotografía original',
    );

    $this->directoryUser = DirectoryUser::create([
        'first_name' => 'Usuario',
        'last_name' => 'Original',
        'email' => 'original@example.com',
        'department_id' => $this->department->getKey(),
        'photo_path' => $this->originalPhotoPath,
    ]);

    $this->payload = [
        '_method' => 'PATCH',
        'first_name' => 'Usuario actualizado',
        'last_name' => 'Demostración',
        'email' => 'original@example.com',
        'company_id' => $this->company->getKey(),
        'department_id' => $this->department->getKey(),
    ];
});

test('editing requires administrator access', function (
    bool $authenticated,
    string $operation,
) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create([
            'is_admin' => false,
        ]));
    }

    $response = $operation === 'edit'
        ? $this->get(route('directory-users.edit', $this->directoryUser))
        : $this->post(
            route('directory-users.update', $this->directoryUser),
            $this->payload,
        );

    if ($authenticated) {
        $response->assertForbidden();
    } else {
        $response->assertRedirect(route('login'));
    }

    expect($this->directoryUser->fresh()->first_name)
        ->toBe('Usuario');

    Storage::disk('s3')->assertExists($this->originalPhotoPath);

    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
})->with([
    'visitante: formulario' => [false, 'edit'],
    'visitante: actualización' => [false, 'update'],
    'usuario sin permisos: formulario' => [true, 'edit'],
    'usuario sin permisos: actualización' => [true, 'update'],
]);

test('administrators can update details without replacing the photo', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $newDepartment = $this->company->departments()->create([
        'name' => 'Operaciones',
    ]);

    $this->payload['department_id'] = $newDepartment->getKey();

    $this->post(
        route('directory-users.update', $this->directoryUser),
        $this->payload,
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('directory-users.index'));

    $this->assertDatabaseCount('directory_users', 1);

    $this->assertDatabaseHas('directory_users', [
        'id' => $this->directoryUser->getKey(),
        'first_name' => 'Usuario actualizado',
        'last_name' => 'Demostración',
        'email' => 'original@example.com',
        'department_id' => $newDepartment->getKey(),
        'photo_path' => $this->originalPhotoPath,
    ]);

    expect(Storage::disk('s3')->get($this->originalPhotoPath))
        ->toBe('Contenido de la fotografía original');

    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
});

test('replacing the photo stores the new file and deletes the previous one', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $photo = UploadedFile::fake()->image('replacement.jpg', 512, 512);
    $contents = file_get_contents($photo->getPathname());

    $this->payload['photo'] = $photo;

    $this->post(
        route('directory-users.update', $this->directoryUser),
        $this->payload,
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('directory-users.index'));

    $updatedUser = $this->directoryUser->fresh();

    expect($updatedUser->photo_path)
        ->not->toBe($this->originalPhotoPath);

    Storage::disk('s3')->assertExists($updatedUser->photo_path);
    Storage::disk('s3')->assertMissing($this->originalPhotoPath);

    expect(Storage::disk('s3')->get($updatedUser->photo_path))
        ->toBe($contents);

    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);

    $this->assertDatabaseCount('directory_users', 1);
});

test('another users email is rejected without changing data or photos', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    Storage::disk('s3')->put('user-photos/another.jpg', 'Otra fotografía');

    DirectoryUser::create([
        'first_name' => 'Otra',
        'last_name' => 'Persona',
        'email' => 'occupied@example.com',
        'department_id' => $this->department->getKey(),
        'photo_path' => 'user-photos/another.jpg',
    ]);

    $originalAttributes = $this->directoryUser->getAttributes();

    $this->payload['email'] = 'occupied@example.com';
    $this->payload['photo'] = UploadedFile::fake()
        ->image('replacement.jpg', 512, 512);

    $this->from(route('directory-users.edit', $this->directoryUser))
        ->post(
            route('directory-users.update', $this->directoryUser),
            $this->payload,
        )
        ->assertSessionHasErrors('email');

    $actualAttributes = $this->directoryUser->fresh()->getAttributes();

    ksort($originalAttributes);
    ksort($actualAttributes);

    expect($actualAttributes)->toBe($originalAttributes);

    expect(Storage::disk('s3')->get($this->originalPhotoPath))
        ->toBe('Contenido de la fotografía original');

    Storage::disk('s3')->assertExists('user-photos/another.jpg');

    expect(Storage::disk('s3')->allFiles())->toHaveCount(2);
});

test('editing rejects a department belonging to another company', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $otherCompany = Company::create([
        'name' => 'Otra empresa',
    ]);

    $otherDepartment = $otherCompany->departments()->create([
        'name' => 'Administración',
    ]);

    $originalAttributes = $this->directoryUser->getAttributes();

    $this->payload['department_id'] = $otherDepartment->getKey();
    $this->payload['photo'] = UploadedFile::fake()
        ->image('replacement.jpg', 512, 512);

    $this->from(route('directory-users.edit', $this->directoryUser))
        ->post(
            route('directory-users.update', $this->directoryUser),
            $this->payload,
        )
        ->assertSessionHasErrors('department_id');

    $actualAttributes = $this->directoryUser->fresh()->getAttributes();

    ksort($originalAttributes);
    ksort($actualAttributes);

    expect($actualAttributes)->toBe($originalAttributes);

    Storage::disk('s3')->assertExists($this->originalPhotoPath);

    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
});

test('an invalid replacement photo leaves the original user unchanged', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $originalAttributes = $this->directoryUser->getAttributes();

    $this->payload['photo'] = UploadedFile::fake()
        ->image('rectangle.jpg', 512, 300);

    $this->from(route('directory-users.edit', $this->directoryUser))
        ->post(
            route('directory-users.update', $this->directoryUser),
            $this->payload,
        )
        ->assertSessionHasErrors('photo');

    $actualAttributes = $this->directoryUser->fresh()->getAttributes();

    ksort($originalAttributes);
    ksort($actualAttributes);

    expect($actualAttributes)->toBe($originalAttributes);

    expect(Storage::disk('s3')->get($this->originalPhotoPath))
        ->toBe('Contenido de la fotografía original');

    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
});
