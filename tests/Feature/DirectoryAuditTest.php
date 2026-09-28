<?php

use App\Models\Company;
use App\Models\DirectoryAuditEntry;
use App\Models\DirectoryUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function () {
    Storage::fake('s3');

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->company = Company::create(['name' => 'Nelixia']);
    $this->department = $this->company->departments()->create(['name' => 'Tecnología']);
    $this->otherDepartment = $this->company->departments()->create(['name' => 'Operaciones']);
});

function createAuditedDirectoryUser(TestCase $case, User $admin, int $companyId, int $departmentId): DirectoryUser
{
    $case->actingAs($admin)
        ->post(route('directory-users.store'), [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'ana@example.test',
            'company_id' => $companyId,
            'department_id' => $departmentId,
            'photo' => UploadedFile::fake()->image('ana.jpg', 512, 512),
        ])
        ->assertSessionHasNoErrors();

    return DirectoryUser::query()->sole();
}

test('creating editing and deleting a user records actor and exact changes', function () {
    $user = createAuditedDirectoryUser($this, $this->admin, $this->company->id, $this->department->id);

    $created = DirectoryAuditEntry::query()->sole();
    expect($created->action)->toBe('created');
    expect($created->actor_user_id)->toBe($this->admin->id);
    expect($created->actor_name)->toBe($this->admin->name);
    expect($created->subject_name)->toBe('Ana López');
    expect($created->changes)->toBeNull();

    $this->actingAs($this->admin)
        ->patch(route('directory-users.update', $user), [
            'first_name' => 'Ana',
            'last_name' => 'Morales',
            'email' => 'ana@example.test',
            'company_id' => $this->company->id,
            'department_id' => $this->otherDepartment->id,
        ])
        ->assertSessionHasNoErrors();

    $updated = DirectoryAuditEntry::query()->where('action', 'updated')->sole();
    expect($updated->changes)->toBe([
        'last_name' => ['from' => 'López', 'to' => 'Morales'],
        'department' => ['from' => 'Tecnología', 'to' => 'Operaciones'],
    ]);

    $this->actingAs($this->admin)
        ->delete(route('directory-users.destroy', $user))
        ->assertSessionHasNoErrors();

    expect($user->fresh())->toBeNull();
    $deleted = DirectoryAuditEntry::query()->where('action', 'deleted')->sole();
    expect($deleted->subject_name)->toBe('Ana Morales');
    expect($deleted->directory_user_id)->toBe($user->id);
    $this->assertDatabaseCount('directory_audit_entries', 3);
});

test('a save with no changed fields does not create a history event', function () {
    $user = createAuditedDirectoryUser($this, $this->admin, $this->company->id, $this->department->id);

    $this->actingAs($this->admin)
        ->patch(route('directory-users.update', $user), [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'ana@example.test',
            'company_id' => $this->company->id,
            'department_id' => $this->department->id,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('directory_audit_entries', 1);
});

test('replacing a photo records the event without revealing storage paths', function () {
    $user = createAuditedDirectoryUser($this, $this->admin, $this->company->id, $this->department->id);
    $oldPath = $user->photo_path;

    $this->actingAs($this->admin)
        ->patch(route('directory-users.update', $user), [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'ana@example.test',
            'company_id' => $this->company->id,
            'department_id' => $this->department->id,
            'photo' => UploadedFile::fake()->image('replacement.jpg', 512, 512),
        ])
        ->assertSessionHasNoErrors();

    $updated = DirectoryAuditEntry::query()->where('action', 'updated')->sole();
    expect($updated->changes)->toBe([
        'photo' => ['from' => null, 'to' => 'Fotografía reemplazada'],
    ]);
    expect(json_encode($updated->changes))->not->toContain($oldPath);
    Storage::disk('s3')->assertMissing($oldPath);
});

test('only administrators can read history and filter it after user deletion', function () {
    $user = createAuditedDirectoryUser($this, $this->admin, $this->company->id, $this->department->id);
    $this->actingAs($this->admin)->delete(route('directory-users.destroy', $user));

    $this->get(route('directory-audit.index', [
        'action' => 'deleted',
        'directory_user_id' => $user->id,
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('directory-audit/index')
        ->has('entries.data', 1)
        ->where('entries.data.0.subject_email', 'ana@example.test')
        ->where('entries.data.0.action', 'deleted')
    );

    auth()->logout();
    $this->get(route('directory-audit.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get(route('directory-audit.index'))
        ->assertForbidden();
});

test('an audit write failure rolls back the user and cleans up the uploaded photo', function () {
    $eventName = 'eloquent.creating: '.DirectoryAuditEntry::class;
    Event::listen($eventName, function (): void {
        throw new RuntimeException('Fallo simulado del historial.');
    });

    try {
        $this->actingAs($this->admin)
            ->post(route('directory-users.store'), [
                'first_name' => 'Ana',
                'last_name' => 'López',
                'email' => 'ana@example.test',
                'company_id' => $this->company->id,
                'department_id' => $this->department->id,
                'photo' => UploadedFile::fake()->image('ana.jpg', 512, 512),
            ])
            ->assertSessionHasErrors('save');
    } finally {
        Event::forget($eventName);
    }

    $this->assertDatabaseCount('directory_users', 0);
    $this->assertDatabaseCount('directory_audit_entries', 0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

test('an audit failure during deletion preserves the user and its photo', function () {
    $user = createAuditedDirectoryUser($this, $this->admin, $this->company->id, $this->department->id);
    $path = $user->photo_path;

    $eventName = 'eloquent.creating: '.DirectoryAuditEntry::class;
    Event::listen($eventName, function (): void {
        throw new RuntimeException('Fallo simulado del historial.');
    });

    try {
        $this->actingAs($this->admin)
            ->delete(route('directory-users.destroy', $user))
            ->assertSessionHasErrors('deletion');
    } finally {
        Event::forget($eventName);
    }

    expect($user->fresh())->not->toBeNull();
    Storage::disk('s3')->assertExists($path);
    $this->assertDatabaseCount('pending_photo_deletions', 0);
    $this->assertDatabaseCount('directory_audit_entries', 1);
});
