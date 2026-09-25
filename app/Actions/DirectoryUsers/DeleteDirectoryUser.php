<?php

namespace App\Actions\DirectoryUsers;

use App\Models\DirectoryUser;
use App\Models\PendingPhotoDeletion;
use App\Services\UserPhotoCleanup;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteDirectoryUser
{
    public function __construct(
        private readonly UserPhotoCleanup $cleanup,
    ) {}

    /**
     * Devuelve true si también se completó la eliminación de la fotografía.
     */
    public function handle(DirectoryUser $directoryUser): bool
    {
        $pending = DB::transaction(function () use ($directoryUser): PendingPhotoDeletion {
            $currentUser = DirectoryUser::query()
                ->whereKey($directoryUser->id)
                ->lockForUpdate()
                ->firstOrFail();

            // La referencia al archivo queda guardada antes de borrar al usuario.
            $pending = PendingPhotoDeletion::firstOrCreate([
                'path' => $currentUser->photo_path,
            ]);

            if ($currentUser->delete() !== true) {
                throw new RuntimeException('No se pudo eliminar el usuario.');
            }

            return $pending;
        });

        // MinIO se contacta después de confirmar los cambios en la base de datos.
        return $this->cleanup->attempt($pending);
    }
}
