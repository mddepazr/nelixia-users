<?php

namespace App\Actions\DirectoryUsers;

use App\Models\DirectoryUser;
use App\Services\DirectoryAudit;
use App\Services\UserPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateDirectoryUser
{
    public function __construct(
        private readonly UserPhotoStorage $photos,
        private readonly DirectoryAudit $audit,
    ) {}

    public function handle(
        DirectoryUser $directoryUser,
        string $firstName,
        string $lastName,
        string $email,
        int $departmentId,
        ?UploadedFile $photo,
    ): DirectoryUser {
        $newPhotoPath = $photo !== null
            ? $this->photos->store($photo)
            : null;

        try {
            [$updatedUser, $previousPhotoPath] = DB::transaction(
                function () use (
                    $directoryUser,
                    $firstName,
                    $lastName,
                    $email,
                    $departmentId,
                    $newPhotoPath,
                ) {
                    $currentUser = DirectoryUser::query()
                        ->whereKey($directoryUser->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $before = $this->audit->snapshot($currentUser);
                    $previousPhotoPath = $currentUser->photo_path;

                    $currentUser->fill([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'department_id' => $departmentId,
                    ]);

                    if ($newPhotoPath !== null) {
                        $currentUser->photo_path = $newPhotoPath;
                    }

                    $currentUser->save();
                    $this->audit->updated($currentUser, $before);

                    return [$currentUser, $previousPhotoPath];
                },
            );
        } catch (Throwable $exception) {
            // Si falla la base de datos, retiramos la nueva foto.
            if ($newPhotoPath !== null) {
                $this->removeUnusedPhoto($newPhotoPath);
            }

            throw $exception;
        }

        // La foto anterior se retira después de confirmar los nuevos datos.
        if (
            $newPhotoPath !== null
            && $previousPhotoPath !== $newPhotoPath
        ) {
            $this->removeUnusedPhoto($previousPhotoPath);
        }

        return $updatedUser;
    }

    private function removeUnusedPhoto(string $path): void
    {
        try {
            $this->photos->delete($path);
        } catch (Throwable $exception) {
            Log::error('No se pudo eliminar una fotografía sin uso.', [
                'photo_path' => $path,
                'exception' => $exception,
            ]);
        }
    }
}
