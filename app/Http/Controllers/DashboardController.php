<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\DirectoryUser;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $recentUsers = DirectoryUser::query()
            ->with('department.company')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return Inertia::render('dashboard', [
            'stats' => [
                'users' => DirectoryUser::query()->count(),
                'companies' => Company::query()->count(),
                'departments' => Department::query()->count(),
            ],
            'recentUsers' => $recentUsers->map(fn (DirectoryUser $user): array => [
                'id' => $user->getKey(),
                'full_name' => $user->first_name.' '.$user->last_name,
                'email' => $user->email,
                'company_name' => $user->department->company->name,
                'department_name' => $user->department->name,
                'photo_url' => Storage::disk('s3')->temporaryUrl(
                    $user->photo_path,
                    now()->addMinutes(15),
                ),
            ])->values(),
        ]);
    }
}
