<?php

namespace App\Http\Controllers;

use App\Models\DirectoryAuditEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryAuditController extends Controller
{
    private const DISPLAY_TIMEZONE = 'America/Guatemala';

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'directory_user_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $action = (string) ($validated['action'] ?? '');
        $from = (string) ($validated['from'] ?? '');
        $to = (string) ($validated['to'] ?? '');
        $directoryUserId = (int) ($validated['directory_user_id'] ?? 0);

        if ($from !== '' && $to !== '' && $to < $from) {
            throw ValidationException::withMessages([
                'to' => 'La fecha final debe ser igual o posterior a la inicial.',
            ]);
        }

        $query = DirectoryAuditEntry::query();

        if ($search !== '') {
            $query->whereAny(
                ['subject_name', 'subject_email', 'actor_name', 'actor_email'],
                'like',
                "%{$search}%",
            );
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($from !== '') {
            $fromUtc = CarbonImmutable::parse($from, self::DISPLAY_TIMEZONE)
                ->startOfDay()
                ->utc();

            $query->where('created_at', '>=', $fromUtc->format('Y-m-d H:i:s'));
        }

        if ($to !== '') {
            $untilUtc = CarbonImmutable::parse($to, self::DISPLAY_TIMEZONE)
                ->addDay()
                ->startOfDay()
                ->utc();

            $query->where('created_at', '<', $untilUtc->format('Y-m-d H:i:s'));
        }

        if ($directoryUserId > 0) {
            $query->where('directory_user_id', $directoryUserId);
        }

        $entries = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DirectoryAuditEntry $entry): array => [
                'id' => $entry->id,
                'directory_user_id' => $entry->directory_user_id,
                'actor_name' => $entry->actor_name,
                'actor_email' => $entry->actor_email,
                'action' => $entry->action,
                'subject_name' => $entry->subject_name,
                'subject_email' => $entry->subject_email,
                'subject_company' => $entry->subject_company,
                'subject_department' => $entry->subject_department,
                'changes' => $entry->changes,
                'created_at' => $entry->created_at->toIso8601String(),
                'created_at_local' => $entry->created_at
                    ->setTimezone(self::DISPLAY_TIMEZONE)
                    ->format('d/m/Y H:i'),
            ]);

        return Inertia::render('directory-audit/index', [
            'entries' => $entries,
            'filters' => [
                'search' => $search,
                'action' => $action,
                'from' => $from,
                'to' => $to,
                'directory_user_id' => $directoryUserId ?: null,
            ],
        ]);
    }
}
