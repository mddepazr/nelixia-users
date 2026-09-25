<?php

use App\Http\Controllers\DirectoryUserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'can:access-admin'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

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
