<?php

use App\Models\Company;
use App\Models\DirectoryUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Cada prueba usa almacenamiento aislado, sin conectarse a MinIO.
    Storage::fake('s3');

    $this->company = Company::create([
        'name' => 'Empresa de prueba',
    ]);

    $this->department = $this->company->departments()->create([
        'name' => 'Tecnología',
    ]);

    $this->payload = [
        'first_name' => 'Marcos',
        'last_name' => 'Demostración',
        'email' => 'marcos@example.com',
        'company_id' => $this->company->id,
        'department_id' => $this->department->id,
        'photo' => UploadedFile::fake()->image('photo.jpg', 512, 512),
    ];
});

test('guests cannot access the user directory', function (
    string $method,
    string $routeName,
) {
    $response = $method === 'post'
        ? $this->post(route($routeName), $this->payload)
        : $this->get(route($routeName));

    $response->assertRedirect(route('login'));

    $this->assertDatabaseCount('directory_users', 0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
})->with([
    'listado' => ['get', 'directory-users.index'],
    'formulario' => ['get', 'directory-users.create'],
    'guardado' => ['post', 'directory-users.store'],
]);

test('non-admin users cannot access the user directory', function (
    string $method,
    string $routeName,
) {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $this->actingAs($user);

    $response = $method === 'post'
        ? $this->post(route($routeName), $this->payload)
        : $this->get(route($routeName));

    $response->assertForbidden();

    $this->assertDatabaseCount('directory_users', 0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
})->with([
    'listado' => ['get', 'directory-users.index'],
    'formulario' => ['get', 'directory-users.create'],
    'guardado' => ['post', 'directory-users.store'],
]);

test('administrators can view directory pages', function (
    string $routeName,
    string $component,
) {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->has('companies', 1)
            ->has('departments', 1)
        );
})->with([
    'listado' => ['directory-users.index', 'directory-users/index'],
    'formulario' => ['directory-users.create', 'directory-users/create'],
]);

test('administrators can create a directory user with a photo', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // La aplicación debe normalizar espacios y mayúsculas del correo.
    $this->payload['email'] = '  MARCOS@EXAMPLE.COM  ';

    $photoContents = file_get_contents(
        $this->payload['photo']->getRealPath(),
    );

    $this->actingAs($admin)
        ->from(route('directory-users.create'))
        ->post(route('directory-users.store'), $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('directory-users.index'));

    $this->assertDatabaseCount('directory_users', 1);

    $this->assertDatabaseHas('directory_users', [
        'first_name' => 'Marcos',
        'last_name' => 'Demostración',
        'email' => 'marcos@example.com',
        'department_id' => $this->department->id,
    ]);

    $directoryUser = DirectoryUser::query()->sole();

    expect($directoryUser->photo_path)->toStartWith('user-photos/');

    Storage::disk('s3')->assertExists($directoryUser->photo_path);

    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);

    expect(Storage::disk('s3')->get($directoryUser->photo_path))
        ->toBe($photoContents);

    // Registrar una persona en el directorio no crea una cuenta de acceso.
    $this->assertDatabaseCount('users', 1);
});

test('duplicate emails are rejected without storing another photo', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $existingPhoto = 'user-photos/existing.jpg';

    Storage::disk('s3')->put($existingPhoto, 'existing-photo');

    DirectoryUser::create([
        'first_name' => 'Usuario',
        'last_name' => 'Existente',
        'email' => 'marcos@example.com',
        'department_id' => $this->department->id,
        'photo_path' => $existingPhoto,
    ]);

    $this->payload['email'] = '  MARCOS@EXAMPLE.COM  ';

    $this->actingAs($admin)
        ->from(route('directory-users.create'))
        ->post(route('directory-users.store'), $this->payload)
        ->assertRedirect(route('directory-users.create'))
        ->assertSessionHasErrors(['email']);

    $this->assertDatabaseCount('directory_users', 1);

    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
    expect(Storage::disk('s3')->get($existingPhoto))
        ->toBe('existing-photo');
});

test('a department from another company is rejected', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $otherCompany = Company::create([
        'name' => 'Otra empresa',
    ]);

    $otherDepartment = $otherCompany->departments()->create([
        'name' => 'Administración',
    ]);

    // Conservamos la primera empresa, pero enviamos un departamento ajeno.
    $this->payload['department_id'] = $otherDepartment->id;

    $this->actingAs($admin)
        ->from(route('directory-users.create'))
        ->post(route('directory-users.store'), $this->payload)
        ->assertRedirect(route('directory-users.create'))
        ->assertSessionHasErrors(['department_id']);

    $this->assertDatabaseCount('directory_users', 0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

test('invalid photos are rejected without creating a user', function (
    string $scenario,
) {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->payload['photo'] = match ($scenario) {
        'missing' => null,
        'not-an-image' => UploadedFile::fake()->createWithContent(
            'photo.jpg',
            'Este contenido no es una imagen.',
        ),
        'not-square' => UploadedFile::fake()->image('photo.jpg', 512, 300),
        'wrong-size' => UploadedFile::fake()->image('photo.jpg', 256, 256),
        'too-large' => UploadedFile::fake()
            ->image('photo.jpg', 512, 512)
            ->size(2049),
    };

    $this->actingAs($admin)
        ->from(route('directory-users.create'))
        ->post(route('directory-users.store'), $this->payload)
        ->assertRedirect(route('directory-users.create'))
        ->assertSessionHasErrors(['photo']);

    $this->assertDatabaseCount('directory_users', 0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
})->with([
    'sin fotografía' => ['missing'],
    'archivo que no es imagen' => ['not-an-image'],
    'fotografía rectangular' => ['not-square'],
    'fotografía cuadrada de tamaño incorrecto' => ['wrong-size'],
    'fotografía mayor a 2 MB' => ['too-large'],
]);
