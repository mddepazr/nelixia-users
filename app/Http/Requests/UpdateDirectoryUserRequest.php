<?php

namespace App\Http\Requests;

use App\Models\DirectoryUser;
use Illuminate\Validation\Rule;

class UpdateDirectoryUserRequest extends StoreDirectoryUserRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $directoryUser = $this->route('directoryUser');

        if (! $directoryUser instanceof DirectoryUser) {
            abort(404);
        }

        // Conservamos las validaciones, autorización y mensajes de creación.
        $rules = parent::rules();

        $rules['email'] = [
            'required',
            'string',
            'email',
            'max:254',
            Rule::unique('directory_users', 'email')->ignore($directoryUser),
        ];

        $rules['photo'] = [
            'bail',
            'nullable',
            'file',
            'max:2048',
            'image',
            'mimes:jpg,jpeg,png,webp',
            Rule::dimensions()->width(512)->height(512),
        ];

        return $rules;
    }
}
