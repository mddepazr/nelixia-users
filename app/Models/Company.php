<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Company extends Model
{
    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
