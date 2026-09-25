<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Crea una cuenta administradora de la aplicación';

    public function handle(): int
    {
        $name = trim(text(
            label: 'Nombre del administrador',
            required: true,
        ));

        $email = Str::lower(trim(text(
            label: 'Correo del administrador',
            required: true,
        )));

        $adminPassword = password(
            label: 'Contraseña',
            required: true,
            hint: 'Mínimo 12 caracteres, con mayúsculas, minúsculas y números.',
        );

        $confirmation = password(
            label: 'Confirmá la contraseña',
            required: true,
        );

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $adminPassword,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:254',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)->mixedCase()->numbers(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::INVALID;
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;

        // El cast "hashed" del modelo protege la contraseña al asignarla.
        $user->password = $adminPassword;

        // Este permiso se asigna explícitamente desde la consola.
        $user->is_admin = true;

        $user->save();

        $this->info('Administrador creado correctamente.');

        return self::SUCCESS;
    }
}
