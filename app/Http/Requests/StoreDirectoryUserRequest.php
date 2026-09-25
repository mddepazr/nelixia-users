<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDirectoryUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('access-admin');
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge([
                'email' => Str::lower(trim($email)),
            ]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('directory_users', 'email'),
            ],
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id'),
            ],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where('company_id', $this->integer('company_id')),
            ],
            'photo' => [
                'bail',
                'required',
                'file',
                'max:2048',
                'image',
                'mimes:jpg,jpeg,png,webp',
                Rule::dimensions()->width(512)->height(512),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'email' => 'correo electrónico',
            'company_id' => 'empresa',
            'department_id' => 'departamento',
            'photo' => 'fotografía',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'Seleccioná una opción válida para :attribute.',
            'first_name.max' => 'El nombre no puede superar los 100 caracteres.',
            'last_name.max' => 'El apellido no puede superar los 100 caracteres.',
            'email.email' => 'Ingresá un correo electrónico válido.',
            'email.max' => 'El correo no puede superar los 254 caracteres.',
            'email.unique' => 'Este correo ya está registrado en el módulo.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'department_id.exists' => 'El departamento no pertenece a la empresa seleccionada.',
            'photo.file' => 'Seleccioná un archivo de imagen válido.',
            'photo.image' => 'El archivo debe ser una imagen válida.',
            'photo.mimes' => 'La fotografía debe estar en formato JPG, PNG o WebP.',
            'photo.max' => 'La fotografía recortada no puede superar los 2 MB.',
            'photo.dimensions' => 'Confirmá el recorte de la fotografía a 512 × 512 píxeles.',
            'photo.uploaded' => 'No se pudo recibir la fotografía. Revisá su tamaño e intentá nuevamente.',
        ];
    }
}
