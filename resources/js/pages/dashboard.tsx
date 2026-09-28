import { Head, Link } from '@inertiajs/react';
import { Building2, LayoutGrid, Plus, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/directory-users';

type RecentUser = {
    id: number;
    full_name: string;
    email: string;
    company_name: string;
    department_name: string;
    photo_url: string;
};

type Props = {
    stats: { users: number; companies: number; departments: number };
    recentUsers: RecentUser[];
};

const statCards = [
    { key: 'users', label: 'Usuarios', icon: Users },
    { key: 'companies', label: 'Empresas', icon: Building2 },
    { key: 'departments', label: 'Departamentos', icon: LayoutGrid },
] as const;

export default function Dashboard({ stats, recentUsers }: Props) {
    return (
        <>
            <Head title="Inicio" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Inicio
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Resumen del directorio de usuarios de Nelixia.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden="true" /> Nuevo usuario
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {statCards.map(({ key, label, icon: Icon }) => (
                        <Card key={key}>
                            <CardHeader className="flex flex-row items-center justify-between gap-2">
                                <CardTitle className="text-sm font-medium">
                                    {label}
                                </CardTitle>
                                <Icon
                                    aria-hidden="true"
                                    className="size-5 text-muted-foreground"
                                />
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-semibold tabular-nums">
                                    {stats[key]}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <div className="space-y-1.5">
                            <CardTitle>Usuarios recientes</CardTitle>
                            <CardDescription>
                                Los últimos cinco registros del directorio.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={index()}>Ver todos</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {recentUsers.length === 0 ? (
                            <div className="rounded-lg border border-dashed px-4 py-10 text-center">
                                <p className="font-medium">
                                    Todavía no hay usuarios registrados.
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Creá el primero para comenzar.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {recentUsers.map((user) => (
                                    <li key={user.id}>
                                        <Link
                                            href={show(user.id)}
                                            className="flex items-center gap-3 py-3 transition-colors duration-200 hover:text-primary focus-visible:rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                        >
                                            <img
                                                src={user.photo_url}
                                                alt=""
                                                className="size-11 shrink-0 rounded-full object-cover"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {user.full_name}
                                                </span>
                                                <span className="block truncate text-sm text-muted-foreground">
                                                    {user.email}
                                                </span>
                                            </span>
                                            <span className="hidden text-right text-sm text-muted-foreground sm:block">
                                                <span className="block">
                                                    {user.company_name}
                                                </span>
                                                <span className="block">
                                                    {user.department_name}
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Inicio',
            href: dashboard(),
        },
    ],
};
