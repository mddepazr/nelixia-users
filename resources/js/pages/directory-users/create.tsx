import { Head } from '@inertiajs/react';
import DirectoryUserForm from '@/components/directory-user-form';
import type { DirectoryUserFormProps } from '@/components/directory-user-form';
import { create, index } from '@/routes/directory-users';

export default function CreateDirectoryUser(
    props: Omit<DirectoryUserFormProps, 'directoryUser'>,
) {
    return (
        <>
            <Head title="Nuevo usuario" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Nuevo usuario</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Completá los datos y confirmá el recorte de la
                        fotografía.
                    </p>
                </div>

                <DirectoryUserForm {...props} />
            </div>
        </>
    );
}

CreateDirectoryUser.layout = {
    breadcrumbs: [
        { title: 'Usuarios', href: index() },
        { title: 'Nuevo usuario', href: create() },
    ],
};
