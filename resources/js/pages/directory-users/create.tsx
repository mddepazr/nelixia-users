import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import InputError from '@/components/input-error';
import PhotoCropper from '@/components/photo-cropper';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/directory-users';

type Company = {
    id: number;
    name: string;
};

type Department = {
    id: number;
    company_id: number;
    name: string;
};

type Props = {
    companies: Company[];
    departments: Department[];
};

type UserForm = {
    first_name: string;
    last_name: string;
    email: string;
    company_id: string;
    department_id: string;
    photo: File | null;
};

const textFields = [
    {
        name: 'first_name',
        label: 'Nombre',
        type: 'text',
        maxLength: 100,
    },
    {
        name: 'last_name',
        label: 'Apellido',
        type: 'text',
        maxLength: 100,
    },
    {
        name: 'email',
        label: 'Correo electrónico',
        type: 'email',
        maxLength: 254,
    },
] as const;

function readPhoto(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => {
            if (typeof reader.result === 'string') {
                resolve(reader.result);
            } else {
                reject(new Error('No se pudo leer la fotografía.'));
            }
        };

        reader.onerror = () =>
            reject(new Error('No se pudo leer la fotografía.'));

        reader.onabort = () => reject(new Error('La lectura fue cancelada.'));

        reader.readAsDataURL(file);
    });
}

