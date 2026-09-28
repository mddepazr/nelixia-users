import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/directory-audit';

type Change = { from: string | null; to: string | null };

type Entry = {
    id: number;
    directory_user_id: number;
    action: 'created' | 'updated' | 'deleted';
    actor_name: string | null;
    actor_email: string | null;
    subject_name: string;
    subject_email: string;
    subject_company: string;
    subject_department: string;
    changes: Record<string, Change> | null;
    created_at: string;
    created_at_local: string;
};

type Filters = {
    search: string;
    action: string;
    from: string;
    to: string;
    directory_user_id: number | null;
};

type Props = {
    entries: {
        data: Entry[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Filters;
};

const actionLabels = {
    created: 'Creado',
    updated: 'Actualizado',
    deleted: 'Eliminado',
};

const fieldLabels: Record<string, string> = {
    first_name: 'Nombre',
    last_name: 'Apellido',
    email: 'Correo',
    company: 'Empresa',
    department: 'Departamento',
    photo: 'Fotografía',
};

export default function DirectoryAuditIndex({ entries, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [action, setAction] = useState(filters.action);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            index().url,
            {
                search: search.trim() || undefined,
                action: action || undefined,
                from: from || undefined,
                to: to || undefined,
                directory_user_id: filters.directory_user_id || undefined,
            },
            { preserveState: false, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Historial" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Historial de cambios
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Movimientos del directorio desde que se activó el
                        historial.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div className="space-y-2">
                        <Label htmlFor="audit-search">Buscar</Label>
                        <Input
                            id="audit-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Usuario o administrador"
                            maxLength={100}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="audit-action">Acción</Label>
                        <select
                            id="audit-action"
                            value={action}
                            onChange={(event) => setAction(event.target.value)}
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Todas</option>
                            <option value="created">Creación</option>
                            <option value="updated">Actualización</option>
                            <option value="deleted">Eliminación</option>
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="audit-from">Desde</Label>
                        <Input
                            id="audit-from"
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="audit-to">Hasta</Label>
                        <Input
                            id="audit-to"
                            type="date"
                            min={from || undefined}
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-4">
                        <Button type="submit">Aplicar filtros</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.get(index().url)}
                        >
                            Limpiar
                        </Button>
                        {filters.directory_user_id && (
                            <span className="text-sm text-muted-foreground">
                                Mostrando usuario #{filters.directory_user_id}
                            </span>
                        )}
                    </div>
                </form>

                <section
                    className="space-y-4"
                    aria-label="Eventos del historial"
                >
                    <p className="text-sm text-muted-foreground">
                        {entries.total}{' '}
                        {entries.total === 1 ? 'evento' : 'eventos'}
                    </p>
                    {entries.data.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                            No hay cambios para estos filtros.
                        </div>
                    ) : (
                        <ol className="space-y-3">
                            {entries.data.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="rounded-xl border bg-card p-4 shadow-sm"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-md bg-muted px-2 py-1 text-xs font-medium">
                                                    {actionLabels[entry.action]}
                                                </span>
                                                <strong>
                                                    {entry.subject_name}
                                                </strong>
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {entry.subject_email} ·{' '}
                                                {entry.subject_company} /{' '}
                                                {entry.subject_department}
                                            </p>
                                        </div>
                                        <time
                                            dateTime={entry.created_at}
                                            className="text-sm text-muted-foreground"
                                        >
                                            {entry.created_at_local} (Guatemala)
                                        </time>
                                    </div>
                                    <p className="mt-3 text-sm">
                                        Por{' '}
                                        {entry.actor_name
                                            ? `${entry.actor_name} (${entry.actor_email})`
                                            : 'Instalación inicial'}
                                    </p>
                                    {entry.action === 'updated' &&
                                        entry.changes && (
                                            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm">
                                                {Object.entries(
                                                    entry.changes,
                                                ).map(([field, change]) => (
                                                    <li key={field}>
                                                        {fieldLabels[field] ??
                                                            field}
                                                        :{' '}
                                                        {field === 'photo'
                                                            ? 'reemplazada'
                                                            : `${change.from ?? '—'} → ${change.to ?? '—'}`}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>

                <div className="flex items-center justify-between gap-3">
                    <span className="text-sm text-muted-foreground">
                        Página {entries.current_page} de {entries.last_page}
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            disabled={!entries.prev_page_url}
                            onClick={() =>
                                entries.prev_page_url &&
                                router.visit(entries.prev_page_url, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Anterior
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!entries.next_page_url}
                            onClick={() =>
                                entries.next_page_url &&
                                router.visit(entries.next_page_url, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Siguiente
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
