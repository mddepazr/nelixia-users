<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $photo_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Department $department
 */
#[Fillable([
    'first_name',
    'last_name',
    'email',
    'department_id',
    'photo_path',
])]
class DirectoryUser extends Model
{
    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
