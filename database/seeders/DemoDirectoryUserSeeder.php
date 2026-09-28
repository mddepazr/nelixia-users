<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DirectoryUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DemoDirectoryUserSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('demo_seed_runs')->where('name', 'directory-users')->exists()) {
            return;
        }

        $this->call(CatalogSeeder::class);

        $company = Company::query()->where('name', 'Nelixia')->firstOrFail();

        $examples = [
            ['Ana', 'López', 'demo.ana@example.test', 'Tecnología', 'ana.jpg'],
            ['Carlos', 'Méndez', 'demo.carlos@example.test', 'Operaciones', 'carlos.jpg'],
            ['Lucía', 'Ramírez', 'demo.lucia@example.test', 'Recursos Humanos', 'lucia.jpg'],
        ];

        $uploaded = [];
        $users = [];

        try {
            foreach ($examples as [$firstName, $lastName, $email, $departmentName, $filename]) {
                // No modifica registros que ya tengan ese correo.
                if (DirectoryUser::query()->where('email', $email)->exists()) {
                    continue;
                }

                $department = $company->departments()->where('name', $departmentName)->firstOrFail();
                $path = 'user-photos/demo-'.$filename;
                $source = __DIR__.'/photos/'.$filename;
                $contents = file_get_contents($source);

                if ($contents === false) {
                    throw new RuntimeException('No se pudo leer la foto de muestra: '.$source);
                }

                if (! Storage::disk('s3')->exists($path)) {
                    if (! Storage::disk('s3')->put($path, $contents, 'private')) {
                        throw new RuntimeException('No se pudo guardar la foto de muestra: '.$path);
                    }

                    $uploaded[] = $path;
                }

                $users[] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'department_id' => $department->getKey(),
                    'photo_path' => $path,
                ];
            }

            DB::transaction(function () use ($users): void {
                foreach ($users as $attributes) {
                    DirectoryUser::query()->firstOrCreate(
                        ['email' => $attributes['email']],
                        $attributes,
                    );
                }

                DB::table('demo_seed_runs')->insert([
                    'name' => 'directory-users',
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($uploaded as $path) {
                try {
                    Storage::disk('s3')->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }
}
