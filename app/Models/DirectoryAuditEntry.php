<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $directory_user_id
 * @property int|null $actor_user_id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property string $action
 * @property string $subject_name
 * @property string $subject_email
 * @property string $subject_company
 * @property string $subject_department
 * @property array<string, array{from: string|null, to: string|null}>|null $changes
 * @property Carbon $created_at
 */
#[Fillable([
    'directory_user_id',
    'actor_user_id',
    'actor_name',
    'actor_email',
    'action',
    'subject_name',
    'subject_email',
    'subject_company',
    'subject_department',
    'changes',
])]
class DirectoryAuditEntry extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }
}
