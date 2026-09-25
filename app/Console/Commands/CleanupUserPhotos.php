<?php

namespace App\Console\Commands;

use App\Models\PendingPhotoDeletion;
use App\Services\UserPhotoCleanup;
use Illuminate\Console\Command;

class CleanupUserPhotos extends Command
{
    protected $signature = 'app:cleanup-user-photos';

    protected $description = 'Reintenta la eliminación de fotografías pendientes';

    public function handle(UserPhotoCleanup $cleanup): int
    {
        $completed = 0;
        $failed = 0;

        foreach (PendingPhotoDeletion::query()->lazyById(100) as $pending) {
            if ($cleanup->attempt($pending)) {
                $completed++;
            } else {
                $failed++;
            }
        }

        $this->info("Fotografías eliminadas: {$completed}.");

        if ($failed > 0) {
            $this->warn("Fotografías todavía pendientes: {$failed}.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
