<?php

namespace App\Http\Controllers;

use App\Actions\DirectoryUsers\CreateDirectoryUser;
use App\Actions\DirectoryUsers\DeleteDirectoryUser;
use App\Actions\DirectoryUsers\UpdateDirectoryUser;
use App\Http\Requests\StoreDirectoryUserRequest;
use App\Http\Requests\UpdateDirectoryUserRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\DirectoryUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DirectoryUserController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = $request->string('search')->trim()->toString();
        $companyId = $request->integer('company_id');
        $departmentId = $request->integer('department_id');

        // Carga las relaciones juntas para evitar consultas por cada fila.
        $query = DirectoryUser::query()->with('department.company');

        if ($search !== '') {
            $query->whereAny(
                ['first_name', 'last_name', 'email'],
                'like',
                "%{$search}%",
            );
        }

        if ($companyId > 0) {
            $query->whereRelation('department', 'company_id', $companyId);
        }

        if ($departmentId > 0) {
            $query->where('department_id', $departmentId);
        }

        $users = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DirectoryUser $user): array => $this->listItem($user));

        return Inertia::render('directory-users/index', [
            'deletionNotice' => $request->session()->get('deletion_notice'),
            'users' => $users,
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'company_id', 'name']),
            'filters' => [
                'search' => $search,
                'company_id' => $companyId ?: null,
                'department_id' => $departmentId ?: null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('directory-users/create', [
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'company_id', 'name']),
        ]);
    }

    public function show(DirectoryUser $directoryUser): Response
    {
        $directoryUser->load('department.company');

        return Inertia::render('directory-users/show', [
            'directoryUser' => [
                'id' => $directoryUser->getKey(),
                'first_name' => $directoryUser->first_name,
                'last_name' => $directoryUser->last_name,
                'email' => $directoryUser->email,
                'company_name' => $directoryUser->department->company->name,
                'department_name' => $directoryUser->department->name,
                'photo_url' => Storage::disk('s3')->temporaryUrl(
                    $directoryUser->photo_path,
                    now()->addMinutes(15),
                ),
                'created_at' => $directoryUser->created_at
                    ->setTimezone('America/Guatemala')
                    ->format('d/m/Y H:i'),
                'updated_at' => $directoryUser->updated_at
                    ->setTimezone('America/Guatemala')
                    ->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function store(
        StoreDirectoryUserRequest $request,
        CreateDirectoryUser $createUser,
    ): RedirectResponse {
        $photo = $request->file('photo');

        if (! $photo instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'photo' => 'Seleccioná y confirmá el recorte de una fotografía.',
            ]);
        }

        try {
            $createUser->handle(
                firstName: $request->string('first_name')->trim()->toString(),
                lastName: $request->string('last_name')->trim()->toString(),
                email: $request->string('email')->toString(),
                departmentId: $request->integer('department_id'),
                photo: $photo,
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'save' => 'No se pudo guardar el usuario. Intentá nuevamente.',
            ]);
        }

        return redirect()->route('directory-users.index');
    }

    public function edit(DirectoryUser $directoryUser): Response
    {
        $directoryUser->load('department.company');

        return Inertia::render('directory-users/edit', [
            'directoryUser' => [
                'id' => $directoryUser->id,
                'first_name' => $directoryUser->first_name,
                'last_name' => $directoryUser->last_name,
                'email' => $directoryUser->email,
                'company_id' => $directoryUser->department->company->getKey(),
                'department_id' => $directoryUser->department->getKey(),
                'photo_url' => Storage::disk('s3')->temporaryUrl(
                    $directoryUser->photo_path,
                    now()->addMinutes(15),
                ),
            ],
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'company_id', 'name']),
        ]);
    }

    public function update(
        UpdateDirectoryUserRequest $request,
        DirectoryUser $directoryUser,
        UpdateDirectoryUser $updateUser,
    ): RedirectResponse {
        $photo = $request->file('photo');

        if ($photo !== null && ! $photo instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'photo' => 'Seleccioná una fotografía válida.',
            ]);
        }

        try {
            $updateUser->handle(
                directoryUser: $directoryUser,
                firstName: $request->string('first_name')->trim()->toString(),
                lastName: $request->string('last_name')->trim()->toString(),
                email: $request->string('email')->toString(),
                departmentId: $request->integer('department_id'),
                photo: $photo,
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'save' => 'No se pudo actualizar el usuario. Intentá nuevamente.',
            ]);
        }

        return redirect()->route('directory-users.index');
    }

    public function destroy(
        DirectoryUser $directoryUser,
        DeleteDirectoryUser $deleteUser,
    ): RedirectResponse {
        try {
            $photoDeleted = $deleteUser->handle($directoryUser);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'deletion' => 'No se pudo completar la eliminación. Actualizá el listado e intentá nuevamente.',
            ]);
        }

        return redirect()
            ->route('directory-users.index')
            ->with(
                'deletion_notice',
                $photoDeleted
                    ? 'Usuario y fotografía eliminados correctamente.'
                    : 'Usuario eliminado. La eliminación de su fotografía quedó pendiente de reintento.',
            );
    }

    /** @return array<string, int|string> */
    private function listItem(DirectoryUser $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->first_name.' '.$user->last_name,
            'email' => $user->email,
            'company_name' => $user->department->company->name,
            'department_name' => $user->department->name,
            'photo_url' => Storage::disk('s3')->temporaryUrl(
                $user->photo_path,
                now()->addMinutes(15),
            ),
        ];
    }
}
