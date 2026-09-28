<?php

use App\Models\User;
use App\Services\UserPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('s3');
});

test('only administrators can upload their profile photo', function () {
    $photo = UploadedFile::fake()->image('avatar.jpg', 512, 512);

    $this->post(route('profile.photo.update'), ['photo' => $photo])
        ->assertRedirect(route('login'));

    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
        ])
        ->assertForbidden();

    expect($user->fresh()->avatar_path)->toBeNull();
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

test('administrator can upload a cropped profile photo', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $photo = UploadedFile::fake()->image('avatar.jpg', 512, 512);
    $contents = file_get_contents($photo->getRealPath());

    $this->actingAs($admin)
        ->post(route('profile.photo.update'), ['photo' => $photo])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $path = $admin->fresh()->avatar_path;

    expect($path)->toStartWith('account-photos/');
    Storage::disk('s3')->assertExists($path);
    expect(Storage::disk('s3')->get($path))->toBe($contents);

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.id', $admin->getKey())
            ->where('auth.user.avatar', fn (string $url): bool => str_contains($url, 'account-photos'))
            ->missing('auth.user.avatar_path')
        );
});

test('replacing a profile photo removes the old one and leaves other accounts unchanged', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $other = User::factory()->create(['is_admin' => true]);

    $admin->avatar_path = 'account-photos/old.jpg';
    $admin->save();
    $other->avatar_path = 'account-photos/other.jpg';
    $other->save();

    Storage::disk('s3')->put($admin->avatar_path, 'old');
    Storage::disk('s3')->put($other->avatar_path, 'other');

    $this->actingAs($admin)
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('replacement.jpg', 512, 512),
        ])
        ->assertSessionHasNoErrors();

    $newPath = $admin->fresh()->avatar_path;

    expect($newPath)->not->toBe('account-photos/old.jpg');
    Storage::disk('s3')->assertExists($newPath);
    Storage::disk('s3')->assertMissing('account-photos/old.jpg');
    Storage::disk('s3')->assertExists('account-photos/other.jpg');
    expect($other->fresh()->avatar_path)->toBe('account-photos/other.jpg');
    $this->assertDatabaseCount('pending_photo_deletions', 0);
});

test('an invalid photo does not alter the existing profile photo', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->avatar_path = 'account-photos/old.jpg';
    $admin->save();
    Storage::disk('s3')->put($admin->avatar_path, 'old');

    $this->actingAs($admin)
        ->from(route('profile.edit'))
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('rectangle.jpg', 600, 400),
        ])
        ->assertSessionHasErrors('photo');

    expect($admin->fresh()->avatar_path)->toBe('account-photos/old.jpg');
    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
});

test('a storage failure leaves the account and its photo unchanged', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->avatar_path = 'account-photos/old.jpg';
    $admin->save();
    Storage::disk('s3')->put($admin->avatar_path, 'old');

    $this->mock(UserPhotoStorage::class)
        ->shouldReceive('store')
        ->once()
        ->andThrow(new RuntimeException('MinIO no respondió.'));

    $this->actingAs($admin)
        ->from(route('profile.edit'))
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('replacement.jpg', 512, 512),
        ])
        ->assertSessionHasErrors('photo');

    expect($admin->fresh()->avatar_path)->toBe('account-photos/old.jpg');
    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
    $this->assertDatabaseCount('pending_photo_deletions', 0);
});

test('a database failure preserves the old photo and removes the newly uploaded file', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->avatar_path = 'account-photos/old.jpg';
    $admin->save();
    Storage::disk('s3')->put($admin->avatar_path, 'old');

    $eventName = 'eloquent.updating: '.User::class;
    Event::listen($eventName, function (): void {
        throw new RuntimeException('Fallo simulado de base de datos.');
    });

    try {
        $this->actingAs($admin)
            ->from(route('profile.edit'))
            ->post(route('profile.photo.update'), [
                'photo' => UploadedFile::fake()->image('replacement.jpg', 512, 512),
            ])
            ->assertSessionHasErrors('photo');
    } finally {
        Event::forget($eventName);
    }

    expect($admin->fresh()->avatar_path)->toBe('account-photos/old.jpg');
    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
    $this->assertDatabaseCount('pending_photo_deletions', 0);
});

test('a failed cleanup can be retried after replacing a profile photo', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->avatar_path = 'account-photos/old.jpg';
    $admin->save();
    Storage::disk('s3')->put($admin->avatar_path, 'old');

    $storage = $this->mock(UserPhotoStorage::class);

    $storage
        ->shouldReceive('store')
        ->once()
        ->andReturn('account-photos/new.jpg');

    $storage
        ->shouldReceive('delete')
        ->once()
        ->with('account-photos/old.jpg')
        ->andThrow(new RuntimeException('MinIO no respondió.'));

    Storage::disk('s3')->put('account-photos/new.jpg', 'new');

    $this->actingAs($admin)
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('replacement.jpg', 512, 512),
        ])
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->avatar_path)->toBe('account-photos/new.jpg');
    $this->assertDatabaseHas('pending_photo_deletions', [
        'path' => 'account-photos/old.jpg',
    ]);

    $this->app->instance(UserPhotoStorage::class, new UserPhotoStorage);
    $this->artisan('app:cleanup-user-photos')->assertExitCode(0);

    Storage::disk('s3')->assertMissing('account-photos/old.jpg');
    Storage::disk('s3')->assertExists('account-photos/new.jpg');
    $this->assertDatabaseCount('pending_photo_deletions', 0);
});

test('deleting an account also removes its profile photo', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->avatar_path = 'account-photos/old.jpg';
    $admin->save();
    Storage::disk('s3')->put($admin->avatar_path, 'old');

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($admin->fresh())->toBeNull();
    Storage::disk('s3')->assertMissing('account-photos/old.jpg');
    $this->assertDatabaseCount('pending_photo_deletions', 0);
});

test('a failed cleanup after account deletion remains retryable', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->avatar_path = 'account-photos/old.jpg';
    $admin->save();
    Storage::disk('s3')->put($admin->avatar_path, 'old');

    $this->mock(UserPhotoStorage::class)
        ->shouldReceive('delete')
        ->once()
        ->with('account-photos/old.jpg')
        ->andThrow(new RuntimeException('MinIO no respondió.'));

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($admin->fresh())->toBeNull();
    $this->assertDatabaseHas('pending_photo_deletions', [
        'path' => 'account-photos/old.jpg',
    ]);

    $this->app->instance(UserPhotoStorage::class, new UserPhotoStorage);
    $this->artisan('app:cleanup-user-photos')->assertExitCode(0);

    Storage::disk('s3')->assertMissing('account-photos/old.jpg');
    $this->assertDatabaseCount('pending_photo_deletions', 0);
});
