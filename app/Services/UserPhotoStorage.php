<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UserPhotoStorage
{
    public function store(UploadedFile $photo): string
    {
        $path = Storage::disk('s3')->putFile(
            'user-photos',
            $photo,
            ['visibility' => 'private'],
        );

        if ($path === false) {
            throw new RuntimeException('No se pudo almacenar la fotografía.');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        if (! Storage::disk('s3')->delete($path)) {
            throw new RuntimeException(
                "No se pudo eliminar la fotografía: {$path}",
            );
        }
    }
}
