import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    History,
    Mail,
    Pencil,
    Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as auditIndex } from '@/routes/directory-audit';
import { edit, index } from '@/routes/directory-users';

type DirectoryUser = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    company_name: string;
    department_name: string;
    photo_url: string;
    created_at: string;
    updated_at: string;
};

export default function ShowDirectoryUser({
    directoryUser: user,
}: {
    directoryUser: DirectoryUser;
}) {
    const name = `${user.first_name} ${user.last_name}`;

    return (
        <>
            <Head title={name} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <Button asChild variant="ghost" className="self-start">
                    <Link href={index()}>
                        <ArrowLeft aria-hidden="true" /> Volver a usuarios
                    </Link>
                </Button>

                <Card className="overflow-hidden">
                    <div className="h-24 bg-gradient-to-r from-primary/20 via-primary/10 to-background md:h-32" />
                    <CardContent className="px-5 pb-6 md:px-8">
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <img
                                    src={user.photo_url}
                                    alt={`Fotografía de ${name}`}
                                    width={144}
                                    height={144}
                                    className="-mt-12 size-28 rounded-2xl border-4 border-card bg-card object-cover shadow-md md:size-36"
                                />
                                <div className="space-y-1 pb-1">
                                    <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                        {name}
                                    </h1>
                                    <p className="text-muted-foreground">
                                        {user.department_name} ·{' '}
                                        {user.company_name}
                                    </p>
                                </div>
                            </div>
                            <Button asChild className="sm:mb-1">
                                <Link href={edit(user.id)}>
                                    <Pencil aria-hidden="true" /> Editar usuario
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-5 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información de contacto</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="flex items-start gap-3">
                                <Mail
                                    aria-hidden="true"
                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                />
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">
                                        Correo electrónico
                                    </p>
                                    <a
                                        href={`mailto:${user.email}`}
                                        className="font-medium break-all text-primary hover:underline"
                                    >
                                        {user.email}
                                    </a>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Users
                                    aria-hidden="true"
                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Nombre completo
                                    </p>
                                    <p className="font-medium">{name}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Organización</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="flex items-start gap-3">
                                <Building2
                                    aria-hidden="true"
                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Empresa
                                    </p>
                                    <p className="font-medium">
                                        {user.company_name}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Users
                                    aria-hidden="true"
                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Departamento
                                    </p>
                                    <p className="font-medium">
                                        {user.department_name}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Actividad del registro</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div className="flex flex-col gap-4 text-sm sm:flex-row sm:gap-10">
                            <div className="flex items-start gap-3">
                                <CalendarDays
                                    aria-hidden="true"
                                    className="size-5 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p className="text-muted-foreground">
                                        Registrado
                                    </p>
                                    <p className="font-medium">
                                        {user.created_at} (Guatemala)
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <CalendarDays
                                    aria-hidden="true"
                                    className="size-5 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p className="text-muted-foreground">
                                        Última actualización
                                    </p>
                                    <p className="font-medium">
                                        {user.updated_at} (Guatemala)
                                    </p>
                                </div>
                            </div>
                        </div>
                        <Button asChild variant="outline">
                            <Link
                                href={auditIndex({
                                    query: { directory_user_id: user.id },
                                })}
                            >
                                <History aria-hidden="true" /> Ver historial
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowDirectoryUser.layout = {
    breadcrumbs: [
        { title: 'Usuarios', href: index() },
        { title: 'Perfil', href: '#' },
    ],
};
