<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryUserController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-admin'])->group(function () {
    Route::get('/', function (): RedirectResponse {
        return to_route('dashboard');
    })->name('home');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('users', [DirectoryUserController::class, 'index'])
        ->name('directory-users.index');

    Route::post('users', [DirectoryUserController::class, 'store'])
        ->name('directory-users.store');

    Route::get('users/create', [DirectoryUserController::class, 'create'])
        ->name('directory-users.create');

    Route::get('users/{directoryUser}/edit', [DirectoryUserController::class, 'edit'])
        ->name('directory-users.edit');

    Route::patch('users/{directoryUser}', [DirectoryUserController::class, 'update'])
        ->name('directory-users.update');

    Route::delete('users/{directoryUser}', [DirectoryUserController::class, 'destroy'])
        ->name('directory-users.destroy');
});

require __DIR__.'/settings.php';
