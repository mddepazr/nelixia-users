<?php

namespace App\Services;

use App\Models\PendingPhotoDeletion;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class UserPhotoCleanup
{
    public function __construct(
        private readonly UserPhotoStorage $photos,
    ) {}

    public function attempt(PendingPhotoDeletion $pending): bool
    {
        try {
            $this->photos->delete($pending->path);

            if ($pending->delete() !== true) {
                throw new RuntimeException(
                    'No se pudo completar el registro de limpieza.',
                );
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('La eliminación de una fotografía sigue pendiente.', [
                'pending_deletion_id' => $pending->id,
                'photo_path' => $pending->path,
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
