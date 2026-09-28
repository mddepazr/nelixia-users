<?php

namespace App\Actions\Settings;

use App\Models\PendingPhotoDeletion;
use App\Models\User;
use App\Services\UserPhotoCleanup;
use App\Services\UserPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateProfilePhoto
{
    public function __construct(
        private readonly UserPhotoStorage $photos,
        private readonly UserPhotoCleanup $cleanup,
    ) {}

    public function handle(User $user, UploadedFile $photo): void
    {
        $newPath = $this->photos->store($photo, 'account-photos');

        try {
            $pending = DB::transaction(function () use ($user, $newPath): ?PendingPhotoDeletion {
                $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $previousPath = $account->avatar_path;
                $account->avatar_path = $newPath;
                $account->save();

                return $previousPath !== null
                    ? PendingPhotoDeletion::firstOrCreate(['path' => $previousPath])
                    : null;
            });
        } catch (Throwable $exception) {
            try {
                $this->photos->delete($newPath);
            } catch (Throwable $cleanupException) {
                Log::error('No se pudo retirar una foto de perfil sin uso.', [
                    'photo_path' => $newPath,
                    'exception' => $cleanupException,
                ]);
            }

            throw $exception;
        }

        // La sesión puede conservar la instancia que recibió la petición.
        // Refrescarla permite compartir el nuevo avatar en la respuesta.
        $user->refresh();

        if ($pending !== null) {
            $this->cleanup->attempt($pending);
        }
    }
}
