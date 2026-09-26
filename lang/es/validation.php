<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser un texto.',
    'email' => 'Ingresá un correo electrónico válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual es incorrecta.',

    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],

    'max' => [
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
    ],

    'password' => [
        'letters' => 'La contraseña debe incluir al menos una letra.',
        'mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
        'numbers' => 'La contraseña debe incluir al menos un número.',
        'symbols' => 'La contraseña debe incluir al menos un símbolo.',
        'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elegí otra.',
    ],

    'custom' => [
        'password' => [
            'confirmed' => 'Las contraseñas no coinciden. Revisá ambos campos.',
        ],
    ],

    'attributes' => [
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'current_password' => 'contraseña actual',
        'name' => 'nombre',
    ],
];
