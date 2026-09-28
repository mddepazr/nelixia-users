<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('access-admin');
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
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
        return ['photo' => 'fotografía'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photo.required' => 'Seleccioná y confirmá el recorte de una fotografía.',
            'photo.image' => 'El archivo debe ser una imagen válida.',
            'photo.mimes' => 'La fotografía debe estar en formato JPG, PNG o WebP.',
            'photo.max' => 'La fotografía recortada no puede superar los 2 MB.',
            'photo.dimensions' => 'Confirmá el recorte de la fotografía a 512 × 512 píxeles.',
            'photo.uploaded' => 'No se pudo recibir la fotografía. Revisá su tamaño e intentá nuevamente.',
        ];
    }
}