export default function CreateDirectoryUser({ companies, departments }: Props) {
    const {
        data,
        setData,
        post,
        processing,
        progress,
        errors,
        setError,
        clearErrors,
    } = useForm<UserForm>({
        first_name: '',
        last_name: '',
        email: '',
        company_id: '',
        department_id: '',
        photo: null,
    });

    const [source, setSource] = useState<string | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [photoLoading, setPhotoLoading] = useState(false);

    // El servidor también puede devolver un error general de guardado.
    const messages: Record<string, string | undefined> = errors;

    const availableDepartments = departments.filter(
        (department) => department.company_id === Number(data.company_id),
    );

    async function selectPhoto(event: ChangeEvent<HTMLInputElement>) {
        const file = event.currentTarget.files?.[0];
        event.currentTarget.value = '';

        if (!file) {
            return;
        }

        clearErrors('photo');

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setError('photo', 'Seleccioná una imagen JPG, PNG o WebP.');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setError(
                'photo',
                'La imagen original debe pesar como máximo 10 MB.',
            );
            return;
        }

        setPhotoLoading(true);

        try {
            const imageSource = await readPhoto(file);

            // Verificamos que el navegador pueda abrir la imagen.
            const image = new Image();
            image.src = imageSource;
            await image.decode();

            setSource(imageSource);
            setPreview(null);
            setData('photo', null);
        } catch {
            setError('photo', 'No se pudo abrir esa imagen. Seleccioná otra.');
        } finally {
            setPhotoLoading(false);
        }
    }

    async function confirmPhoto(photo: File) {
        setPhotoLoading(true);

        try {
            const croppedPreview = await readPhoto(photo);

            setData('photo', photo);
            setPreview(croppedPreview);
            setSource(null);
            clearErrors('photo');
        } catch {
            setError(
                'photo',
                'No se pudo preparar la vista previa. Intentá nuevamente.',
            );
        } finally {
            setPhotoLoading(false);
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (processing || photoLoading) {
            return;
        }

        if (source || !data.photo) {
            setError(
                'photo',
                'Seleccioná una fotografía y confirmá el recorte.',
            );
            return;
        }

        post(store().url, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

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

                <form onSubmit={submit} className="space-y-6">
                    {messages.save && (
                        <p
                            role="alert"
                            className="rounded-lg border border-destructive p-3 text-sm text-destructive"
                        >
                            {messages.save}
                        </p>
                    )}

                    <fieldset
                        disabled={processing || photoLoading}
                        className="space-y-6 rounded-xl border p-4 md:p-6"
                    >
                        <legend className="px-2 font-medium">
                            Datos del usuario
                        </legend>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {textFields.map((field) => (
                                <div key={field.name} className="space-y-2">
                                    <Label htmlFor={field.name}>
                                        {field.label}
                                    </Label>

                                    <Input
                                        id={field.name}
                                        name={field.name}
                                        type={field.type}
                                        maxLength={field.maxLength}
                                        value={data[field.name]}
                                        required
                                        aria-invalid={Boolean(
                                            errors[field.name],
                                        )}
                                        aria-describedby={`${field.name}-error`}
                                        onChange={(event) =>
                                            setData(
                                                field.name,
                                                event.target.value,
                                            )
                                        }
                                    />

                                    <InputError
                                        id={`${field.name}-error`}
                                        message={errors[field.name]}
                                    />
                                </div>
                            ))}

                            <div className="space-y-2">
                                <Label htmlFor="company_id">Empresa</Label>

                                <select
                                    id="company_id"
                                    value={data.company_id}
                                    required
                                    aria-invalid={Boolean(errors.company_id)}
                                    aria-describedby="company-error"
                                    onChange={(event) => {
                                        setData({
                                            ...data,
                                            company_id: event.target.value,
                                            department_id: '',
                                        });
                                    }}
                                    className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        Seleccioná una empresa
                                    </option>
                                    {companies.map((company) => (
                                        <option
                                            key={company.id}
                                            value={company.id}
                                        >
                                            {company.name}
                                        </option>
                                    ))}
                                </select>

                                <InputError
                                    id="company-error"
                                    message={errors.company_id}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="department_id">
                                    Departamento
                                </Label>

                                <select
                                    id="department_id"
                                    value={data.department_id}
                                    required
                                    disabled={!data.company_id}
                                    aria-invalid={Boolean(errors.department_id)}
                                    aria-describedby="department-error"
                                    onChange={(event) =>
                                        setData(
                                            'department_id',
                                            event.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-md border bg-background px-3 text-sm disabled:opacity-50"
                                >
                                    <option value="">
                                        Seleccioná un departamento
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

                                <InputError
                                    id="department-error"
                                    message={errors.department_id}
                                />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <Label htmlFor="photo">Fotografía</Label>

                            <Input
                                id="photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={selectPhoto}
                                disabled={Boolean(source)}
                                aria-invalid={Boolean(errors.photo)}
                                aria-describedby="photo-help photo-error"
                                className="h-auto py-2 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:font-medium file:text-foreground"
                            />

                            <p
                                id="photo-help"
                                className="text-sm text-muted-foreground"
                            >
                                JPG, PNG o WebP. Imagen original de hasta 10 MB.
                                Confirmá el recorte antes de guardar.
                            </p>

                            {photoLoading && (
                                <p role="status" className="text-sm">
                                    Preparando fotografía…
                                </p>
                            )}

                            {source && (
                                <div inert={processing || photoLoading}>
                                    <PhotoCropper
                                        key={source}
                                        image={source}
                                        onConfirm={confirmPhoto}
                                        onCancel={() => setSource(null)}
                                    />
                                </div>
                            )}

                            {preview && (
                                <figure className="space-y-2">
                                    <img
                                        src={preview}
                                        alt="Vista previa de la fotografía recortada"
                                        width={160}
                                        height={160}
                                        className="size-40 rounded-lg border object-cover"
                                    />
                                    <figcaption className="text-sm text-muted-foreground">
                                        Recorte confirmado. Esta fotografía se
                                        guardará.
                                    </figcaption>
                                </figure>
                            )}

                            <InputError
                                id="photo-error"
                                message={errors.photo}
                            />
                        </div>
                    </fieldset>

                    {processing && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            {progress?.percentage != null
                                ? `Enviando datos: ${progress.percentage}%`
                                : 'Guardando usuario…'}
                        </p>
                    )}

                    <div className="flex gap-3">
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                photoLoading ||
                                Boolean(source) ||
                                !data.photo
                            }
                        >
                            {processing ? 'Guardando…' : 'Guardar usuario'}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing || photoLoading}
                            asChild
                        >
                            <Link
                                href={index()}
                                onClick={(event) => {
                                    if (processing || photoLoading) {
                                        event.preventDefault();
                                    }
                                }}
                            >
                                Volver
                            </Link>
                        </Button>
                    </div>
                </form>
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
