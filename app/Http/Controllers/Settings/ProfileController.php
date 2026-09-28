<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateProfilePhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfilePhotoRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\PendingPhotoDeletion;
use App\Models\User;
use App\Services\UserPhotoCleanup;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function updatePhoto(
        ProfilePhotoRequest $request,
        UpdateProfilePhoto $updatePhoto,
    ): RedirectResponse {
        $user = $request->user();
        $photo = $request->file('photo');

        if (! $user instanceof User || ! $photo instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'photo' => 'Seleccioná y confirmá el recorte de una fotografía.',
            ]);
        }

        try {
            $updatePhoto->handle($user, $photo);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'photo' => 'No se pudo guardar la fotografía. Intentá nuevamente.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fotografía actualizada correctamente.']);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, UserPhotoCleanup $cleanup): RedirectResponse
    {
        $user = $request->user();

        $pending = DB::transaction(function () use ($user): ?PendingPhotoDeletion {
            $avatarPath = $user->avatar_path;
            $pending = $avatarPath !== null
                ? PendingPhotoDeletion::firstOrCreate(['path' => $avatarPath])
                : null;

            if ($user->delete() !== true) {
                throw new RuntimeException('No se pudo eliminar la cuenta.');
            }

            return $pending;
        });

        Auth::logout();

        if ($pending !== null) {
            $cleanup->attempt($pending);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
