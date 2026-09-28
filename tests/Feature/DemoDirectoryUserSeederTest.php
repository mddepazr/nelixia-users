<?php

use App\Models\DirectoryUser;
use Database\Seeders\DemoDirectoryUserSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

test('the demo seeder creates three directory users with square photos in s3', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDirectoryUserSeeder'])
        ->assertExitCode(0);

    expect(DirectoryUser::query()->count())->toBe(3);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseHas('demo_seed_runs', ['name' => 'directory-users']);

    foreach (DirectoryUser::query()->get() as $user) {
        expect($user->email)->toEndWith('@example.test');
        expect($user->department->company->name)->toBe('Nelixia');
        Storage::disk('s3')->assertExists($user->photo_path);

        $image = getimagesizefromstring(Storage::disk('s3')->get($user->photo_path));

        if ($image === false) {
            throw new RuntimeException('La fotografía de muestra no es una imagen válida.');
        }

        expect($image[0])->toBe(512);
        expect($image[1])->toBe(512);
        expect($image['mime'])->toBe('image/jpeg');
    }
});

test('running the demo seeder again never restores a deleted user or photo', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDirectoryUserSeeder'])
        ->assertExitCode(0);

    $user = DirectoryUser::query()->where('email', 'demo.ana@example.test')->firstOrFail();
    $oldPath = $user->photo_path;
    $user->delete();
    Storage::disk('s3')->delete($oldPath);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDirectoryUserSeeder'])
        ->assertExitCode(0);

    expect(DirectoryUser::query()->count())->toBe(2);
    expect(DirectoryUser::query()->where('email', 'demo.ana@example.test')->exists())->toBeFalse();
    Storage::disk('s3')->assertMissing($oldPath);
});

test('a database failure rolls back demo users and removes uploaded photos', function () {
    $eventName = 'eloquent.creating: '.DirectoryUser::class;
    Event::listen($eventName, function (DirectoryUser $user): void {
        if ($user->email === 'demo.lucia@example.test') {
            throw new RuntimeException('Fallo simulado de la base de datos.');
        }
    });

    try {
        expect(fn () => app(DemoDirectoryUserSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'Fallo simulado de la base de datos.');
    } finally {
        Event::forget($eventName);
    }

    expect(DirectoryUser::query()->count())->toBe(0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
    $this->assertDatabaseCount('demo_seed_runs', 0);
});
