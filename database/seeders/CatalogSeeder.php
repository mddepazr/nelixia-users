<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Datos ilustrativos; no representan la estructura oficial de Nelixia.
        $catalogs = [
            'Nelixia' => [
                'Operaciones',
                'Recursos Humanos',
                'Tecnología',
            ],
            'Empresa de demostración' => [
                'Administración',
                'Logística',
                'Recursos Humanos',
            ],
        ];

        DB::transaction(function () use ($catalogs): void {
            foreach ($catalogs as $companyName => $departmentNames) {
                $company = Company::firstOrCreate([
                    'name' => $companyName,
                ]);

                foreach ($departmentNames as $departmentName) {
                    $company->departments()->firstOrCreate([
                        'name' => $departmentName,
                    ]);
                }
            }
        });
    }
}
