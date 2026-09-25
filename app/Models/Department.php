<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property-read Company $company
 */
#[Fillable(['company_id', 'name'])]
class Department extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<DirectoryUser, $this> */
    public function directoryUsers(): HasMany
    {
        return $this->hasMany(DirectoryUser::class);
    }
}
