<?php

namespace App\Actions\DirectoryUsers;

use App\Models\DirectoryUser;
use App\Services\UserPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateDirectoryUser
{
    public function __construct(
        private readonly UserPhotoStorage $photos,
    ) {}

    public function handle(
        string $firstName,
        string $lastName,
        string $email,
        int $departmentId,
        UploadedFile $photo,
    ): DirectoryUser {
        $path = $this->photos->store($photo);

        try {
            return DB::transaction(fn (): DirectoryUser => DirectoryUser::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'department_id' => $departmentId,
                'photo_path' => $path,
            ]));
        } catch (Throwable $exception) {
            // La transacción de la base de datos no revierte archivos en MinIO.
            try {
                $this->photos->delete($path);
            } catch (Throwable $cleanupException) {
                Log::error('No se pudo limpiar una fotografía sin registro.', [
                    'photo_path' => $path,
                    'exception' => $cleanupException,
                ]);
            }

            throw $exception;
        }
    }
}
