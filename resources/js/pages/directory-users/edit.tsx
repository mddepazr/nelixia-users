import { Head } from '@inertiajs/react';
import DirectoryUserForm from '@/components/directory-user-form';
import type {
    DirectoryUserData,
    DirectoryUserFormProps,
} from '@/components/directory-user-form';
import { index } from '@/routes/directory-users';

type Props = DirectoryUserFormProps & {
    directoryUser: DirectoryUserData;
};

export default function EditDirectoryUser(props: Props) {
    return (
        <>
            <Head title="Editar usuario" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Editar usuario</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Actualizá los datos. Cambiar la fotografía es opcional.
                    </p>
                </div>

                <DirectoryUserForm key={props.directoryUser.id} {...props} />
            </div>
        </>
    );
}

EditDirectoryUser.layout = {
    breadcrumbs: [
        { title: 'Usuarios', href: index() },
        { title: 'Editar usuario', href: '#' },
    ],
};
