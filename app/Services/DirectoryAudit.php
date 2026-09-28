<?php

namespace App\Services;

use App\Models\DirectoryAuditEntry;
use App\Models\DirectoryUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DirectoryAudit
{
    /** @return array{name: string, first_name: string, last_name: string, email: string, company: string, department: string, photo: string} */
    public function snapshot(DirectoryUser $user): array
    {
        $user->load('department.company');

        return [
            'name' => $user->first_name.' '.$user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'company' => $user->department->company->name,
            'department' => $user->department->name,
            'photo' => $user->photo_path,
        ];
    }

    public function created(DirectoryUser $user): void
    {
        $this->record($user, 'created', $this->snapshot($user));
    }

    /** @param array{name: string, first_name: string, last_name: string, email: string, company: string, department: string, photo: string} $before */
    public function updated(DirectoryUser $user, array $before): void
    {
        $after = $this->snapshot($user);
        $changes = [];

        foreach (['first_name', 'last_name', 'email', 'company', 'department'] as $field) {
            if ($before[$field] !== $after[$field]) {
                $changes[$field] = ['from' => $before[$field], 'to' => $after[$field]];
            }
        }

        if ($before['photo'] !== $after['photo']) {
            $changes['photo'] = ['from' => null, 'to' => 'Fotografía reemplazada'];
        }

        if ($changes !== []) {
            $this->record($user, 'updated', $after, $changes);
        }
    }

    /** @param array{name: string, first_name: string, last_name: string, email: string, company: string, department: string, photo: string} $snapshot */
    public function deleted(DirectoryUser $user, array $snapshot): void
    {
        $this->record($user, 'deleted', $snapshot);
    }

    /**
     * @param  array{name: string, first_name: string, last_name: string, email: string, company: string, department: string, photo: string}  $snapshot
     * @param  array<string, array{from: string|null, to: string|null}>  $changes
     */
    private function record(DirectoryUser $user, string $action, array $snapshot, array $changes = []): void
    {
        $actor = Auth::user();
        $actor = $actor instanceof User ? $actor : null;

        DirectoryAuditEntry::create([
            'directory_user_id' => $user->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'action' => $action,
            'subject_name' => $snapshot['name'],
            'subject_email' => $snapshot['email'],
            'subject_company' => $snapshot['company'],
            'subject_department' => $snapshot['department'],
            'changes' => $changes === [] ? null : $changes,
        ]);
    }
}
