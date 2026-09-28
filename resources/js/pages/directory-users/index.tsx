import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import DeleteDirectoryUser from '@/components/delete-directory-user';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, edit, index, show } from '@/routes/directory-users';

type DirectoryUser = {
    id: number;
    full_name: string;
    email: string;
    company_name: string;
    department_name: string;
    photo_url: string;
};

type Company = {
    id: number;
    name: string;
};

type Department = {
    id: number;
    company_id: number;
    name: string;
};

type Filters = {
    search: string;
    company_id: number | null;
    department_id: number | null;
};

type Props = {
    users: {
        data: DirectoryUser[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    companies: Company[];
    departments: Department[];
    filters: Filters;
    deletionNotice?: string | null;
    errors?: Record<string, string>;
};

export default function DirectoryUsers({
    users,
    companies,
    departments,
    filters,
    deletionNotice,
    errors = {},
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [companyId, setCompanyId] = useState(
        filters.company_id?.toString() ?? '',
    );
    const [departmentId, setDepartmentId] = useState(
        filters.department_id?.toString() ?? '',
    );
    const [processing, setProcessing] = useState(false);

    const availableDepartments = departments.filter(
        (department) => department.company_id.toString() === companyId,
    );

    const hasFilters =
        filters.search !== '' ||
        filters.company_id !== null ||
        filters.department_id !== null;

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            index().url,
            {
                search: search.trim() || undefined,
                company_id: companyId || undefined,
                department_id: departmentId || undefined,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function changePage(url: string | null) {
        if (url) {
            router.visit(url, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Usuarios" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Usuarios
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Consultá las personas registradas por empresa y
                            departamento.
                        </p>
                    </div>

                    <Button asChild>
                        <Link href={create()}>Nuevo usuario</Link>
                    </Button>
                </div>

                {deletionNotice && (
                    <div
                        role="status"
                        className="rounded-lg border bg-muted/40 px-4 py-3 text-sm"
                    >
                        {deletionNotice}
                    </div>
                )}

                <form
                    onSubmit={applyFilters}
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div className="space-y-2">
                        <Label htmlFor="search">Buscar</Label>
                        <Input
                            id="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Nombre, apellido o correo"
                            maxLength={100}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="company">Empresa</Label>
                        <select
                            id="company"
                            value={companyId}
                            onChange={(event) => {
                                setCompanyId(event.target.value);
                                setDepartmentId('');
                            }}
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Todas las empresas</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>
                                    {company.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="department">Departamento</Label>
                        <select
                            id="department"
                            value={departmentId}
                            onChange={(event) =>
                                setDepartmentId(event.target.value)
                            }
                            disabled={!companyId}
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50"
                        >
                            <option value="">
                                {companyId
                                    ? 'Todos los departamentos'
                                    : 'Seleccioná una empresa'}
                            </option>
                            {availableDepartments.map((department) => (
                                <option
                                    key={department.id}
                                    value={department.id}
                                >
                                    {department.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex items-end gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Buscando…' : 'Aplicar'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => router.get(index().url)}
                        >
                            Limpiar
                        </Button>
                    </div>
                </form>

                {Object.keys(errors).length > 0 && (
                    <div role="alert" className="text-sm text-destructive">
                        {Object.entries(errors).map(([field, message]) => (
                            <p key={field}>{message}</p>
                        ))}
                    </div>
                )}

                <section
                    aria-label="Listado de usuarios"
                    className="overflow-hidden rounded-xl border"
                >
                    <div className="border-b px-4 py-3 text-sm text-muted-foreground">
                        {users.total}{' '}
                        {users.total === 1 ? 'resultado' : 'resultados'}
                    </div>

                    {users.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <h2 className="font-medium">
                                {hasFilters
                                    ? 'No encontramos coincidencias'
                                    : 'Todavía no hay usuarios registrados'}
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {hasFilters
                                    ? 'Probá con otros filtros o limpiá la búsqueda.'
                                    : 'Los usuarios que registres aparecerán aquí.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Usuarios registrados y su ubicación
                                    organizacional
                                </caption>
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Fotografía
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Nombre completo
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Correo
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Empresa
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Departamento
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-t hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <img
                                                    src={user.photo_url}
                                                    alt={`Fotografía de ${user.full_name}`}
                                                    width={48}
                                                    height={48}
                                                    loading="lazy"
                                                    className="size-12 rounded-lg object-cover"
                                                />
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                <Link
                                                    href={show(user.id)}
                                                    className="transition-colors duration-200 hover:text-primary focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                                >
                                                    {user.full_name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.email}
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.company_name}
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.department_name}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(user.id)}
                                                            aria-label={`Editar a ${user.full_name}`}
                                                        >
                                                            Editar
                                                        </Link>
                                                    </Button>

                                                    <DeleteDirectoryUser
                                                        user={user}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {users.total > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                            <p className="text-sm text-muted-foreground">
                                {users.from}–{users.to} de {users.total}
                            </p>
                            <div className="flex items-center gap-3">
                                <Button
                                    variant="outline"
                                    disabled={!users.prev_page_url}
                                    onClick={() =>
                                        changePage(users.prev_page_url)
                                    }
                                >
                                    Anterior
                                </Button>
                                <span className="text-sm">
                                    {users.current_page} / {users.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    disabled={!users.next_page_url}
                                    onClick={() =>
                                        changePage(users.next_page_url)
                                    }
                                >
                                    Siguiente
                                </Button>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

DirectoryUsers.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: index(),
        },
    ],
};
