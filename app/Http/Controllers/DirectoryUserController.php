<?php

namespace App\Http\Controllers;

use App\Actions\DirectoryUsers\CreateDirectoryUser;
use App\Http\Requests\StoreDirectoryUserRequest;
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
